<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Services\ProjectDetailsCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP4.4: Templat & import CSV Perincian Projek (Fasa 3).
 */
class ProjectDetailsCsvImportTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private const HEADER = "kod_kategori_projek,spesifikasi_teknikal,kuantiti,kos_seunit_rm\n";

    // --- Templat ---

    public function test_template_download_contains_headers_and_active_category_codes(): void
    {
        ProjectCategory::where('code', 'khidmat_perunding_ict')->update(['is_active' => false]);

        $component = Livewire::actingAs($this->applicant())->test(ApplicationForm::class)
            ->call('downloadDetailsTemplate')
            ->assertFileDownloaded('templat-perincian-projek.csv');

        $content = base64_decode($component->effects['download']['content']);

        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'kod_kategori_projek,spesifikasi_teknikal,kuantiti,kos_seunit_rm', $content);
        $this->assertStringContainsString('projek_baharu', $content);
        $this->assertStringNotContainsString('khidmat_perunding_ict', $content);
    }

    public function test_unmodified_template_is_rejected_as_having_no_items(): void
    {
        $template = app(ProjectDetailsCsvService::class)->template();

        $this->formWithStep3()
            ->set('detailsCsv', $this->csv($template))
            ->assertHasErrors('detailsCsv');
    }

    public function test_template_with_items_added_imports_successfully(): void
    {
        $template = app(ProjectDetailsCsvService::class)->template() . "projek_baharu,Pelayan aplikasi,1,30000\r\n";

        $this->formWithStep3()
            ->set('detailsCsv', $this->csv($template))
            ->assertHasNoErrors()
            ->assertSet('form.details.0.items.0.technical_specifications', 'Pelayan aplikasi');
    }

    // --- Import ke medan kosong ---

    public function test_valid_csv_fills_empty_fields_grouped_by_category(): void
    {
        $component = $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER
                . "projek_baharu,Komputer riba,10,4500.00\n"
                . "peningkatan_sistem,Naik taraf pelayan,1,\"12,000.50\"\n"
                . "PROJEK_BAHARU,Pencetak laser,2,RM 800\n"))
            ->assertHasNoErrors()
            ->assertSet('pendingCsvDetails', null);

        $details = $component->get('form.details');

        $this->assertCount(2, $details);
        $this->assertSame($this->masterId(ProjectCategory::class, 'projek_baharu'), $details[0]['project_category_id']);
        $this->assertSame([
            ['technical_specifications' => 'Komputer riba', 'unit_quantity' => 10, 'unit_cost' => 4500.0],
            ['technical_specifications' => 'Pencetak laser', 'unit_quantity' => 2, 'unit_cost' => 800.0],
        ], $details[0]['items']);
        $this->assertSame($this->masterId(ProjectCategory::class, 'peningkatan_sistem'), $details[1]['project_category_id']);
        $this->assertSame(12000.5, $details[1]['items'][0]['unit_cost']);
        $this->assertStringContainsString('3 item berjaya diimport', $component->get('csvImportNotice'));
    }

    public function test_imported_details_pass_step3_and_are_saved_with_draft(): void
    {
        $component = $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER . "projek_baharu,Komputer riba,10,4500.00\n"))
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4)
            ->call('saveDraft');

        $application = Application::with('details')->latest('id')->firstOrFail();

        $this->assertCount(1, $application->details);
        $this->assertSame('Komputer riba', $application->details[0]->technical_specifications);
        $this->assertSame(10, $application->details[0]->unit_quantity);
        $this->assertSame(45000.0, (float) $application->details[0]->total_cost);
        $component->assertSet('applicationId', $application->id);
    }

    public function test_semicolon_delimited_and_windows_1252_files_are_accepted(): void
    {
        $content = mb_convert_encoding(
            "kod_kategori_projek;spesifikasi_teknikal;kuantiti;kos_seunit_rm\r\nprojek_baharu;Kabel rangkaian – Cat6;5;12.50\r\n",
            'Windows-1252',
            'UTF-8'
        );

        $this->formWithStep3()
            ->set('detailsCsv', $this->csv($content))
            ->assertHasNoErrors()
            ->assertSet('form.details.0.items.0.technical_specifications', 'Kabel rangkaian – Cat6');
    }

    public function test_blank_and_comment_rows_are_ignored(): void
    {
        $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER . "\n,,,\n# nota pengguna\nprojek_baharu,Tetikus,3,40\n"))
            ->assertHasNoErrors()
            ->assertCount('form.details.0.items', 1);
    }

    // --- Ganti perincian sedia ada ---

    public function test_existing_details_require_replace_confirmation(): void
    {
        $component = $this->formWithStep3($this->existingItems())
            ->set('detailsCsv', $this->csv(self::HEADER . "projek_baharu,Item CSV 1,1,100\nprojek_baharu,Item CSV 2,1,200\n"))
            ->assertHasNoErrors()
            ->assertSet('pendingCsvItemCount', 2)
            ->assertSet('form.details.0.items.0.technical_specifications', 'Item manual A')
            ->assertSee('akan digantikan dengan')
            ->assertSee('2 item');

        $component->call('confirmCsvReplace')
            ->assertSet('pendingCsvDetails', null)
            ->assertCount('form.details', 1)
            ->assertCount('form.details.0.items', 2)
            ->assertSet('form.details.0.items.0.technical_specifications', 'Item CSV 1');
    }

    public function test_cancel_keeps_existing_details_unchanged(): void
    {
        $existing = $this->existingItems();

        $this->formWithStep3($existing)
            ->set('detailsCsv', $this->csv(self::HEADER . "projek_baharu,Item CSV,1,100\n"))
            ->call('cancelCsvReplace')
            ->assertSet('pendingCsvDetails', null)
            ->assertSet('form.details', $existing)
            ->call('confirmCsvReplace')
            ->assertSet('form.details', $existing);
    }

    public function test_default_blank_row_is_replaced_without_confirmation(): void
    {
        // Borang baharu bermula dengan satu baris kosong (kuantiti 1, kos 0)
        Livewire::actingAs($this->applicant())->test(ApplicationForm::class)
            ->set('currentStep', 3)
            ->set('detailsCsv', $this->csv(self::HEADER . "projek_baharu,Item CSV,1,100\n"))
            ->assertSet('pendingCsvDetails', null)
            ->assertSet('form.details.0.items.0.technical_specifications', 'Item CSV');
    }

    // --- Fail ditolak (semua-atau-tiada) ---

    public function test_wrong_header_is_rejected_and_details_unchanged(): void
    {
        $existing = $this->existingItems();

        $this->formWithStep3($existing)
            ->set('detailsCsv', $this->csv("kategori,spesifikasi,kuantiti,kos\nprojek_baharu,Item,1,100\n"))
            ->assertHasErrors('detailsCsv')
            ->assertSee('Format fail tidak mengikut templat sistem')
            ->assertSet('form.details', $existing)
            ->assertSet('pendingCsvDetails', null);
    }

    public function test_one_invalid_row_rejects_whole_file_with_row_numbers(): void
    {
        $component = $this->formWithStep3($this->existingItems())
            ->set('detailsCsv', $this->csv(self::HEADER
                . "projek_baharu,Item sah,1,100\n"
                . "kod_tiada,Item,1,100\n"
                . "projek_baharu,ab,0,abc\n"
                . "projek_baharu,Item,1.5,100.999\n"))
            ->assertHasErrors('detailsCsv')
            ->assertSet('pendingCsvDetails', null)
            ->assertSet('form.details.0.items.0.technical_specifications', 'Item manual A');

        $errors = $component->errors()->get('detailsCsv');

        $this->assertContains('Baris 3: kod kategori projek "kod_tiada" tidak sah atau tidak aktif.', $errors);
        $this->assertContains('Baris 4: spesifikasi teknikal hendaklah sekurang-kurangnya 3 aksara.', $errors);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_starts_with($e, 'Baris 4: kuantiti')));
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_starts_with($e, 'Baris 4: kos seunit')));
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_starts_with($e, 'Baris 5: kuantiti')));
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_starts_with($e, 'Baris 5: kos seunit')));
        $this->assertFalse(collect($errors)->contains(fn ($e) => str_starts_with($e, 'Baris 2')));
    }

    public function test_inactive_category_code_is_rejected(): void
    {
        ProjectCategory::where('code', 'khidmat_perunding_ict')->update(['is_active' => false]);

        $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER . "khidmat_perunding_ict,Kajian,1,100\n"))
            ->assertHasErrors('detailsCsv');
    }

    public function test_limits_on_quantity_cost_and_extra_columns(): void
    {
        $errors = $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER
                . "projek_baharu,Item,100001,100\n"
                . "projek_baharu,Item,1,10000000000\n"
                . "projek_baharu,Item,1,100,lebihan\n"
                . "projek_baharu,Item,1,-5\n"))
            ->assertHasErrors('detailsCsv')
            ->errors()->get('detailsCsv');

        $this->assertCount(4, $errors);
    }

    public function test_more_than_max_rows_is_rejected(): void
    {
        $rows = str_repeat("projek_baharu,Item,1,1\n", ProjectDetailsCsvService::MAX_ROWS + 1);

        $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER . $rows))
            ->assertHasErrors('detailsCsv')
            ->assertSee('lebih daripada 500 item');
    }

    public function test_exactly_max_rows_is_accepted(): void
    {
        $rows = str_repeat("projek_baharu,Item,1,1\n", ProjectDetailsCsvService::MAX_ROWS);

        $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER . $rows))
            ->assertHasNoErrors()
            ->assertCount('form.details.0.items', ProjectDetailsCsvService::MAX_ROWS);
    }

    public function test_non_csv_file_and_oversized_file_are_rejected(): void
    {
        $this->formWithStep3()
            ->set('detailsCsv', UploadedFile::fake()->create('perincian.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['detailsCsv' => 'extensions']);

        $this->formWithStep3()
            ->set('detailsCsv', UploadedFile::fake()->createWithContent('perincian.csv', self::HEADER . str_repeat('x', 1100 * 1024)))
            ->assertHasErrors(['detailsCsv' => 'max']);
    }

    public function test_html_in_specification_is_stripped(): void
    {
        $this->formWithStep3()
            ->set('detailsCsv', $this->csv(self::HEADER . "projek_baharu,<script>alert(1)</script>Monitor 24 inci,1,500\n"))
            ->assertHasNoErrors()
            ->assertSet('form.details.0.items.0.technical_specifications', 'alert(1)Monitor 24 inci');
    }

    public function test_imported_total_over_ceiling_is_caught_by_existing_step3_validation(): void
    {
        $this->formWithStep3(ceilingCost: 1000)
            ->set('detailsCsv', $this->csv(self::HEADER . "projek_baharu,Pelayan,1,5000\n"))
            ->assertHasNoErrors()
            ->call('nextStep')
            ->assertHasErrors('details_total')
            ->assertSet('currentStep', 3);
    }

    // --- Helper ---

    private function formWithStep3(?array $details = null, float $ceilingCost = 1000000)
    {
        $component = Livewire::actingAs($this->applicant())->test(ApplicationForm::class)
            ->set('form.title', 'Projek Ujian CSV')
            ->set('form.ceiling_cost', $ceilingCost)
            ->set('form.estimated_cost', 1000)
            ->set('currentStep', 3);

        if ($details !== null) {
            $component->set('form.details', $details);
        }

        return $component;
    }

    private function existingItems(): array
    {
        return [[
            'project_category_id' => $this->masterId(ProjectCategory::class, 'peningkatan_sistem'),
            'items' => [
                ['technical_specifications' => 'Item manual A', 'unit_quantity' => 1, 'unit_cost' => 100],
                ['technical_specifications' => 'Item manual B', 'unit_quantity' => 2, 'unit_cost' => 50],
            ],
        ]];
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('perincian.csv', $content);
    }

    private function applicant(string $agencyCode = 'JKR'): User
    {
        $agency = Agency::firstOrCreate(['code' => $agencyCode], ['name' => "Agensi {$agencyCode}", 'category' => 'SUK', 'is_active' => true]);
        $user = User::factory()->create(['agency_id' => $agency->id]);
        $user->assignRole(Role::findOrCreate('pengguna_biasa'));

        return $user;
    }
}
