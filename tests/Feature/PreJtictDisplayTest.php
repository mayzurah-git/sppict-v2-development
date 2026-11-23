<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Livewire\ApplicationForm;
use App\Livewire\ApplicationList;
use App\Livewire\PreJtictList;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationOfficer;
use App\Models\ApplicationReview;
use App\Models\Meeting;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP4.2A: tanda "Dihantar semula" dalam senarai semakan PP2 (termasuk selepas Pindaan Pra JTICT)
 * dan label status terperinci mengikut rekod keputusan (application_decisions).
 *
 * Nota: dijalankan pada SQLite in-memory; tidak membuktikan tingkah laku MySQL.
 */
class PreJtictDisplayTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private const REMARKS = 'Sila perincikan justifikasi kos dan skop fasa kedua projek.';

    private const PP2_FLAG = 'Dihantar semula selepas pindaan';

    private const PRE_JTICT_FLAG = 'Dihantar semula selepas Pindaan Pra JTICT';

    private Agency $agency;

    private User $urusSetia;

    private User $applicant;

    private User $officer;

    private Meeting $meeting;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
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

        $this->meeting = $this->openJtictMeeting(['series' => 'Bil. 1/2026']);
    }

    // --- B. TANDA PENGHANTARAN SEMULA (SENARAI SEMAKAN PP2) ---

    public function test_resubmission_after_pp2_incomplete_is_flagged(): void
    {
        $application = $this->makeApplication('SUBMITTED', 'Projek Tidak Lengkap PP2');
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);
        $this->resubmit($application);

        $this->assertSame('SUBMITTED', $application->fresh()->status);
        $this->reviewList()
            ->assertSee('Projek Tidak Lengkap PP2')
            ->assertSee(self::PP2_FLAG)
            ->assertDontSee(self::PRE_JTICT_FLAG);
    }

    public function test_resubmission_after_pre_jtict_amendment_is_flagged(): void
    {
        $application = $this->completeApplication('Projek Pindaan Pra JTICT');
        $this->decide($application, Decision::AmendmentRequired, self::REMARKS);
        $this->resubmit($application);

        $application->refresh();
        $this->assertSame('SUBMITTED', $application->status);
        // Tiada rekod Tidak Lengkap: logik lama (to_status NEEDS_AMENDMENT sahaja) tidak menandakannya
        $this->assertFalse($application->reviews()->where('to_status', 'NEEDS_AMENDMENT')->exists());

        $this->reviewList()
            ->assertSee('Projek Pindaan Pra JTICT')
            ->assertSee(self::PRE_JTICT_FLAG);
    }

    public function test_latest_resubmission_determines_flag_text(): void
    {
        $application = $this->completeApplication('Projek Dua Kitaran');
        $this->decide($application, Decision::AmendmentRequired, self::REMARKS);
        $this->resubmit($application);

        // Kitaran kedua: PP2 Tidak Lengkap, kemudian dihantar semula
        app(ApplicationReviewService::class)->review($application->fresh(), ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);
        $this->resubmit($application);

        $this->reviewList()
            ->assertSee('Projek Dua Kitaran')
            ->assertSee(self::PP2_FLAG)
            ->assertDontSee(self::PRE_JTICT_FLAG);
    }

    public function test_applications_not_yet_resubmitted_are_not_flagged(): void
    {
        // Penghantaran pertama
        $this->makeApplication('SUBMITTED', 'Projek Baharu Dihantar');

        // Tidak Lengkap / Pindaan Pra JTICT yang belum dihantar semula
        $incomplete = $this->makeApplication('SUBMITTED', 'Projek Menunggu Pindaan PP2');
        app(ApplicationReviewService::class)->review($incomplete, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);
        $amendment = $this->completeApplication('Projek Menunggu Pindaan Pra JTICT');
        $this->decide($amendment, Decision::AmendmentRequired, self::REMARKS);

        $reviews = ApplicationReview::count();

        $this->reviewList()
            ->assertSee('Projek Baharu Dihantar')
            ->assertDontSee(self::PP2_FLAG)
            ->assertDontSee(self::PRE_JTICT_FLAG);

        foreach (['NEEDS_AMENDMENT' => 'Projek Menunggu Pindaan PP2', 'PRE_JTICT_AMENDMENT' => 'Projek Menunggu Pindaan Pra JTICT'] as $status => $title) {
            $this->reviewList($status)
                ->assertSee($title)
                ->assertDontSee(self::PP2_FLAG)
                ->assertDontSee(self::PRE_JTICT_FLAG);
        }

        $this->assertSame($reviews, ApplicationReview::count(), 'Paparan tidak menambah rekod sejarah');
    }

    // --- C. LABEL STATUS MENGIKUT REKOD KEPUTUSAN ---

    public function test_complete_label_distinguishes_awaiting_decision_from_plain_complete(): void
    {
        $awaiting = $this->completeApplication('Projek Menunggu Keputusan');
        $legacy = $this->completeApplication('Projek Lama Tanpa Mesyuarat');
        $legacy->forceFill(['jtict_meeting_id' => null])->save();

        // Lengkap semula selepas Pindaan Pra JTICT: kembali menunggu keputusan
        $cycled = $this->completeApplication('Projek Lengkap Semula');
        $this->decide($cycled, Decision::AmendmentRequired, self::REMARKS);
        $this->resubmit($cycled);
        app(ApplicationReviewService::class)->review($cycled->fresh(), ApplicationStatus::Complete, null, $this->urusSetia);

        $this->assertSame('Lengkap – Menunggu Keputusan Pra JTICT', $awaiting->fresh()->statusDisplayLabel());
        $this->assertSame('Lengkap – Menunggu Keputusan Pra JTICT', $cycled->fresh()->statusDisplayLabel());
        $this->assertSame($legacy->statusLabel(), $legacy->fresh()->statusDisplayLabel(), 'Perlu Semakan Manual: label asas');

        // Senarai Urus Setia (penapis Lengkap) & senarai Pra JTICT memaparkan perbezaan yang sama
        $html = $this->reviewList('COMPLETE')->html();
        $this->assertSame(2, substr_count($html, 'Lengkap – Menunggu Keputusan Pra JTICT'));

        Livewire::actingAs($this->urusSetia)->test(PreJtictList::class)
            ->assertSee('Projek Lama Tanpa Mesyuarat')
            ->assertSee('Perlu Semakan Manual');
    }

    public function test_in_jtict_and_pre_jtict_amendment_labels_follow_decision_records(): void
    {
        $accepted = $this->completeApplication('Projek Diterima');
        $this->decide($accepted, Decision::Accepted);
        $amendment = $this->completeApplication('Projek Perlu Pindaan');
        $this->decide($amendment, Decision::AmendmentRequired, self::REMARKS);

        // IN_JTICT tanpa rekod keputusan (data lama): tiada label "Diterima" direka
        $legacy = $this->makeApplication('IN_JTICT', 'Projek JTICT Lama');

        $this->assertSame('Diterima – Menunggu Mesyuarat JTICT', $accepted->fresh()->statusDisplayLabel());
        $this->assertSame('Ditolak dengan Pindaan – Menunggu Tindakan Pemohon', $amendment->fresh()->statusDisplayLabel());
        $this->assertSame($legacy->statusLabel(), $legacy->fresh()->statusDisplayLabel());

        // Senarai & papan pemuka pegawai bertanggungjawab
        Livewire::actingAs($this->officer)->test(ApplicationList::class)
            ->assertSee('Diterima – Menunggu Mesyuarat JTICT')
            ->assertSee('Ditolak dengan Pindaan – Menunggu Tindakan Pemohon');

        $this->actingAs($this->officer)->get('/dashboard')
            ->assertOk()
            ->assertSee('Ditolak dengan Pindaan – Menunggu Tindakan Pemohon');

        // Urus Setia (penapis Dalam Proses JTICT)
        $this->reviewList('IN_JTICT')
            ->assertSee('Diterima – Menunggu Mesyuarat JTICT')
            ->assertSee('Projek JTICT Lama');
    }

    public function test_closed_without_decision_is_not_shown_as_pre_jtict_rejection(): void
    {
        $closed = $this->makeApplication('CLOSED', 'Projek Selesai Tanpa Keputusan');
        $rejected = $this->completeApplication('Projek Ditolak Pra JTICT');
        $this->decide($rejected, Decision::Rejected);

        $this->assertSame($closed->statusLabel(), $closed->fresh()->statusDisplayLabel());
        $this->assertSame('Selesai – Ditolak (Pra JTICT)', $rejected->fresh()->statusDisplayLabel());

        $html = Livewire::actingAs($this->officer)->test(ApplicationList::class)->html();
        $this->assertSame(1, substr_count($html, 'Selesai – Ditolak (Pra JTICT)'), 'Hanya permohonan dengan keputusan Ditolak');
        $this->assertStringNotContainsString('(JTICT)', $html, 'Keputusan JTICT tidak direka');
    }

    public function test_base_labels_for_filters_are_unchanged(): void
    {
        $this->assertSame('Lengkap', ApplicationStatus::Complete->label());
        $this->assertSame('Pindaan Pra JTICT', ApplicationStatus::PreJtictAmendment->label());

        // Penapis Urus Setia kekal menggunakan label asas enum
        $this->reviewList('COMPLETE')->assertSee('Lengkap')->assertSet('statusFilter', 'COMPLETE');
    }

    // --- PEMBANTU ---

    private function reviewList(string $status = 'SUBMITTED')
    {
        return Livewire::actingAs($this->urusSetia)->test(ApplicationList::class)->set('statusFilter', $status);
    }

    private function decide(Application $application, Decision $decision, ?string $remarks = null): void
    {
        app(ApplicationDecisionService::class)->decide($application->fresh(), DecisionStage::PreJtict, $decision, $remarks, today(), $this->urusSetia);
    }

    /**
     * Penghantaran semula melalui borang permohonan sebenar oleh Pegawai Utama aktif (aliran PP2).
     */
    private function resubmit(Application $application): void
    {
        $component = Livewire::actingAs($this->officer)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component, $application->title);
        $component->call('submitApplication')->assertHasNoErrors();
    }

    private function makeApplication(string $status, string $title): Application
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => $title,
            'estimated_cost' => 10000,
            'status' => $status,
            'jtict_meeting_id' => $this->meeting->id,
        ]);

        $application->officers()->create(ApplicationOfficer::snapshotFrom($this->officer) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        return $application;
    }

    private function completeApplication(string $title): Application
    {
        $application = $this->makeApplication('SUBMITTED', $title);
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);

        return $application->fresh();
    }

    private function fillValidForm($component, string $title): void
    {
        $component
            ->set('form.title', $title)
            ->set('form.application_category', 'pembangunan_sistem')
            ->set('form.objectives', 'Objektif projek ujian yang lengkap.')
            ->set('form.project_scope', 'Skop projek ujian yang lengkap.')
            ->set('form.procurement_type_id', $this->masterId(ProcurementType::class, 'pembekalan_perkhidmatan_one_off'))
            ->set('form.procurement_method_id', $this->masterId(ProcurementMethod::class, 'sebut_harga'))
            ->set('form.ceiling_cost', 20000)
            ->set('form.estimated_cost', 10000)
            ->set('form.expected_duration_months', 12)
            ->set('form.outcome_code', 'Bajet Mengurus')
            ->set('form.details', [[
                'project_category_id' => $this->masterId(ProjectCategory::class, 'projek_baharu'),
                'items' => [['technical_specifications' => 'Pelayan aplikasi', 'unit_quantity' => 1, 'unit_cost' => 10000]],
            ]])
            ->set('form.proposal_paper', UploadedFile::fake()->createWithContent('kertas.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"));
    }
}
