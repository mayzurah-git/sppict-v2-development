<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Enums\UserStatus;
use App\Livewire\JtictList;
use App\Livewire\ViewApplication;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\MeetingName;
use App\Models\User;
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

/**
 * PP4.3A: keputusan Mesyuarat JTICT (Lulus / Tidak Lulus / Kaji Semula) melalui ApplicationDecisionService,
 * senarai JTICT tiga bahagian, UI keputusan dalam ViewApplication dan kawalan akses.
 *
 * Nota: dijalankan pada SQLite in-memory; tidak membuktikan tingkah laku MySQL (cth. kunci baris).
 */
class JtictDecisionTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting;

    private const REMARKS = 'Mesyuarat JTICT bersetuju dengan cadangan projek ini.';

    private Agency $agency;

    private User $urusSetia;

    private User $applicant;

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
    }

    // --- 9, 32, 33: LULUS ---

    public function test_approved_decision_is_recorded_after_meeting_through_ui(): void
    {
        $application = $this->inJtictApplication('Projek Lulus');
        $preJtictBefore = $application->decisions()->where('stage', 'PRE_JTICT')->orderBy('id')->get()->toArray();

        $this->assertTrue($this->urusSetia->can('decideJtict', $application));

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertSee('Keputusan Mesyuarat JTICT')
            ->assertSee($application->jtictMeeting->label())
            ->assertSee('Rekod keputusan hanya selepas Mesyuarat JTICT benar-benar bersidang')
            ->call('openJtictModal', Decision::Approved->value)
            ->assertSet('showJtictModal', true)
            ->assertSet('jtictDate', '') // tiada tarikh lalai
            ->assertSeeHtml('min="' . $application->jtictMeeting->meeting_date->toDateString() . '"')
            ->assertSeeHtml('max="' . today()->toDateString() . '"')
            ->set('jtictDate', today()->toDateString())
            ->set('jtictRemarks', self::REMARKS)
            ->call('submitJtictDecision')
            ->assertHasNoErrors()
            ->assertSet('showJtictModal', false)
            ->assertSee('Keputusan Mesyuarat JTICT direkodkan: Lulus');

        $application->refresh();
        $record = $application->decisions()->where('stage', 'JTICT')->sole();

        $this->assertSame(ApplicationStatus::Closed->value, $application->status);
        $this->assertSame(DecisionStage::Jtict, $record->stage);
        $this->assertSame(Decision::Approved, $record->decision);
        $this->assertSame(['IN_JTICT', 'CLOSED'], [$record->from_status, $record->to_status]);
        $this->assertSame(self::REMARKS, $record->remarks);
        $this->assertSame(today()->toDateString(), $record->decision_date->toDateString());
        $this->assertSame($application->jtict_meeting_id, (int) $record->meeting_id);
        $this->assertSame($this->urusSetia->id, (int) $record->decided_by);

        // 32: label CLOSED mengikut keputusan JTICT sebenar
        $this->assertSame('Selesai – Lulus (JTICT)', $application->statusDisplayLabel());

        // 33: sejarah Pra JTICT tidak berubah; medan legacy tidak disentuh
        $this->assertSame($preJtictBefore, $application->decisions()->where('stage', 'PRE_JTICT')->orderBy('id')->get()->toArray());
        $this->assertSame('PENDING', $application->jtict_decision);
        $this->assertNull($application->jtict_remarks);
    }

    // --- 10, 11: TIDAK LULUS & KAJI SEMULA ---

    public function test_not_approved_and_review_decisions_are_recorded(): void
    {
        foreach ([[Decision::NotApproved, 'Selesai – Tidak Lulus (JTICT)'], [Decision::Review, 'Selesai – Kaji Semula (JTICT)']] as [$decision, $label]) {
            $application = $this->inJtictApplication("Projek {$decision->value}");

            $record = $this->decide($application, $decision);

            $this->assertSame($decision, $record->decision);
            $this->assertSame(ApplicationStatus::Closed->value, $application->fresh()->status);
            $this->assertSame($label, $application->fresh()->statusDisplayLabel());
        }

        $this->assertSame(0, Application::whereNotNull('copied_from_application_id')->count(), 'Tiada salinan dicipta (PP4.3C)');
        $this->assertSame(0, DB::table('projects')->count(), 'Tiada rekod projek dicipta');
    }

    // --- 12, 13: TARIKH ---

    public function test_decision_cannot_be_recorded_before_meeting_or_before_meeting_date(): void
    {
        // Mesyuarat belum berlangsung: tiada butang, polisi & service menolak dengan sebab yang jelas
        $scheduled = $this->inJtictApplication('Projek Belum Bersidang', today()->addDays(5));
        $this->assertFalse($this->urusSetia->can('decideJtict', $scheduled));
        $this->assertDecisionInvalid($scheduled, 'meetingId', 'belum berlangsung');

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $scheduled->uuid])
            ->assertSee('Keputusan Mesyuarat JTICT belum boleh direkodkan')
            ->assertDontSeeHtml('openJtictModal')
            ->call('openJtictModal', Decision::Approved->value)
            ->assertForbidden();

        // Mesyuarat telah berlangsung: tarikh keputusan tidak boleh sebelum tarikh mesyuarat
        $held = $this->inJtictApplication('Projek Telah Bersidang', today()->subDays(3));
        $this->assertDecisionInvalid($held, 'decisionDate', 'tarikh mesyuarat', today()->subDays(4));

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $held->uuid])
            ->call('openJtictModal', Decision::Approved->value)
            ->set('jtictDate', today()->subDays(4)->toDateString())
            ->set('jtictRemarks', self::REMARKS)
            ->call('submitJtictDecision')
            ->assertHasErrors(['jtictDate']);

        $this->assertSame(0, ApplicationDecision::where('stage', 'JTICT')->count());
    }

    public function test_decision_date_cannot_be_in_the_future(): void
    {
        $application = $this->inJtictApplication('Projek Tarikh Hadapan');

        $this->assertDecisionInvalid($application, 'decisionDate', 'melebihi tarikh semasa', today()->addDay());
    }

    // --- 14, 15, 16: MESYUARAT ---

    public function test_meeting_earlier_than_pre_jtict_acceptance_is_refused_and_needs_manual_review(): void
    {
        // Kes seperti permohonan ID 6: mesyuarat lebih awal daripada tarikh Pra JTICT Diterima
        $application = $this->inJtictApplication('Projek Mesyuarat Terdahulu', today()->addDays(5));
        $application->jtictMeeting->update(['meeting_date' => today()->subDays(10), 'is_active' => false]);
        $meetingId = $application->jtict_meeting_id;
        $preJtictBefore = $application->decisions()->orderBy('id')->get()->toArray();

        $this->assertFalse($this->urusSetia->can('decideJtict', $application->fresh()));
        $this->assertDecisionInvalid($application, 'meetingId', 'lebih awal daripada tarikh keputusan Pra JTICT Diterima');

        $this->assertSame([$application->id], Application::needsManualJtictReview()->pluck('id')->all());
        $this->assertSame($meetingId, $application->fresh()->jtict_meeting_id, 'Mesyuarat tidak diubah secara automatik');
        $this->assertSame($preJtictBefore, $application->decisions()->orderBy('id')->get()->toArray(), 'Keputusan Pra JTICT tidak diubah');
        $this->assertSame(ApplicationStatus::InJtict->value, $application->fresh()->status);
    }

    public function test_non_jtict_meeting_is_refused(): void
    {
        $application = $this->inJtictApplication('Projek Bukan JTICT');
        $other = MeetingName::create(['code' => 'JPICTNS', 'name' => 'JPICTNS', 'accepts_applications' => false]);
        $application->jtictMeeting->update(['meeting_name_id' => $other->id]);

        $this->assertFalse($this->urusSetia->can('decideJtict', $application->fresh()));
        $this->assertDecisionInvalid($application, 'meetingId', 'bukan jenis JTICT');
        $this->assertSame([$application->id], Application::needsManualJtictReview()->pluck('id')->all());
    }

    public function test_inactive_meeting_does_not_block_decision_after_meeting(): void
    {
        $application = $this->inJtictApplication('Projek Mesyuarat Tidak Aktif');
        $application->jtictMeeting->update(['is_active' => false]);

        $this->assertTrue($this->urusSetia->can('decideJtict', $application->fresh()));
        $this->decide($application, Decision::Approved);

        $this->assertSame(ApplicationStatus::Closed->value, $application->fresh()->status);
    }

    // --- 17, 18: ULASAN ---

    public function test_remarks_are_required_between_10_and_5000_characters(): void
    {
        $application = $this->inJtictApplication('Projek Ulasan');

        foreach (['', '   ', 'Lulus.', str_repeat('a', ApplicationDecisionService::REMARKS_MAX + 1)] as $remarks) {
            try {
                $this->service()->decide($application->fresh(), DecisionStage::Jtict, Decision::Approved, $remarks, today(), $this->urusSetia);
                $this->fail('Ulasan tidak sah sepatutnya ditolak');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('remarks', $e->errors());
            }
        }

        $this->assertSame(0, ApplicationDecision::where('stage', 'JTICT')->count());

        // Tepat 10 dan 5,000 aksara diterima
        $this->assertSame(Decision::Approved, $this->decide($application, Decision::Approved, str_repeat('b', 10))->decision);
        $other = $this->inJtictApplication('Projek Ulasan Panjang');
        $this->assertSame(Decision::Review, $this->decide($other, Decision::Review, str_repeat('c', 5000))->decision);
    }

    // --- 19, 20: KEPUTUSAN KEDUA & PERMOHONAN DIPADAM ---

    public function test_second_jtict_decision_is_refused(): void
    {
        $application = $this->inJtictApplication('Projek Keputusan Kedua');
        $this->decide($application, Decision::Approved);

        // Selepas ditutup
        $this->assertRefused($application, Decision::Review);

        // Walaupun status dipulihkan secara tidak sah, keputusan JTICT sedia ada menghalang keputusan kedua
        $application->forceFill(['status' => ApplicationStatus::InJtict->value])->save();
        $this->assertFalse($this->urusSetia->can('decideJtict', $application->fresh()));
        $this->assertRefused($application, Decision::NotApproved);

        $this->assertSame(1, $application->decisions()->where('stage', 'JTICT')->count());
    }

    public function test_deleted_application_cannot_be_decided(): void
    {
        $application = $this->inJtictApplication('Projek Dipadam');
        $application->delete();

        $this->assertFalse($this->urusSetia->can('decideJtict', Application::withTrashed()->find($application->id)));

        try {
            $this->service()->decide($application, DecisionStage::Jtict, Decision::Approved, self::REMARKS, today(), $this->urusSetia);
            $this->fail('Permohonan dipadam tidak sepatutnya boleh diputuskan');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('telah dipadam', $e->getMessage());
        }

        $this->assertSame(0, ApplicationDecision::where('stage', 'JTICT')->count());
        $this->assertSame(ApplicationStatus::InJtict->value, Application::withTrashed()->find($application->id)->status);
    }

    // --- 21 - 27: KAWALAN AKSES ---

    public function test_only_active_urus_setia_can_view_list_and_record_decisions(): void
    {
        $application = $this->inJtictApplication('Projek Akses');

        $inactive = $this->user(['pentadbir_urus_setia'], ['status' => UserStatus::Inactive]);
        $denied = [
            'superadmin' => $this->user(['superadmin']),
            'pengurusan' => $this->user(['pengurusan']),
            'pengguna_biasa' => $this->user(['pengguna_biasa']),
            'urus_setia+superadmin' => $this->user(['pentadbir_urus_setia', 'superadmin']),
            'urus_setia+pengurusan' => $this->user(['pentadbir_urus_setia', 'pengurusan']),
        ];

        foreach ($denied + ['urus_setia_tidak_aktif' => $inactive] as $label => $user) {
            $this->assertFalse($user->can('viewJtictQueue', Application::class), $label);
            $this->assertFalse($user->can('decideJtict', $application), $label);
            $this->assertRefused($application, Decision::Approved, $user, $label);
        }

        foreach ($denied as $label => $user) {
            $this->actingAs($user)->get(route('jtict.index'))->assertForbidden();
        }
        // Akaun tidak aktif dilog keluar oleh middleware `active`
        $this->actingAs($inactive)->get(route('jtict.index'))->assertRedirect(route('login'));

        foreach (['urus_setia+superadmin', 'urus_setia+pengurusan'] as $label) {
            Livewire::actingAs($denied[$label])->test(JtictList::class)->assertForbidden();
        }

        // Urus Setia aktif: senarai dan rekod keputusan
        $this->actingAs($this->urusSetia)->get(route('jtict.index'))->assertOk()->assertSee('Keputusan Mesyuarat JTICT');
        $this->assertTrue($this->urusSetia->can('decideJtict', $application));
        $this->decide($application, Decision::Approved);
        $this->assertSame(ApplicationStatus::Closed->value, $application->fresh()->status);
    }

    public function test_jtict_decision_cannot_be_recorded_through_pre_jtict_modal_or_vice_versa(): void
    {
        $application = $this->inJtictApplication('Projek Silang Peringkat');

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openJtictModal', Decision::Accepted->value)
            ->assertStatus(422);

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->set('showJtictModal', true)
            ->set('jtictDecision', Decision::Rejected->value)
            ->set('jtictDate', today()->toDateString())
            ->set('jtictRemarks', self::REMARKS)
            ->call('submitJtictDecision')
            ->assertStatus(422);

        $this->assertSame(0, ApplicationDecision::where('stage', 'JTICT')->count());
    }

    // --- 28 - 31: SENARAI & PAPARAN ---

    public function test_list_separates_ready_scheduled_and_manual_review(): void
    {
        $ready = $this->inJtictApplication('Projek Sedia Diputuskan');
        $scheduled = $this->inJtictApplication('Projek Menunggu Mesyuarat', today()->addDays(7));
        $manual = $this->inJtictApplication('Projek Semakan Manual', today()->addDays(3));
        $manual->jtictMeeting->update(['meeting_date' => today()->subDays(20)]);

        // Tidak berkenaan: belum Pra JTICT Diterima / tiada mesyuarat (cth. ID 2 & 3) / telah diputuskan
        $this->makeApplication('SUBMITTED', 'Projek Tanpa Mesyuarat', ['jtict_meeting_id' => null]);
        $closed = $this->inJtictApplication('Projek Telah Diputuskan');
        $this->decide($closed, Decision::NotApproved);

        $this->assertSame([$ready->id], Application::awaitingJtictDecision()->pluck('id')->all());
        $this->assertSame([$scheduled->id], Application::scheduledForJtict()->pluck('id')->all());
        $this->assertSame([$manual->id], Application::needsManualJtictReview()->pluck('id')->all());

        $component = Livewire::actingAs($this->urusSetia)->test(JtictList::class)
            ->assertSeeInOrder(['Sedia Diputuskan (1)', 'Projek Sedia Diputuskan', 'Menunggu Mesyuarat (1)', 'Projek Menunggu Mesyuarat', 'Perlu Semakan Manual (1)', 'Projek Semakan Manual'])
            ->assertSee(today()->addDays(7)->format('d/m/Y'))
            ->assertSee('lebih awal daripada tarikh keputusan Pra JTICT Diterima')
            ->assertDontSee('Projek Tanpa Mesyuarat')
            ->assertDontSee('Projek Telah Diputuskan');

        // Hanya bahagian Sedia Diputuskan mempunyai tindakan rekod keputusan
        $this->assertSame(1, substr_count($component->html(), 'Semak &amp; Rekod Keputusan'));

        // Penapis mengikut Mesyuarat JTICT
        $component->set('meeting', (string) $scheduled->jtict_meeting_id)
            ->assertSee('Projek Menunggu Mesyuarat')
            ->assertDontSee('Projek Sedia Diputuskan')
            ->assertDontSee('Projek Semakan Manual');

        // 31: tiada tindakan keputusan bagi kes Perlu Semakan Manual
        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $manual->uuid])
            ->assertSee('Keputusan Mesyuarat JTICT belum boleh direkodkan')
            ->assertDontSeeHtml('openJtictModal')
            ->call('openJtictModal', Decision::Approved->value)
            ->assertForbidden();
    }

    public function test_menu_and_dashboard_link_to_jtict_list(): void
    {
        $this->actingAs($this->urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertSee('href="' . e(route('jtict.index')) . '"', false);
    }

    // --- PEMBANTU ---

    private function service(): ApplicationDecisionService
    {
        return app(ApplicationDecisionService::class);
    }

    private function decide(Application $application, Decision $decision, string $remarks = self::REMARKS, $date = null): ApplicationDecision
    {
        return $this->service()->decide($application->fresh(), DecisionStage::Jtict, $decision, $remarks, $date ?? today(), $this->urusSetia);
    }

    private function assertDecisionInvalid(Application $application, string $key, string $message, $date = null): void
    {
        try {
            $this->decide($application, Decision::Approved, self::REMARKS, $date);
            $this->fail("Keputusan JTICT sepatutnya ditolak ({$key})");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            $this->assertStringContainsString($message, $e->errors()[$key][0]);
        }

        $this->assertSame(ApplicationStatus::InJtict->value, $application->fresh()->status);
    }

    private function assertRefused(Application $application, Decision $decision, ?User $user = null, string $label = ''): void
    {
        $before = ApplicationDecision::count();

        try {
            $this->service()->decide($application->fresh(), DecisionStage::Jtict, $decision, self::REMARKS, today(), $user ?? $this->urusSetia);
            $this->fail("Keputusan JTICT sepatutnya ditolak {$label}");
        } catch (AuthorizationException) {
        }

        $this->assertSame($before, ApplicationDecision::count(), $label);
    }

    private function user(array $roles, array $attributes = []): User
    {
        $user = User::factory()->create(['agency_id' => $this->agency->id] + $attributes);
        $user->assignRole($roles);

        return $user;
    }

    private function makeApplication(string $status, string $title, array $attributes = []): Application
    {
        $this->sequence++;

        $application = Application::create($attributes + [
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => $title,
            'estimated_cost' => 10000,
            'status' => $status,
        ]);
        $application->officers()->create(ApplicationOfficer::snapshotFrom($this->applicant) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        return $application;
    }

    /**
     * Permohonan Dalam Proses JTICT melalui aliran sebenar: PP2 Lengkap dan Pra JTICT Diterima direkodkan
     * semasa mesyuarat masih sah (pada tarikh mesyuarat jika telah berlalu, atau hari ini jika akan datang).
     */
    private function inJtictApplication(string $title, ?Carbon $meetingDate = null): Application
    {
        $meetingDate ??= today()->subDay();
        $meeting = $this->openJtictMeeting(['series' => 'Bil. ' . ($this->sequence + 1) . '/2026', 'meeting_date' => $meetingDate]);

        $this->travelTo(($meetingDate->isFuture() ? today() : $meetingDate)->copy()->setTime(9, 0));
        $application = $this->makeApplication('SUBMITTED', $title, ['jtict_meeting_id' => $meeting->id]);
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);
        $this->service()->decide($application->fresh(), DecisionStage::PreJtict, Decision::Accepted, null, today(), $this->urusSetia);
        $this->travelBack();

        return $application->fresh();
    }
}
