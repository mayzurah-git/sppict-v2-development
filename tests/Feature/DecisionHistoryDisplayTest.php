<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Livewire\ApplicationList;
use App\Livewire\ViewApplication;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\Meeting;
use App\Models\User;
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

/**
 * PP4.3B: paparan "Sejarah Keputusan Permohonan" (Pra JTICT & Mesyuarat JTICT) daripada application_decisions,
 * mengikut kronologi dan tahap akses; ketekalan label status dalam senarai, butiran & papan pemuka.
 *
 * Nota: dijalankan pada SQLite in-memory; tidak membuktikan tingkah laku MySQL.
 */
class DecisionHistoryDisplayTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting;

    private const AMENDMENT_REMARKS = 'Sila perincikan justifikasi kos fasa kedua projek.';

    private const JTICT_REMARKS = 'Mesyuarat JTICT meminta skop projek dikaji semula.';

    private Agency $agency;

    private User $urusSetia;

    private User $applicant;

    private User $officer;

    private Meeting $meeting;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->urusSetia = User::factory()->create(['name' => 'Siti Urus Setia', 'agency_id' => $this->agency->id]);
        $this->urusSetia->assignRole('pentadbir_urus_setia');
        $this->applicant = User::factory()->create(['name' => 'Ali Pemohon', 'agency_id' => $this->agency->id]);
        $this->applicant->assignRole('pengguna_biasa');
        $this->officer = User::factory()->create(['name' => 'Ahmad Pegawai', 'agency_id' => $this->agency->id]);
        $this->officer->assignRole('pengguna_biasa');

        $this->meeting = $this->openJtictMeeting(['series' => 'Bil. 3/2026', 'meeting_date' => today()->subDays(2)]);
    }

    // --- 1 - 5, 7: SEJARAH (URUS SETIA) ---

    public function test_urus_setia_sees_both_stages_chronologically_with_meeting_and_recorder(): void
    {
        $application = $this->reviewedApplication();
        $records = $application->decisions()->orderBy('id')->get();

        $component = Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertSeeInOrder([
                'Sejarah Keputusan Permohonan',
                'Pra JTICT', 'Ditolak dengan Pindaan', 'Tarikh keputusan: ' . today()->subDays(6)->format('d/m/Y'), self::AMENDMENT_REMARKS,
                'Pra JTICT', 'Diterima', 'Tarikh keputusan: ' . today()->subDays(4)->format('d/m/Y'), 'Tiada ulasan.',
                'Mesyuarat JTICT', 'Kaji Semula', 'Terkini', 'Tarikh keputusan: ' . today()->subDay()->format('d/m/Y'), self::JTICT_REMARKS,
            ])
            // 5: maklumat mesyuarat (nama & tarikh)
            ->assertSeeInOrder(['Mesyuarat:', $this->meeting->label() . ' (' . $this->meeting->meeting_date->format('d/m/Y') . ')'])
            // 7: nama pegawai & masa rekod untuk Urus Setia
            ->assertSee('Direkodkan oleh:')
            ->assertSee('Siti Urus Setia')
            ->assertSee('Masa rekod:')
            ->assertSee($records->last()->created_at->format('d/m/Y h:i A'));

        // Semua rekod dipaparkan sekali sahaja (tiada pendua, tiada sejarah hilang)
        $html = $component->html();
        $this->assertSame(3, substr_count($html, 'wire:key="decision-'));
        $this->assertSame(1, substr_count(substr($html, strpos($html, 'Sejarah Keputusan Permohonan')), '>Terkini<'), 'Satu rekod terkini sahaja');
        foreach ($records as $record) {
            $this->assertSame(1, substr_count($html, 'wire:key="decision-' . $record->id . '"'));
        }
    }

    // --- 6: PEMOHON TIDAK MELIHAT NAMA PEREKOD ---

    public function test_applicant_and_officer_see_history_without_recorder_identity(): void
    {
        $application = $this->reviewedApplication();

        foreach ([$this->applicant, $this->officer] as $viewer) {
            Livewire::actingAs($viewer)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->assertSee('Sejarah Keputusan Permohonan')
                ->assertSeeInOrder(['Ditolak dengan Pindaan', 'Diterima', 'Kaji Semula'])
                ->assertSee(self::AMENDMENT_REMARKS)
                ->assertSee(self::JTICT_REMARKS)
                ->assertSee(today()->subDay()->format('d/m/Y'))
                ->assertSeeInOrder(['Mesyuarat:', $this->meeting->label()])
                ->assertSeeInOrder(['Direkodkan oleh:', 'Urus Setia'])
                ->assertDontSee('Siti Urus Setia')
                ->assertDontSee('Masa rekod:');
        }
    }

    // --- 8: TANPA KEBENARAN ---

    public function test_users_without_view_permission_cannot_see_history(): void
    {
        $application = $this->reviewedApplication();

        $outsider = User::factory()->create(['agency_id' => Agency::create(['code' => 'JPN', 'name' => 'Agensi Lain', 'category' => 'SUK', 'is_active' => true])->id]);
        $outsider->assignRole('pengguna_biasa');
        $superadmin = User::factory()->create(['agency_id' => $this->agency->id]);
        $superadmin->assignRole('superadmin');

        Livewire::actingAs($outsider)->test(ViewApplication::class, ['uuid' => $application->uuid])->assertForbidden();
        $this->actingAs($outsider)->get(route('application.view', $application->uuid))->assertForbidden();
        $this->actingAs($superadmin)->get(route('application.view', $application->uuid))->assertForbidden();
    }

    // --- 9: REKOD TIDAK BERUBAH ---

    public function test_viewing_history_does_not_change_decision_records(): void
    {
        $application = $this->reviewedApplication();
        $before = ApplicationDecision::orderBy('id')->get()->toArray();
        $applicationBefore = $application->fresh()->getAttributes();

        foreach ([$this->urusSetia, $this->applicant, $this->officer] as $viewer) {
            Livewire::actingAs($viewer)->test(ViewApplication::class, ['uuid' => $application->uuid])->assertOk();
            $this->actingAs($viewer)->get(route('application.view', $application->uuid))->assertOk();
        }

        $this->assertSame($before, ApplicationDecision::orderBy('id')->get()->toArray());
        $this->assertSame($applicationBefore, $application->fresh()->getAttributes());
        $this->assertSame('PENDING', $application->fresh()->jtict_decision, 'Medan legacy tidak digunakan');

        // Tiada tindakan awam edit / padam keputusan pada halaman butiran
        $html = Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])->html();
        $this->assertDoesNotMatchRegularExpression('/wire:click="(edit|update|delete|remove)\w*Decision/i', $html);
    }

    // --- 10: KETEKALAN LABEL STATUS ---

    public function test_status_labels_show_actual_jtict_decision_in_lists_detail_and_dashboards(): void
    {
        $review = $this->reviewedApplication('Projek Kaji Semula');
        $approved = $this->acceptedApplication('Projek Lulus');
        $this->decideJtict($approved, Decision::Approved, 'Mesyuarat JTICT meluluskan projek ini.');

        // Butiran
        Livewire::actingAs($this->applicant)->test(ViewApplication::class, ['uuid' => $review->uuid])
            ->assertSee('Selesai – Kaji Semula (JTICT)');

        // Senarai pemohon & papan pemuka pemohon (label + lajur Keputusan)
        Livewire::actingAs($this->applicant)->test(ApplicationList::class)
            ->assertSee('Selesai – Kaji Semula (JTICT)')
            ->assertSee('Selesai – Lulus (JTICT)')
            ->assertSee('JTICT: Kaji Semula')
            ->assertSee('JTICT: Lulus');
        $this->actingAs($this->applicant)->get('/dashboard')
            ->assertOk()
            ->assertSee('Selesai – Kaji Semula (JTICT)')
            ->assertSee('Selesai – Lulus (JTICT)');

        // Senarai Urus Setia (penapis Selesai) & papan pemuka Urus Setia
        Livewire::actingAs($this->urusSetia)->test(ApplicationList::class)
            ->set('statusFilter', ApplicationStatus::Closed->value)
            ->assertSee('Selesai – Kaji Semula (JTICT)')
            ->assertSee('Selesai – Lulus (JTICT)');
        $this->actingAs($this->urusSetia)->get('/dashboard?status=CLOSED')
            ->assertOk()
            ->assertSee('Selesai – Lulus (JTICT)');

        // Tiada keputusan dipaparkan sebagai "Selesai" umum sahaja
        $this->assertSame('Selesai – Kaji Semula (JTICT)', $review->fresh()->statusDisplayLabel());
        $this->assertSame('Selesai – Lulus (JTICT)', $approved->fresh()->statusDisplayLabel());
    }

    // --- PEMBANTU ---

    /**
     * Kronologi: Pra JTICT Ditolak dengan Pindaan (H-6) → Lengkap semula & Pra JTICT Diterima (H-4)
     * → Mesyuarat JTICT (H-2) → keputusan JTICT Kaji Semula (H-1).
     */
    private function reviewedApplication(string $title = 'Projek Sejarah Keputusan'): Application
    {
        $this->travelTo(today()->subDays(6)->setTime(9, 0));
        $application = $this->completeApplication($title);
        $this->service()->decide($application, DecisionStage::PreJtict, Decision::AmendmentRequired, self::AMENDMENT_REMARKS, today(), $this->urusSetia);
        $this->travelBack();

        $this->travelTo(today()->subDays(4)->setTime(9, 0));
        // Penghantaran semula disimulasikan (aliran borang diuji dalam PreJtictDecisionTest)
        $application->fresh()->forceFill(['status' => ApplicationStatus::Submitted->value])->save();
        app(ApplicationReviewService::class)->review($application->fresh(), ApplicationStatus::Complete, null, $this->urusSetia);
        $this->service()->decide($application->fresh(), DecisionStage::PreJtict, Decision::Accepted, null, today(), $this->urusSetia);
        $this->travelBack();

        $this->decideJtict($application, Decision::Review, self::JTICT_REMARKS, today()->subDay());

        return $application->fresh();
    }

    private function acceptedApplication(string $title): Application
    {
        $this->travelTo(today()->subDays(3)->setTime(9, 0));
        $application = $this->completeApplication($title);
        $this->service()->decide($application, DecisionStage::PreJtict, Decision::Accepted, null, today(), $this->urusSetia);
        $this->travelBack();

        return $application->fresh();
    }

    private function decideJtict(Application $application, Decision $decision, string $remarks, $date = null): void
    {
        $this->service()->decide($application->fresh(), DecisionStage::Jtict, $decision, $remarks, $date ?? today(), $this->urusSetia);
    }

    private function completeApplication(string $title): Application
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => $title,
            'estimated_cost' => 10000,
            'status' => 'SUBMITTED',
            'jtict_meeting_id' => $this->meeting->id,
        ]);
        $application->officers()->create(ApplicationOfficer::snapshotFrom($this->officer) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);

        return $application->fresh();
    }

    private function service(): ApplicationDecisionService
    {
        return app(ApplicationDecisionService::class);
    }
}
