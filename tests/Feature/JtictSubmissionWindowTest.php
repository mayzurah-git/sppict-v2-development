<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ProjectCategory;
use App\Models\Meeting;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JtictSubmissionWindowTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->applicant = User::factory()->create(['agency_id' => $agency->id]);
        $this->applicant->assignRole(Role::findOrCreate('pengguna_biasa')); // UM7A: hanya pengguna_biasa/urus setia boleh mendaftar permohonan
    }

    // --- TAKRIF MESYUARAT AKTIF ---

    public function test_only_active_jtict_meeting_within_window_is_open(): void
    {
        $this->assertNull(Meeting::currentJtict());

        $notOpen = [
            'tidak aktif' => ['is_active' => false],
            'tamat tempoh' => ['submission_opens_at' => today()->subDays(10), 'submission_closes_at' => today()->subDay()],
            'belum dibuka' => ['submission_opens_at' => today()->addDay(), 'submission_closes_at' => today()->addDays(10)],
            'JPICT' => ['code' => 'JPICTNS'],
            'tiada tempoh' => ['submission_opens_at' => null, 'submission_closes_at' => null],
        ];

        foreach ($notOpen as $case => $attributes) {
            $meeting = $this->openJtictMeeting($attributes);
            $this->assertNull(Meeting::currentJtict(), "Tidak sepatutnya aktif: {$case}");
            $meeting->forceDelete();
        }

        // Tarikh buka & tutup adalah inklusif
        $meeting = $this->openJtictMeeting(['submission_opens_at' => today(), 'submission_closes_at' => today()]);
        $this->assertTrue($meeting->is(Meeting::currentJtict()));
    }

    // --- BUTANG HANTAR ---

    public function test_submit_button_is_disabled_without_open_meeting(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('currentStep', 5)
            ->assertSee('Tiada Mesyuarat JTICT yang dibuka');

        $this->assertTrue($this->submitButtonDisabled($component));
    }

    public function test_submit_button_is_enabled_with_open_meeting(): void
    {
        $this->openJtictMeeting(['series' => 'Bil. 3/2026']);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('currentStep', 5)
            ->assertSee('Permohonan dibuka untuk JTICTNS Bil. 3/2026');

        $this->assertFalse($this->submitButtonDisabled($component));
    }

    // --- KAWALAN DI PELAYAN ---

    public function test_submit_without_open_meeting_is_saved_as_draft(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $this->fillValidForm($component);

        $component->call('submitApplication')
            ->assertNoRedirect()
            ->assertSee('Tempoh permohonan Mesyuarat JTICT telah ditutup');

        $application = Application::sole();
        $this->assertSame('DRAFT', $application->status);
        $this->assertStringContainsString('/DRAFT-', $application->reference_number);
        $this->assertNull($application->jtict_meeting_id);
        $this->assertSame(1, $application->documents()->count());
        $this->assertNotNull($application->primaryOfficer);

        // Draf yang sama dikemas kini pada cubaan seterusnya (tiada rekod pendua)
        $component->assertSet('applicationId', $application->id)->call('submitApplication');
        $this->assertSame(1, Application::count());
    }

    public function test_meeting_closing_while_form_is_open_keeps_application_as_draft(): void
    {
        $meeting = $this->openJtictMeeting();

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $this->fillValidForm($component);

        // Urus Setia menutup tempoh ketika pemohon masih mengisi borang
        $meeting->update(['submission_closes_at' => today()->subDay()]);

        $component->call('submitApplication')->assertNoRedirect();

        $this->assertSame('DRAFT', Application::sole()->status);
    }

    public function test_submission_is_attached_to_open_meeting(): void
    {
        $meeting = $this->openJtictMeeting();

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertRedirect('/dashboard');

        $application = Application::sole();
        $this->assertSame('SUBMITTED', $application->status);
        $this->assertSame($meeting->id, $application->jtict_meeting_id);
    }

    public function test_draft_can_be_saved_without_open_meeting(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $this->fillValidForm($component);
        $component->call('saveDraft')->assertHasNoErrors();

        $this->assertSame('DRAFT', Application::sole()->status);
    }

    // --- PERLU PINDAAN ---

    public function test_needs_amendment_resubmits_to_original_meeting_even_when_closed(): void
    {
        $original = $this->openJtictMeeting(['series' => 'Bil. 1/2026']);
        $application = $this->amendmentFor($original);

        // Tempoh asal ditutup dan mesyuarat baharu dibuka
        $original->update(['submission_closes_at' => today()->subDay()]);
        $this->openJtictMeeting(['series' => 'Bil. 2/2026']);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->set('currentStep', 5)
            ->assertSee('Penghantaran semula pindaan untuk JTICTNS Bil. 1/2026')
            ;

        $this->assertFalse($this->submitButtonDisabled($component));

        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors()->assertRedirect('/dashboard');

        $application->refresh();
        $this->assertSame('SUBMITTED', $application->status);
        $this->assertSame($original->id, $application->jtict_meeting_id);
    }

    // --- PEMBANTU ---

    private function submitButtonDisabled($component): bool
    {
        preg_match('/<button[^>]*wire:click="submitApplication"[^>]*>/s', $component->html(), $match);
        $this->assertNotEmpty($match, 'Butang Hantar tidak dijumpai.');

        return (bool) preg_match('/\sdisabled(\s|>|=)/', $match[0]);
    }

    private function amendmentFor(Meeting $meeting): Application
    {
        $application = Application::create([
            'reference_number' => 'SPPICT/2026/0099',
            'agency_id' => $this->applicant->agency_id,
            'applicant_id' => $this->applicant->id,
            'title' => 'Permohonan Pindaan',
            'estimated_cost' => 10000,
            'status' => 'NEEDS_AMENDMENT',
            'jtict_meeting_id' => $meeting->id,
        ]);

        $application->officers()->create(\App\Models\ApplicationOfficer::snapshotFrom($this->applicant) + [
            'role' => \App\Enums\OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        return $application;
    }

    private function fillValidForm($component): void
    {
        $component
            ->set('form.title', 'Projek Ujian')
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
