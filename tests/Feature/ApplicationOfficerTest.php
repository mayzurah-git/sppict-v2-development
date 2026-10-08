<?php

namespace Tests\Feature;

use App\Enums\OfficerEndReason;
use App\Enums\OfficerRole;
use App\Livewire\Admin\UserManagement;
use App\Livewire\ApplicationForm;
use App\Livewire\ApplicationList;
use App\Livewire\ViewApplication;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ProjectCategory;
use App\Models\ApplicationOfficer;
use App\Models\User;
use App\Notifications\ResponsibleOfficerChanged;
use App\Services\OfficerHandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

class ApplicationOfficerTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private Agency $agency;
    private User $applicant;
    private User $colleague;
    private User $urusSetia;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->openJtictMeeting();
        Notification::fake();

        Role::create(['name' => 'pentadbir_urus_setia']);
        Role::create(['name' => 'superadmin']);

        $this->agency = $this->makeAgency('JKR');
        $this->applicant = User::factory()->create(['agency_id' => $this->agency->id, 'position' => 'Jurutera J44', 'phone_number' => '06-111']);
        $this->colleague = User::factory()->create(['agency_id' => $this->agency->id, 'position' => 'Penolong Jurutera JA29']);

        $this->urusSetia = User::factory()->create(['agency_id' => $this->makeAgency('BTM')->id]);
        $this->urusSetia->assignRole('pentadbir_urus_setia');
    }

    // --- FASA 5: BORANG PERMOHONAN ---

    public function test_applicant_is_default_primary_officer_and_snapshot_is_saved_on_submit(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->assertSet('form.primary_officer_id', $this->applicant->id);

        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors();

        $officer = Application::sole()->primaryOfficer;

        $this->assertSame($this->applicant->id, $officer->user_id);
        $this->assertSame('Jurutera J44', $officer->position);
        $this->assertSame('06-111', $officer->phone);
        $this->assertTrue($officer->started_at->isToday());
    }

    public function test_phase_five_lists_only_same_agency_users(): void
    {
        $outsider = User::factory()->create(['agency_id' => $this->makeAgency('JPN')->id]);

        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('currentStep', 5)
            ->assertSee($this->colleague->email)
            ->assertSee('Jurutera J44')
            ->assertDontSee($outsider->email);
    }

    public function test_officer_must_be_registered_user_in_same_agency(): void
    {
        $outsider = User::factory()->create(['agency_id' => $this->makeAgency('JPN')->id]);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $this->fillValidForm($component);

        $component->set('form.primary_officer_id', $outsider->id)
            ->call('submitApplication')
            ->assertHasErrors('form.primary_officer_id');

        $component->set('form.primary_officer_id', $this->applicant->id)
            ->set('form.has_secondary_officer', true)
            ->set('form.secondary_officer_id', $this->applicant->id)
            ->call('submitApplication')
            ->assertHasErrors('form.secondary_officer_id');

        $this->assertSame(0, Application::where('status', 'SUBMITTED')->count());
    }

    public function test_changing_officer_while_draft_updates_in_place_without_history(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $this->fillValidForm($component);
        $component->call('saveDraft');

        $component->set('form.primary_officer_id', $this->colleague->id)->call('saveDraft');

        $application = Application::sole();
        $this->assertSame(1, $application->officers()->withTrashed()->count());
        $this->assertSame($this->colleague->id, $application->primaryOfficer->user_id);
        $this->assertNull($application->primaryOfficer->started_at);

        // Buka semula draf: pilihan pegawai dikekalkan
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->assertSet('form.primary_officer_id', $this->colleague->id);
    }

    // --- PERTUKARAN PEGAWAI SELEPAS DIHANTAR ---

    public function test_handover_keeps_history_and_notifies_urus_setia(): void
    {
        $application = $this->submittedApplication();

        Livewire::actingAs($this->applicant)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openHandoverModal', OfficerRole::Primary->value)
            ->set('newOfficerId', $this->colleague->id)
            ->set('endReason', OfficerEndReason::TransferOut->value)
            ->set('handoverRemarks', 'Fail projek telah diserahkan.')
            ->call('reassignOfficer')
            ->assertHasNoErrors()
            ->assertSee('Sejarah Pegawai Bertanggungjawab')
            ->assertSee('Bertukar Keluar Jabatan/Agensi')
            ->assertSee('Fail projek telah diserahkan.');

        $history = $application->officers()->orderBy('id')->get();
        $this->assertCount(2, $history);

        [$old, $new] = $history;
        $this->assertSame($this->applicant->id, $old->user_id);
        $this->assertTrue($old->ended_at->isToday());
        $this->assertSame(OfficerEndReason::TransferOut, $old->end_reason);
        $this->assertSame('Fail projek telah diserahkan.', $old->handover_remarks);
        $this->assertSame($this->applicant->id, $old->ended_by);

        $this->assertTrue($new->isActive());
        $this->assertSame($this->colleague->id, $application->fresh()->primaryOfficer->user_id);

        Notification::assertSentTo([$this->urusSetia, $this->colleague], ResponsibleOfficerChanged::class);
        Notification::assertNotSentTo($this->applicant, ResponsibleOfficerChanged::class); // pelaku sendiri
    }

    public function test_history_snapshot_survives_officer_profile_change(): void
    {
        $application = $this->submittedApplication();
        $this->handover($application, $this->colleague);

        // Pegawai lama bertukar agensi & jawatan
        $this->applicant->update(['agency_id' => $this->makeAgency('JPN')->id, 'position' => 'Jawatan Baharu']);

        $old = $application->officers()->whereNotNull('ended_at')->sole();
        $this->assertSame('Jurutera J44', $old->position);
        $this->assertSame($this->agency->id, $old->agency_id);
    }

    public function test_promoting_secondary_to_primary_ends_secondary_assignment(): void
    {
        $application = $this->submittedApplication(secondary: $this->colleague);

        $this->handover($application, $this->colleague);

        $application->refresh();
        $this->assertSame($this->colleague->id, $application->primaryOfficer->user_id);
        $this->assertNull($application->secondaryOfficer);
        $this->assertSame(1, $application->officers()->active()->count());
        $this->assertSame(3, $application->officers()->count());
    }

    public function test_secondary_officer_can_be_ended_without_replacement(): void
    {
        $application = $this->submittedApplication(secondary: $this->colleague);

        app(OfficerHandoverService::class)->reassign(
            $application, OfficerRole::Secondary, null, today(), OfficerEndReason::Retirement, null, $this->urusSetia,
        );

        $this->assertNull($application->fresh()->secondaryOfficer);
        $this->assertSame(OfficerEndReason::Retirement, $application->officers()->role(OfficerRole::Secondary)->sole()->end_reason);
    }

    public function test_handover_rejects_invalid_requests(): void
    {
        $application = $this->submittedApplication();
        $outsider = User::factory()->create(['agency_id' => $this->makeAgency('JPN')->id]);
        $service = app(OfficerHandoverService::class);

        foreach ([
            'agensi lain' => [$outsider, today()],
            'pegawai sama' => [$this->applicant, today()],
            'tarikh masa depan' => [$this->colleague, today()->addDay()],
            'sebelum tarikh mula' => [$this->colleague, today()->subYear()],
            'utama tanpa pengganti' => [null, today()],
        ] as $case => [$newOfficer, $date]) {
            try {
                $service->reassign($application, OfficerRole::Primary, $newOfficer, $date, OfficerEndReason::Other, null, $this->urusSetia);
                $this->fail("Pertukaran sepatutnya ditolak: {$case}");
            } catch (ValidationException) {
                // dijangka
            }
        }

        $this->assertSame(1, $application->officers()->count());

        // Permohonan Draf: pertukaran melalui borang sahaja
        $application->update(['status' => 'DRAFT']);
        $this->expectException(ValidationException::class);
        $service->reassign($application, OfficerRole::Primary, $this->colleague, today(), OfficerEndReason::Other, null, $this->urusSetia);
    }

    // --- KEBENARAN ---

    public function test_who_can_manage_officers_and_update_progress(): void
    {
        $application = $this->submittedApplication();
        $otherAgency = User::factory()->create(['agency_id' => $this->makeAgency('JPN')->id]);

        $this->assertTrue($this->applicant->can('manageOfficers', $application));
        $this->assertTrue($this->urusSetia->can('manageOfficers', $application));
        $this->assertFalse($this->colleague->can('manageOfficers', $application)); // agensi sama, tetapi bukan pemohon/pegawai
        $this->assertFalse($otherAgency->can('manageOfficers', $application));

        $this->assertTrue($this->applicant->can('updateProgress', $application));
        $this->assertFalse($this->colleague->can('updateProgress', $application));

        // Selepas pertukaran: pegawai baharu ambil alih, pegawai lama hilang akses tulis
        $this->handover($application, $this->colleague);
        $this->assertTrue($this->colleague->can('updateProgress', $application));
        $this->assertFalse($this->applicant->fresh()->can('updateProgress', $application));

        // Pegawai aktif yang bertukar agensi hilang akses serta-merta
        $this->colleague->update(['agency_id' => $otherAgency->agency_id]);
        $this->assertFalse($this->colleague->fresh()->can('updateProgress', $application));
        $this->assertFalse($this->colleague->fresh()->can('manageOfficers', $application));
    }

    public function test_handover_modal_is_forbidden_without_permission(): void
    {
        $application = $this->submittedApplication();

        Livewire::actingAs($this->colleague)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openHandoverModal', OfficerRole::Primary->value)
            ->assertForbidden();
    }

    public function test_new_officer_sees_taken_over_application_in_list(): void
    {
        $application = $this->submittedApplication();
        $this->handover($application, $this->colleague);

        Livewire::actingAs($this->colleague)->test(ApplicationList::class)
            ->assertSee($application->reference_number);
    }

    // --- PENGESANAN PEGAWAI BERTUKAR ---

    public function test_application_flagged_when_primary_officer_leaves_agency(): void
    {
        $application = $this->submittedApplication();
        $this->assertFalse(Application::needsOfficerReassignment()->exists());

        $this->applicant->update(['agency_id' => $this->makeAgency('JPN')->id]);
        $this->assertTrue(Application::needsOfficerReassignment()->whereKey($application->id)->exists());

        $this->handover($application, $this->colleague);
        $this->assertFalse(Application::needsOfficerReassignment()->exists());
    }

    public function test_superadmin_is_warned_when_moving_an_active_officer_to_another_agency(): void
    {
        $application = $this->submittedApplication();
        $superadmin = User::factory()->create(['agency_id' => $this->agency->id]);
        $superadmin->assignRole('superadmin');
        Role::create(['name' => 'pengguna_biasa']);

        Livewire::actingAs($superadmin)->test(UserManagement::class)
            ->call('openModal', $this->applicant->id)
            ->set('role', 'pengguna_biasa')
            ->set('agency_id', $this->makeAgency('JPN')->id)
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSee('masih Pegawai Bertanggungjawab')
            ->assertSee($application->reference_number);
    }

    // --- PEMBANTU ---

    private function makeAgency(string $code): Agency
    {
        return Agency::create(['code' => $code, 'name' => "Agensi {$code}", 'category' => 'SUK', 'is_active' => true]);
    }

    private function submittedApplication(?User $secondary = null): Application
    {
        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . random_int(1000, 9999),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => 'Projek Ujian',
            'estimated_cost' => 10000,
            'status' => 'SUBMITTED',
        ]);

        $application->officers()->create(ApplicationOfficer::snapshotFrom($this->applicant) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        if ($secondary) {
            $application->officers()->create(ApplicationOfficer::snapshotFrom($secondary) + [
                'role' => OfficerRole::Secondary,
                'started_at' => today()->subMonth(),
            ]);
        }

        return $application;
    }

    private function handover(Application $application, User $newOfficer): void
    {
        app(OfficerHandoverService::class)->reassign(
            $application, OfficerRole::Primary, $newOfficer, today(), OfficerEndReason::TransferOut, null, $this->urusSetia,
        );
    }

    private function fillValidForm($component): void
    {
        $component
            ->set('form.title', 'Projek Ujian')
            ->set('form.application_category', 'pembangunan_sistem')
            ->set('form.objectives', 'Objektif projek ujian yang lengkap.')
            ->set('form.project_scope', 'Skop projek ujian yang lengkap.')
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
