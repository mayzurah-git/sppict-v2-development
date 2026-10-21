<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ProjectCategory;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;
use ZipArchive;

class ApplicationDocumentTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private Agency $agency;
    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->openJtictMeeting();

        $this->agency = $this->makeAgency('JKR');
        $this->applicant = User::factory()->create(['agency_id' => $this->agency->id]);
        $this->applicant->assignRole(Role::findOrCreate('pengguna_biasa')); // UM7A: hanya pengguna_biasa/urus setia boleh mendaftar permohonan
    }

    // --- FORMAT & SAIZ FAIL ---

    public function test_proposal_paper_accepts_pdf_and_docx(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);

        $component->set('form.proposal_paper', $this->pdf('kertas.pdf'))->assertHasNoErrors();
        $component->set('form.proposal_paper', $this->docx('kertas.docx'))->assertHasNoErrors();
    }

    public function test_proposal_paper_rejects_legacy_doc_and_other_formats(): void
    {
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('form.proposal_paper', UploadedFile::fake()->createWithContent('kertas.doc', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"))
            ->assertHasErrors('form.proposal_paper')
            ->assertSet('form.proposal_paper', null)
            ->set('form.proposal_paper', UploadedFile::fake()->create('gambar.png', 10, 'image/png'))
            ->assertHasErrors('form.proposal_paper');
    }

    public function test_presentation_slide_accepts_pdf_only(): void
    {
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('form.presentation_slide', $this->docx('slaid.docx'))
            ->assertHasErrors('form.presentation_slide')
            ->assertSet('form.presentation_slide', null)
            ->set('form.presentation_slide', $this->pdf('slaid.pdf'))
            ->assertHasNoErrors();
    }

    public function test_renamed_files_are_rejected_by_content_signature(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);

        $component->set('form.proposal_paper', UploadedFile::fake()->createWithContent('palsu.pdf', 'MZ' . str_repeat("\x90", 512)))
            ->assertHasErrors('form.proposal_paper');

        $component->set('form.proposal_paper', UploadedFile::fake()->createWithContent('palsu.pdf', 'Ini bukan dokumen PDF.'))
            ->assertHasErrors('form.proposal_paper');

        $component->set('form.proposal_paper', UploadedFile::fake()->createWithContent('palsu.docx', 'Ini bukan dokumen Word.'))
            ->assertHasErrors('form.proposal_paper');
    }

    public function test_file_larger_than_25mb_is_rejected(): void
    {
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('form.proposal_paper', UploadedFile::fake()->create('besar.pdf', 25601, 'application/pdf'))
            ->assertHasErrors('form.proposal_paper');
    }

    // --- DRAF & HANTAR ---

    public function test_draft_saves_documents_privately_and_reopened_draft_does_not_require_reupload(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('form.proposal_paper', $this->pdf('kertas.pdf'))
            ->call('saveDraft');

        $application = Application::sole();
        $document = $application->documents()->sole();

        $this->assertSame('DRAFT', $application->status);
        $this->assertSame('application/pdf', $document->mime_type);
        Storage::disk('local')->assertExists($document->file_path);
        $component->assertSet('form.proposal_paper', null);

        Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->set('currentStep', 4)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 5);
    }

    public function test_submitting_a_draft_updates_the_same_application(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $this->fillValidForm($component);
        $component->call('saveDraft');

        $draft = Application::sole();
        $this->assertStringContainsString('/DRAFT-', $draft->reference_number);

        $component->call('submitApplication')->assertHasNoErrors();

        $submitted = Application::sole();
        $this->assertSame($draft->id, $submitted->id);
        $this->assertSame('SUBMITTED', $submitted->status);
        $this->assertStringNotContainsString('DRAFT', $submitted->reference_number);
        $this->assertSame(1, $submitted->documents()->count());
    }

    public function test_application_id_cannot_be_tampered_from_browser(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)->set('applicationId', 999);
    }

    // --- KAWALAN STATUS ---

    public function test_only_draft_or_needs_amendment_applications_can_be_edited(): void
    {
        foreach (['DRAFT' => 200, 'NEEDS_AMENDMENT' => 200, 'SUBMITTED' => 403, 'UNDER_REVIEW' => 403] as $status => $expected) {
            $application = $this->makeApplication($this->applicant, $status);

            $this->actingAs($this->applicant)
                ->get(route('application.edit', $application->uuid))
                ->assertStatus($expected);
        }
    }

    public function test_replacing_a_document_soft_deletes_the_old_one_and_keeps_its_file(): void
    {
        $application = $this->makeApplication($this->applicant, 'NEEDS_AMENDMENT');
        $old = $this->storeDocument($application);

        Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->set('form.proposal_paper', $this->docx('baharu.docx'))
            ->call('saveDraft');

        $this->assertSoftDeleted($old);
        Storage::disk('local')->assertExists($old->file_path);

        $new = $application->documents()->sole();
        $this->assertSame('baharu.docx', $new->file_name);
        $this->assertSame('NEEDS_AMENDMENT', $application->fresh()->status);
    }

    public function test_document_can_be_deleted_only_while_editable(): void
    {
        $application = $this->makeApplication($this->applicant, 'DRAFT');
        $document = $this->storeDocument($application);

        // Borang dibuka semasa Draf, kemudian permohonan dihantar di tab lain
        $staleComponent = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $application->update(['status' => 'SUBMITTED']);

        $staleComponent->call('deleteDocument', $document->uuid)->assertForbidden();
        $this->assertNotSoftDeleted($document);

        $application->update(['status' => 'DRAFT']);
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->call('deleteDocument', $document->uuid);

        $this->assertSoftDeleted($document);
        Storage::disk('local')->assertExists($document->file_path);
    }

    // --- AKSES DOKUMEN ---

    public function test_document_access_is_limited_to_applicant_urus_setia_and_same_agency(): void
    {
        Role::create(['name' => 'pentadbir_urus_setia']);

        $application = $this->makeApplication($this->applicant, 'SUBMITTED');
        $document = $this->storeDocument($application);
        $url = route('application.document', [$application->uuid, $document->uuid]);

        $urusSetia = User::factory()->create(['agency_id' => $this->makeAgency('BTM')->id]);
        $urusSetia->assignRole('pentadbir_urus_setia');
        $sameAgency = User::factory()->create(['agency_id' => $this->agency->id]);
        $otherAgency = User::factory()->create(['agency_id' => $this->makeAgency('JPN')->id]);

        $this->actingAs($this->applicant)->get($url)->assertOk();
        $this->actingAs($urusSetia)->get($url)->assertOk();
        $this->actingAs($sameAgency)->get($url)->assertOk();
        $this->actingAs($otherAgency)->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    // --- PEMBANTU ---

    private function makeAgency(string $code): Agency
    {
        return Agency::create(['code' => $code, 'name' => "Agensi {$code}", 'category' => 'SUK', 'is_active' => true]);
    }

    private function makeApplication(User $applicant, string $status): Application
    {
        static $sequence = 0;
        $sequence++;

        return Application::create([
            'reference_number' => sprintf('SPPICT/2026/T%04d', $sequence),
            'agency_id' => $applicant->agency_id,
            'applicant_id' => $applicant->id,
            'title' => 'Permohonan Ujian',
            'estimated_cost' => 10000,
            'status' => $status,
        ]);
    }

    private function storeDocument(Application $application): ApplicationDocument
    {
        $path = "applications/{$application->uuid}/documents/lama.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 lama');

        return ApplicationDocument::create([
            'application_id' => $application->id,
            'document_type' => 'PROPOSAL_PAPER',
            'file_name' => 'lama.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size_kb' => 1,
        ]);
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
            ->set('form.proposal_paper', $this->pdf('kertas.pdf'))
            ->set('form.primary_officer_id', $this->applicant->id);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    private function docx(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body/></w:document>');
        $zip->close();

        return UploadedFile::fake()->createWithContent($name, file_get_contents($path));
    }
}
