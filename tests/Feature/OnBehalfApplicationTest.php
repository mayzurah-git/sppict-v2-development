<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\OfficerRole;
use App\Livewire\ApplicationForm;
use App\Livewire\ApplicationList;
use App\Livewire\Forms\ApplicationForm as ApplicationFormObject;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Notifications\ApplicationMarkedIncomplete;
use App\Services\ApplicationReviewService;
use App\Services\ApplicationSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP3: permohonan projek oleh Urus Setia bagi pihak agensi sasaran.
 */
class OnBehalfApplicationTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private const REMARKS = 'Sila lampirkan Slaid Pembentangan dan perincikan skop projek.';

    private Agency $ownAgency;

    private Agency $targetAgency;

    private User $urusSetia;

    private User $targetOfficer;

    private User $ownAgencyUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->ownAgency = Agency::create(['code' => 'BTM', 'name' => 'Bahagian Teknologi Maklumat', 'category' => 'SUK', 'is_active' => true]);
        $this->targetAgency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'STATE_DEPT', 'is_active' => true]);

        $this->urusSetia = User::factory()->create(['name' => 'Siti Urus Setia', 'agency_id' => $this->ownAgency->id]);
        $this->urusSetia->assignRole('pentadbir_urus_setia');

        $this->targetOfficer = User::factory()->create(['name' => 'Ahmad Pegawai JKR', 'agency_id' => $this->targetAgency->id]);
        $this->targetOfficer->assignRole('pengguna_biasa');

        $this->ownAgencyUser = User::factory()->create(['name' => 'Rakan BTM', 'agency_id' => $this->ownAgency->id]);
        $this->ownAgencyUser->assignRole('pengguna_biasa');
    }

    // --- 11.1 PERMOHONAN BAGI PIHAK AGENSI ---

    public function test_urus_setia_must_choose_target_agency_before_filling_form(): void
    {
        $component = Livewire::actingAs($this->urusSetia)->test(ApplicationForm::class)
            ->assertSee('Permohonan bagi Pihak Agensi')
            ->assertDontSeeHtml('wire:click="nextStep"')
            ->assertDontSeeHtml('wire:click="saveDraft"');

        // Tindakan dipanggil terus (memintas UI) tetap disekat
        $component->call('nextStep')->assertHasErrors('targetAgencyId')->assertSet('currentStep', 1);
        $component->call('saveDraft')->assertHasErrors('targetAgencyId');
        $component->call('submitApplication')->assertHasErrors('targetAgencyId');
        $this->assertSame(0, Application::count());

        $component->call('confirmAgency')->assertHasErrors(['targetAgencyId' => 'required']);
    }

    public function test_only_active_agencies_are_offered_and_accepted(): void
    {
        $inactive = Agency::create(['code' => 'OLD', 'name' => 'Agensi Tidak Aktif', 'category' => 'SUK', 'is_active' => false]);
        $deleted = Agency::create(['code' => 'DEL', 'name' => 'Agensi Dipadam', 'category' => 'SUK', 'is_active' => true]);
        $deleted->delete();

        $component = Livewire::actingAs($this->urusSetia)->test(ApplicationForm::class)
            ->assertSee('Jabatan Kerja Raya (JKR)')
            ->assertDontSee('Agensi Tidak Aktif')
            ->assertDontSee('Agensi Dipadam');

        foreach ([$inactive->id, $deleted->id, 999999, 'abc'] as $invalid) {
            $component->set('targetAgencyId', $invalid)
                ->call('confirmAgency')
                ->assertHasErrors('targetAgencyId')
                ->assertSet('confirmedAgencyId', null);
        }

        $component->set('targetAgencyId', $this->targetAgency->id)
            ->call('confirmAgency')
            ->assertHasNoErrors()
            ->assertSet('confirmedAgencyId', $this->targetAgency->id)
            ->assertSee('Permohonan bagi pihak:')
            ->assertSee('Jabatan Kerja Raya (JKR)')
            ->assertSeeHtml('wire:click="nextStep"');
    }

    public function test_confirmed_agency_cannot_be_set_directly_from_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->urusSetia)->test(ApplicationForm::class)
            ->set('confirmedAgencyId', $this->targetAgency->id);
    }

    public function test_draft_is_saved_under_target_agency_without_open_meeting(): void
    {
        $this->assertNull(\App\Models\Meeting::currentJtict());
        config(['audit.console' => true]); // rekod audit sedia ada (owen-it) sebagai jejak tindakan

        $component = $this->onBehalfForm()
            ->set('form.title', 'Projek JKR oleh Urus Setia')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $application = Application::sole();
        $this->assertSame(ApplicationStatus::Draft->value, $application->status);
        $this->assertSame($this->targetAgency->id, $application->agency_id, 'Agensi sasaran, bukan agensi Urus Setia');
        $this->assertSame($this->urusSetia->id, $application->applicant_id, 'Pendaftar rekod ialah Urus Setia');
        $this->assertStringContainsString('/DRAFT-', $application->reference_number);
        $this->assertNull($application->jtict_meeting_id);

        // Selepas disimpan, agensi dikunci (tiada butang tukar; borang menunjukkan agensi rekod)
        $component->assertDontSee('Tukar Agensi')->assertSee('Jabatan Kerja Raya (JKR)');

        // Log audit merekod pengguna yang membuat tindakan dan agensi sasaran
        $audit = Audit::where('auditable_type', Application::class)->where('auditable_id', $application->id)->where('event', 'created')->sole();
        $this->assertSame($this->urusSetia->id, (int) $audit->user_id);
        $this->assertSame($this->targetAgency->id, (int) $audit->new_values['agency_id']);
    }

    public function test_officer_candidates_come_from_target_agency_only(): void
    {
        $component = $this->onBehalfForm()
            ->assertSet('form.primary_officer_id', '')
            ->set('currentStep', 5)
            ->assertSee('Ahmad Pegawai JKR')
            ->assertDontSee('Rakan BTM');

        // Pegawai daripada agensi Urus Setia (bukan agensi sasaran) ditolak di pelayan
        $this->openJtictMeeting();
        $this->fillValidForm($component, $this->ownAgencyUser);
        $component->call('submitApplication')->assertHasErrors('form.primary_officer_id');
        $this->assertSame(0, Application::count());
    }

    public function test_changing_agency_before_save_resets_officers(): void
    {
        $component = $this->onBehalfForm()
            ->set('form.primary_officer_id', $this->targetOfficer->id)
            ->call('changeAgency')
            ->assertSet('confirmedAgencyId', null)
            ->assertSet('form.primary_officer_id', '')
            ->assertSee('Permohonan bagi Pihak Agensi');

        $component->set('targetAgencyId', $this->ownAgency->id)->call('confirmAgency')
            ->assertSet('confirmedAgencyId', $this->ownAgency->id);
    }

    public function test_submission_requires_open_meeting_and_enters_review_list(): void
    {
        // Tanpa mesyuarat aktif: disimpan sebagai Draf (peraturan sedia ada tidak dilonggarkan)
        $component = $this->onBehalfForm();
        $this->fillValidForm($component, $this->targetOfficer);
        $component->call('submitApplication')->assertNoRedirect()->assertSee('Tempoh permohonan Mesyuarat JTICT telah ditutup');

        $application = Application::sole();
        $this->assertSame(ApplicationStatus::Draft->value, $application->status);
        $this->assertSame($this->targetAgency->id, $application->agency_id);

        // Mesyuarat dibuka: draf yang sama dihantar
        $meeting = $this->openJtictMeeting();
        $this->fillValidForm($component, $this->targetOfficer);
        $component->call('submitApplication')->assertHasNoErrors()->assertRedirect('/dashboard');

        $application->refresh();
        $this->assertSame(1, Application::count());
        $this->assertSame(ApplicationStatus::Submitted->value, $application->status);
        $this->assertSame($meeting->id, $application->jtict_meeting_id);
        $this->assertSame($this->targetAgency->id, $application->agency_id);
        $this->assertSame($this->urusSetia->id, $application->applicant_id);
        $this->assertSame($this->targetOfficer->id, $application->primaryOfficer->user_id);

        Livewire::actingAs($this->urusSetia)->test(ApplicationList::class)
            ->assertSee('Projek Bagi Pihak JKR')
            ->assertSee('JKR');
    }

    public function test_urus_setia_drafts_list_shows_only_own_drafts(): void
    {
        $this->onBehalfForm()->set('form.title', 'Draf Urus Setia JKR')->call('saveDraft');

        $other = User::factory()->create(['agency_id' => $this->targetAgency->id]);
        $other->assignRole('pengguna_biasa');
        Livewire::actingAs($other)->test(ApplicationForm::class)->set('form.title', 'Draf Pemohon Lain')->call('saveDraft');

        $draft = Application::where('applicant_id', $this->urusSetia->id)->sole();

        Livewire::actingAs($this->urusSetia)->test(ApplicationList::class)
            ->assertDontSee('Draf Urus Setia JKR')
            ->set('statusFilter', 'DRAFT')
            ->assertSee('Draf Urus Setia JKR')
            ->assertDontSee('Draf Pemohon Lain')
            ->assertSeeHtml(route('application.edit', $draft->uuid));

        // Draf dibuka semula: agensi rekod dipaparkan, tiada pemilih agensi
        Livewire::actingAs($this->urusSetia)->test(ApplicationForm::class, ['uuid' => $draft->uuid])
            ->assertSet('form.title', 'Draf Urus Setia JKR')
            ->assertSee('Jabatan/Agensi Permohonan:')
            ->assertSee('Jabatan Kerja Raya (JKR)')
            ->assertDontSee('Permohonan bagi Pihak Agensi');
    }

    public function test_agency_of_saved_application_cannot_be_changed(): void
    {
        $component = $this->onBehalfForm()->set('form.title', 'Projek Terkunci')->call('saveDraft');
        $application = Application::sole();

        $component->call('changeAgency')->assertForbidden();

        $edit = Livewire::actingAs($this->urusSetia)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $edit->set('targetAgencyId', $this->ownAgency->id)->call('confirmAgency')->assertForbidden();

        // Servis menolak agensi sasaran bagi rekod sedia ada
        try {
            app(ApplicationSubmissionService::class)->saveDraft($this->formObject('Projek Terkunci'), $this->urusSetia, $application->id, $this->ownAgency->id);
            $this->fail('Agensi rekod sedia ada tidak sepatutnya boleh diubah.');
        } catch (AuthorizationException) {
        }

        $this->assertSame($this->targetAgency->id, $application->fresh()->agency_id);
    }

    // --- 11.2 PENGGUNA BIASA ---

    public function test_regular_user_has_no_agency_picker_and_uses_own_agency(): void
    {
        $component = Livewire::actingAs($this->ownAgencyUser)->test(ApplicationForm::class)
            ->assertDontSee('Permohonan bagi Pihak Agensi')
            ->assertSee('Jabatan/Agensi Permohonan:')
            ->assertSee('Bahagian Teknologi Maklumat (BTM)')
            ->assertSet('form.primary_officer_id', $this->ownAgencyUser->id);

        // Manipulasi payload: agensi lain diabaikan; rekod kekal pada agensi akaun pengguna
        $component->set('targetAgencyId', $this->targetAgency->id)
            ->set('form.title', 'Projek Pengguna Biasa')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $application = Application::sole();
        $this->assertSame($this->ownAgency->id, $application->agency_id);
        $this->assertSame($this->ownAgencyUser->id, $application->applicant_id);
    }

    public function test_regular_user_cannot_use_on_behalf_function(): void
    {
        Livewire::actingAs($this->ownAgencyUser)->test(ApplicationForm::class)
            ->set('targetAgencyId', $this->targetAgency->id)
            ->call('confirmAgency')
            ->assertForbidden();

        try {
            Livewire::actingAs($this->ownAgencyUser)->test(ApplicationForm::class)
                ->set('confirmedAgencyId', $this->targetAgency->id);
            $this->fail('confirmedAgencyId sepatutnya dikunci.');
        } catch (CannotUpdateLockedPropertyException) {
        }

        // Panggilan servis terus dengan agensi lain ditolak
        try {
            app(ApplicationSubmissionService::class)->saveDraft($this->formObject('Projek Manipulasi'), $this->ownAgencyUser, null, $this->targetAgency->id);
            $this->fail('Pengguna biasa tidak sepatutnya boleh memilih agensi lain.');
        } catch (AuthorizationException) {
        }

        $this->assertSame(0, Application::count());
    }

    // --- 11.4 KAWALAN AKSES ---

    public function test_only_urus_setia_may_create_on_behalf(): void
    {
        $this->assertTrue($this->urusSetia->can('createOnBehalf', Application::class));
        $this->assertFalse($this->ownAgencyUser->can('createOnBehalf', Application::class));

        foreach (['pengurusan', 'superadmin'] as $role) {
            $user = User::factory()->create(['agency_id' => $this->ownAgency->id]);
            $user->assignRole($role);
            $this->assertFalse($user->can('createOnBehalf', Application::class), $role);
        }

        // Urus Setia yang turut memegang peranan superadmin/pengurusan tidak dibenarkan (ikut `create`)
        $mixed = User::factory()->create(['agency_id' => $this->ownAgency->id]);
        $mixed->assignRole(['pentadbir_urus_setia', 'pengurusan']);
        $this->assertFalse($mixed->can('createOnBehalf', Application::class));
    }

    public function test_target_agency_users_can_view_but_other_agencies_cannot(): void
    {
        $application = $this->submittedOnBehalf();

        $this->actingAs($this->targetOfficer)->get(route('application.view', $application->uuid))
            ->assertOk()
            ->assertSee('Jabatan Kerja Raya (JKR)')
            ->assertSee('Didaftarkan oleh:')
            ->assertSee('Siti Urus Setia')
            // UAT PP3 langkah 9: Pegawai Utama agensi sasaran dipaparkan di bahagian atas halaman
            ->assertSeeInOrder(['Pegawai Utama:', 'Ahmad Pegawai JKR']);

        $sameAgencyColleague = User::factory()->create(['agency_id' => $this->targetAgency->id]);
        $sameAgencyColleague->assignRole('pengguna_biasa');
        $this->assertFalse($sameAgencyColleague->can('update', $application), 'Rakan seagensi yang bukan pegawai bertanggungjawab tidak boleh meminda');

        $this->actingAs($this->ownAgencyUser)->get(route('application.view', $application->uuid))->assertForbidden();
    }

    // --- 11.3 IDENTITI & SEJARAH: KITARAN PP2 ---

    public function test_pp2_incomplete_cycle_works_for_on_behalf_application(): void
    {
        Notification::fake();

        $application = $this->submittedOnBehalf();

        // Polisi konflik kepentingan tidak diubah: Urus Setia pendaftar masih boleh menyemak (isu tertangguh)
        $this->assertTrue($this->urusSetia->can('review', $application));

        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        // E-mel kepada pendaftar (Urus Setia) dan Pegawai Utama agensi sasaran
        Notification::assertSentTo($this->urusSetia, ApplicationMarkedIncomplete::class);
        Notification::assertSentTo($this->targetOfficer, ApplicationMarkedIncomplete::class);

        // Pendaftar membuat pindaan dan menghantar semula; agensi kekal
        $component = Livewire::actingAs($this->urusSetia)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->assertSee(self::REMARKS)
            ->assertDontSee('Permohonan bagi Pihak Agensi');
        $this->fillValidForm($component, $this->targetOfficer);
        $component->call('submitApplication')->assertHasNoErrors()->assertRedirect('/dashboard');

        $application->refresh();
        $this->assertSame(ApplicationStatus::Submitted->value, $application->status);
        $this->assertSame($this->targetAgency->id, $application->agency_id);
        $this->assertSame(
            [['SUBMITTED', 'NEEDS_AMENDMENT', $this->urusSetia->id], ['NEEDS_AMENDMENT', 'SUBMITTED', $this->urusSetia->id]],
            $application->reviews()->orderBy('id')->get()->map(fn ($r) => [$r->from_status, $r->to_status, $r->user_id])->all()
        );
        $this->assertSame($this->targetOfficer->id, $application->primaryOfficer->user_id);
        $this->assertSame(OfficerRole::Primary, $application->primaryOfficer->role);
    }

    // --- UAT PP3 LANGKAH 12: PINDAAN OLEH PEGAWAI BERTANGGUNGJAWAB AGENSI SASARAN ---

    public function test_target_agency_officer_can_amend_and_resubmit_incomplete_application(): void
    {
        Notification::fake();

        $application = $this->submittedOnBehalf();
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        $this->actingAs($this->targetOfficer)->get(route('application.view', $application->uuid))->assertOk()->assertSee('Kemas Kini & Hantar Semula');
        $this->actingAs($this->targetOfficer)->get(route('application.edit', $application->uuid))->assertOk();

        $component = Livewire::actingAs($this->targetOfficer)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->assertSee(self::REMARKS)
            ->assertDontSee('Permohonan bagi Pihak Agensi');
        $this->fillValidForm($component, $this->targetOfficer);
        $component->set('form.title', 'Projek JKR Dipinda oleh Pegawai')
            ->call('submitApplication')
            ->assertHasNoErrors()
            ->assertRedirect('/dashboard');

        $application->refresh();
        $this->assertSame(ApplicationStatus::Submitted->value, $application->status);
        $this->assertSame('Projek JKR Dipinda oleh Pegawai', $application->title);
        $this->assertSame($this->targetAgency->id, $application->agency_id, 'Agensi kekal');
        $this->assertSame($this->urusSetia->id, $application->applicant_id, 'Pendaftar rekod kekal Urus Setia');
        $this->assertSame(
            [['SUBMITTED', 'NEEDS_AMENDMENT', $this->urusSetia->id], ['NEEDS_AMENDMENT', 'SUBMITTED', $this->targetOfficer->id]],
            $application->reviews()->orderBy('id')->get()->map(fn ($r) => [$r->from_status, $r->to_status, $r->user_id])->all(),
            'Penghantaran semula direkodkan atas nama pegawai yang meminda'
        );

        // Selepas dihantar semula, pegawai tidak lagi boleh meminda (status bukan Draf / Tidak Lengkap)
        $this->assertFalse($this->targetOfficer->can('update', $application));
    }

    public function test_active_primary_and_secondary_officers_in_application_agency_may_edit(): void
    {
        $secondary = User::factory()->create(['name' => 'Bakar Pegawai Kedua', 'agency_id' => $this->targetAgency->id]);
        $secondary->assignRole('pengguna_biasa');

        // Draf bagi pihak agensi dengan Pegawai Utama & Kedua daripada agensi sasaran
        $this->onBehalfForm()
            ->set('form.title', 'Draf JKR')
            ->set('form.primary_officer_id', $this->targetOfficer->id)
            ->set('form.has_secondary_officer', true)
            ->set('form.secondary_officer_id', $secondary->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $application = Application::sole();
        $this->assertTrue($this->targetOfficer->can('update', $application), 'Pegawai Utama boleh meminda Draf');
        $this->assertTrue($secondary->can('update', $application), 'Pegawai Kedua boleh meminda Draf');
        $this->assertTrue($this->urusSetia->can('update', $application), 'Pendaftar kekal boleh meminda');

        // Pegawai Kedua menyimpan Draf: rekod dikemas kini, pendaftar & agensi tidak berubah
        Livewire::actingAs($secondary)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->set('form.title', 'Draf JKR Dikemas Kini')
            ->call('saveDraft')
            ->assertHasNoErrors();
        $application->refresh();
        $this->assertSame('Draf JKR Dikemas Kini', $application->title);
        $this->assertSame(ApplicationStatus::Draft->value, $application->status);
        $this->assertSame($this->urusSetia->id, $application->applicant_id);
        $this->assertSame($this->targetAgency->id, $application->agency_id);

        // Dihantar / Lengkap: tiada pindaan oleh sesiapa
        foreach ([ApplicationStatus::Submitted, ApplicationStatus::Complete] as $status) {
            $application->forceFill(['status' => $status->value])->save();
            $this->assertFalse($this->targetOfficer->can('update', $application), $status->value);
            $this->actingAs($this->targetOfficer)->get(route('application.edit', $application->uuid))->assertForbidden();
        }

        $application->forceFill(['status' => ApplicationStatus::NeedsAmendment->value])->save();
        $this->assertTrue($secondary->can('update', $application), 'Pegawai Kedua boleh meminda Tidak Lengkap');

        // Pegawai yang tugasannya ditamatkan tidak lagi boleh meminda
        $application->secondaryOfficer->update(['ended_at' => now()]);
        $this->assertFalse($secondary->fresh()->can('update', $application->fresh()));

        // Pegawai yang bertukar keluar daripada agensi permohonan tidak lagi boleh meminda
        $this->targetOfficer->update(['agency_id' => $this->ownAgency->id]);
        $this->assertFalse($this->targetOfficer->fresh()->can('update', $application->fresh()));
        $this->actingAs($this->targetOfficer->fresh())->get(route('application.edit', $application->uuid))->assertForbidden();
    }

    // --- PEMBANTU ---

    private function onBehalfForm()
    {
        return Livewire::actingAs($this->urusSetia)->test(ApplicationForm::class)
            ->set('targetAgencyId', $this->targetAgency->id)
            ->call('confirmAgency')
            ->assertHasNoErrors();
    }

    private function submittedOnBehalf(): Application
    {
        $this->openJtictMeeting();

        $component = $this->onBehalfForm();
        $this->fillValidForm($component, $this->targetOfficer);
        $component->call('submitApplication')->assertHasNoErrors();

        return Application::sole();
    }

    private function formObject(string $title): ApplicationFormObject
    {
        $form = new ApplicationFormObject(new ApplicationForm(), 'form');
        $form->title = $title;

        return $form;
    }

    private function fillValidForm($component, User $officer): void
    {
        $component
            ->set('form.title', 'Projek Bagi Pihak JKR')
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
            ->set('form.proposal_paper', UploadedFile::fake()->createWithContent('kertas.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"))
            ->set('form.primary_officer_id', $officer->id);
    }
}
