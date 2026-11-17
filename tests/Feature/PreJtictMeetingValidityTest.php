<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Livewire\PreJtictList;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

/**
 * PP4.3A (PP42B-01): keputusan Pra JTICT "Diterima" hanya bagi Mesyuarat JTICT yang wujud, tidak dipadam,
 * jenis JTICT, Aktif dan tarikh mesyuarat hari ini atau akan datang. Ditolak dengan Pindaan / Ditolak
 * tidak terikat syarat mesyuarat.
 *
 * Nota: dijalankan pada SQLite in-memory; tidak membuktikan tingkah laku MySQL.
 */
class PreJtictMeetingValidityTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting;

    private const REMARKS = 'Sila hantar semula permohonan ke Mesyuarat JTICT yang dibuka.';

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
        $this->applicant = User::factory()->create(['agency_id' => $this->agency->id]);
        $this->applicant->assignRole('pengguna_biasa');
    }

    // --- 1 & 2: DITERIMA DIBENARKAN ---

    public function test_accepted_is_allowed_for_active_upcoming_meeting(): void
    {
        $meeting = $this->openJtictMeeting(['meeting_date' => today()->addDays(10)]);
        $application = $this->completeApplication($meeting);

        $this->assertTrue($meeting->isValidForPreJtictAcceptance());
        $this->assertNull($application->preJtictAcceptanceBlocker());

        $record = $this->decide($application, Decision::Accepted);

        $this->assertSame(ApplicationStatus::InJtict->value, $application->fresh()->status);
        $this->assertSame($meeting->id, (int) $record->meeting_id);
    }

    public function test_accepted_is_allowed_on_the_meeting_day(): void
    {
        $meeting = $this->openJtictMeeting(['meeting_date' => today()]);
        $application = $this->completeApplication($meeting);

        $this->assertTrue($meeting->isValidForPreJtictAcceptance());
        $this->decide($application, Decision::Accepted);

        $this->assertSame(ApplicationStatus::InJtict->value, $application->fresh()->status);
    }

    // --- 3 - 6: DITERIMA DITOLAK ---

    public function test_accepted_is_refused_when_meeting_is_inactive(): void
    {
        $meeting = $this->openJtictMeeting();
        $application = $this->completeApplication($meeting);
        $meeting->update(['is_active' => false]);

        $this->assertAcceptanceRefused($application, 'tidak aktif');
    }

    public function test_accepted_is_refused_when_meeting_has_passed(): void
    {
        $meeting = $this->openJtictMeeting(['meeting_date' => today()->subDay()]);
        $application = $this->completeApplication($meeting);

        $this->assertFalse($meeting->isValidForPreJtictAcceptance());
        $this->assertAcceptanceRefused($application, 'telah berlangsung');
    }

    public function test_accepted_is_refused_when_meeting_is_not_jtict(): void
    {
        $meeting = $this->openJtictMeeting(['code' => 'JPICTNS', 'series' => 'Bil. 4/2026']);
        $application = $this->completeApplication($meeting);

        $this->assertFalse($meeting->isJtict());
        $this->assertAcceptanceRefused($application, 'bukan Mesyuarat JTICT');
    }

    public function test_accepted_is_refused_when_meeting_is_deleted(): void
    {
        $meeting = $this->openJtictMeeting();
        $application = $this->completeApplication($meeting);
        $meeting->delete();

        $this->assertAcceptanceRefused($application, 'telah dipadam');
    }

    // --- 7 & 8: PINDAAN / DITOLAK TIDAK TERIKAT SYARAT MESYUARAT ---

    public function test_amendment_can_still_be_recorded_when_meeting_is_invalid(): void
    {
        $meeting = $this->openJtictMeeting(['meeting_date' => today()->subDay(), 'is_active' => false]);
        $application = $this->completeApplication($meeting);

        $this->decide($application, Decision::AmendmentRequired, self::REMARKS);

        $application->refresh();
        $this->assertSame(ApplicationStatus::PreJtictAmendment->value, $application->status);
        $this->assertSame($meeting->id, $application->jtict_meeting_id, 'Mesyuarat tidak diubah; pemohon memilih mesyuarat yang dibuka semasa hantar semula (PP2)');
        $this->assertTrue($application->requiresMeetingSelection());
    }

    public function test_rejection_can_still_be_recorded_when_meeting_is_invalid(): void
    {
        $meeting = $this->openJtictMeeting(['meeting_date' => today()->subDay()]);
        $application = $this->completeApplication($meeting);

        $this->decide($application, Decision::Rejected);

        $this->assertSame(ApplicationStatus::Closed->value, $application->fresh()->status);
    }

    // --- PAPARAN: BUTANG DITERIMA DINYAHDAYAKAN + SEBAB ---

    public function test_accept_button_is_disabled_with_reason_and_payload_is_refused(): void
    {
        $meeting = $this->openJtictMeeting(['meeting_date' => today()->subDays(3)]);
        $application = $this->completeApplication($meeting);

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertSee('Keputusan Diterima tidak dibenarkan')
            ->assertSee('telah berlangsung pada ' . today()->subDays(3)->format('d/m/Y'))
            ->assertSeeHtml('wire:click="openPreJtictModal(\'ACCEPTED\')" disabled')
            // Payload terus (memintas UI): disekat oleh service, ralat dipaparkan pada medan keputusan
            ->set('showPreJtictModal', true)
            ->set('preJtictDecision', Decision::Accepted->value)
            ->set('preJtictDate', today()->toDateString())
            ->call('submitPreJtictDecision')
            ->assertHasErrors(['preJtictDecision']);

        $this->assertSame(0, ApplicationDecision::count());
        $this->assertSame('COMPLETE', $application->fresh()->status);

        // Senarai Pra JTICT menanda mesyuarat yang tidak sah untuk Diterima
        Livewire::actingAs($this->urusSetia)->test(PreJtictList::class)
            ->assertSee($application->reference_number)
            ->assertSee('Mesyuarat tidak sah untuk Diterima');
    }

    public function test_valid_meeting_shows_enabled_accept_button(): void
    {
        $application = $this->completeApplication($this->openJtictMeeting());

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertDontSee('Keputusan Diterima tidak dibenarkan')
            ->assertDontSeeHtml('wire:click="openPreJtictModal(\'ACCEPTED\')" disabled');
    }

    // --- PEMBANTU ---

    private function assertAcceptanceRefused(Application $application, string $reason): void
    {
        $this->assertNotNull($application->fresh()->preJtictAcceptanceBlocker());

        try {
            $this->decide($application, Decision::Accepted);
            $this->fail('Pra JTICT Diterima sepatutnya disekat');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('meetingId', $e->errors());
            $this->assertStringContainsString($reason, $e->errors()['meetingId'][0]);
        }

        $this->assertSame(0, ApplicationDecision::count());
        $this->assertSame(ApplicationStatus::Complete->value, $application->fresh()->status);
    }

    private function decide(Application $application, Decision $decision, ?string $remarks = null): ApplicationDecision
    {
        return app(ApplicationDecisionService::class)->decide($application->fresh(), DecisionStage::PreJtict, $decision, $remarks, today(), $this->urusSetia);
    }

    private function completeApplication(Meeting $meeting): Application
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => "Projek Mesyuarat {$this->sequence}",
            'estimated_cost' => 10000,
            'status' => 'SUBMITTED',
            'jtict_meeting_id' => $meeting->id,
        ]);
        $application->officers()->create(ApplicationOfficer::snapshotFrom($this->applicant) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);

        return $application->fresh();
    }
}
