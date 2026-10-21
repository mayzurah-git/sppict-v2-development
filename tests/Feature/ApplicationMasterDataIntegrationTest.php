<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use App\Livewire\ApplicationList;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationDetail;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsesApplicationMasterData;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa U5: borang Permohonan Projek menggunakan master data (bukan hard code).
 */
class ApplicationMasterDataIntegrationTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->applicant = User::factory()->create(['agency_id' => $agency->id]);
        $this->applicant->assignRole(Role::findOrCreate('pengguna_biasa')); // UM7A: hanya pengguna_biasa/urus setia boleh mendaftar permohonan
    }

    // --- DROPDOWN & NILAI LALAI ---

    public function test_dropdowns_list_only_active_master_data_in_sort_order(): void
    {
        ProcurementMethod::where('code', 'tender_terbuka')->update(['is_active' => false]);
        ApplicationCategory::where('code', 'kajian_perunding')->first()->delete();
        ProjectCategory::create(['code' => 'kategori_ujian', 'name' => 'Kategori Ujian', 'sort_order' => 7]);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class);
        $form = $component->instance();

        $this->assertSame(['sebut_harga', 'rundingan_terus'], $form->procurementMethodOptions->pluck('code')->all());
        $this->assertNotContains('kajian_perunding', $form->applicationCategoryOptions->pluck('code')->all());
        $this->assertSame(
            ['A. PROJEK BAHARU', 'B. PENINGKATAN SISTEM', 'C. PELUASAN SISTEM / PROJEK', 'D. PENAMBAHBAIKAN PERALATAN',
                'E. PENYELENGGARAAN / SEWAAN / LANGGANAN', 'F. KHIDMAT PERUNDING ICT', 'G. KATEGORI UJIAN'],
            $form->projectCategoryOptions->map->label()->all(),
        );

        $component->assertSee('Pembangunan Sistem / Aplikasi')->assertDontSee('Kajian/Khidmat Perunding ICT');

        $component->set('currentStep', 2)
            ->assertSee('Rundingan Terus')
            ->assertDontSee('Tender Terbuka');

        $component->set('currentStep', 3)->assertSeeInOrder(['A. PROJEK BAHARU', 'F. KHIDMAT PERUNDING ICT', 'G. KATEGORI UJIAN']);
    }

    public function test_new_application_defaults_to_first_active_master_by_sort_order(): void
    {
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->assertSet('form.procurement_type_id', $this->masterId(ProcurementType::class, 'pembekalan_perkhidmatan_one_off'))
            ->assertSet('form.procurement_method_id', $this->masterId(ProcurementMethod::class, 'sebut_harga'))
            ->assertSet('form.details.0.project_category_id', $this->masterId(ProjectCategory::class, 'projek_baharu'));

        // Default mengikut master data semasa, bukan nilai tetap
        ProcurementType::where('code', 'pembekalan_perkhidmatan_one_off')->update(['is_active' => false]);
        ProcurementMethod::where('code', 'rundingan_terus')->update(['sort_order' => 0]);
        ProjectCategory::where('code', 'projek_baharu')->update(['is_active' => false]);

        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->assertSet('form.procurement_type_id', $this->masterId(ProcurementType::class, 'pembekalan_perkhidmatan_bermasa'))
            ->assertSet('form.procurement_method_id', $this->masterId(ProcurementMethod::class, 'rundingan_terus'))
            ->assertSet('form.details.0.project_category_id', $this->masterId(ProjectCategory::class, 'peningkatan_sistem'))
            ->call('addCategoryGroup')
            ->assertSet('form.details.1.project_category_id', $this->masterId(ProjectCategory::class, 'peningkatan_sistem'));
    }

    // --- CREATE / UPDATE: FK + SNAPSHOT ---

    public function test_create_saves_foreign_keys_and_snapshots_without_mixing_penyelenggaraan_contexts(): void
    {
        $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class), [
            'application_category' => 'penyelenggaraan_sewaan_langganan',
            'procurement_type' => 'penyelenggaraan',
            'procurement_method' => 'rundingan_terus',
            'project_categories' => ['penyelenggaraan_sewaan_langganan', 'projek_baharu'],
        ])->call('saveDraft')->assertHasNoErrors();

        $application = Application::sole();

        // Kategori Permohonan disimpan sebagai code
        $this->assertSame('penyelenggaraan_sewaan_langganan', $application->getRawOriginal('application_category'));

        // Jenis Perolehan: "Penyelenggaraan" → procurement_types.penyelenggaraan
        $this->assertSame($this->masterId(ProcurementType::class, 'penyelenggaraan'), $application->procurement_type_id);
        $this->assertSame('Penyelenggaraan', $application->procurement_type);
        $this->assertSame($this->masterId(ProcurementMethod::class, 'rundingan_terus'), $application->procurement_method_id);
        $this->assertSame('Rundingan Terus', $application->procurement_method);

        // Kategori Projek: project_categories.penyelenggaraan_sewaan_langganan
        $details = $application->details()->orderBy('id')->get();
        $this->assertSame($this->masterId(ProjectCategory::class, 'penyelenggaraan_sewaan_langganan'), $details[0]->project_category_id);
        $this->assertSame('Penyelenggaraan / Sewaan / Langganan', $details[0]->item_category);
        $this->assertSame($this->masterId(ProjectCategory::class, 'projek_baharu'), $details[1]->project_category_id);
        $this->assertSame('Projek Baharu', $details[1]->item_category);
    }

    public function test_update_saves_new_foreign_keys_and_current_master_names(): void
    {
        $application = $this->createDraft();

        // Nama master ditukar: snapshot mengambil nama semasa ketika disimpan
        ProcurementMethod::where('code', 'tender_terbuka')->update(['name' => 'Tender Terbuka (Dikemaskini)']);

        $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]), [
            'procurement_type' => 'pembangunan_aplikasi',
            'procurement_method' => 'tender_terbuka',
            'project_categories' => ['khidmat_perunding_ict'],
        ])->call('saveDraft')->assertHasNoErrors();

        $application->refresh();
        $this->assertSame($this->masterId(ProcurementType::class, 'pembangunan_aplikasi'), $application->procurement_type_id);
        $this->assertSame('Pembangunan Aplikasi', $application->procurement_type);
        $this->assertSame('Tender Terbuka (Dikemaskini)', $application->procurement_method);

        $detail = $application->details()->sole();
        $this->assertSame($this->masterId(ProjectCategory::class, 'khidmat_perunding_ict'), $detail->project_category_id);
        $this->assertSame('Khidmat Perunding ICT', $detail->item_category);
    }

    // --- REKOD LAMA DENGAN MASTER TIDAK AKTIF ---

    public function test_editing_record_with_inactive_master_keeps_current_value(): void
    {
        $application = $this->createDraft();

        ApplicationCategory::where('code', 'pembangunan_sistem')->update(['is_active' => false]);
        ProcurementType::where('code', 'penyelenggaraan')->update(['is_active' => false]);
        ProcurementMethod::where('code', 'sebut_harga')->first()->delete();
        ProjectCategory::where('code', 'penyelenggaraan_sewaan_langganan')->update(['is_active' => false]);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->assertSet('form.application_category', 'pembangunan_sistem')
            ->assertSet('form.procurement_type_id', $this->masterId(ProcurementType::class, 'penyelenggaraan'))
            ->assertSet('form.procurement_method_id', $this->masterId(ProcurementMethod::class, 'sebut_harga'))
            ->assertSet('form.details.0.project_category_id', $this->masterId(ProjectCategory::class, 'penyelenggaraan_sewaan_langganan'))
            ->assertSee('Pembangunan Sistem / Aplikasi (Tidak Aktif)');

        // Nilai semasa kekal sah untuk rekod ini
        foreach ([1, 2, 3] as $step) {
            $component->set('currentStep', $step)->call('nextStep')->assertHasNoErrors();
        }

        $component->set('currentStep', 2)
            ->assertSee('Penyelenggaraan (Tidak Aktif)')
            ->assertSee('Sebut Harga (Tidak Aktif)');

        // Kumpulan baharu tidak menawarkan kategori tidak aktif
        $component->set('currentStep', 3)
            ->call('addCategoryGroup')
            ->assertSet('form.details.1.project_category_id', $this->masterId(ProjectCategory::class, 'projek_baharu'))
            ->assertSeeHtmlInOrder(['E. PENYELENGGARAAN / SEWAAN / LANGGANAN (Tidak Aktif)', 'Kategori Projek #2']);

        $this->assertSame(1, substr_count($component->html(), 'E. PENYELENGGARAAN / SEWAAN / LANGGANAN (Tidak Aktif)'));

        $component->set('form.details.1.items.0.technical_specifications', 'Item kumpulan baharu')
            ->call('saveDraft')
            ->assertHasNoErrors();

        // Snapshot dan FK lama kekal selepas disimpan semula
        $application->refresh();
        $this->assertSame('Sebut Harga', $application->procurement_method);
        $this->assertSame(
            ['Penyelenggaraan / Sewaan / Langganan', 'Projek Baharu'],
            $application->details()->orderBy('id')->pluck('item_category')->unique()->values()->all(),
        );
    }

    public function test_inactive_master_cannot_be_chosen_for_a_new_record(): void
    {
        ApplicationCategory::where('code', 'kajian_perunding')->update(['is_active' => false]);
        ProcurementType::where('code', 'penyelenggaraan')->update(['is_active' => false]);
        ProjectCategory::where('code', 'peningkatan_sistem')->update(['is_active' => false]);

        $component = $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class), [
            'application_category' => 'kajian_perunding',
            'procurement_type' => 'penyelenggaraan',
            'project_categories' => ['peningkatan_sistem'],
        ]);

        $component->set('currentStep', 1)->call('nextStep')->assertHasErrors(['form.application_category' => 'exists']);
        $component->set('currentStep', 2)->call('nextStep')->assertHasErrors(['form.procurement_type_id' => 'exists']);
        $component->set('currentStep', 3)->call('nextStep')->assertHasErrors(['form.details.0.project_category_id' => 'exists']);
    }

    public function test_inactive_master_of_another_record_cannot_be_reused(): void
    {
        $this->createDraft(); // menggunakan penyelenggaraan (Jenis Perolehan)
        ProcurementType::where('code', 'penyelenggaraan')->update(['is_active' => false]);

        $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class), ['procurement_type' => 'penyelenggaraan'])
            ->set('currentStep', 2)
            ->call('nextStep')
            ->assertHasErrors(['form.procurement_type_id' => 'exists']);
    }

    // --- ID TIDAK SAH & TIADA FALLBACK HARD CODE ---

    public function test_invalid_master_values_are_rejected(): void
    {
        $component = $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class))
            ->set('form.application_category', 'kod_tidak_wujud')
            ->set('form.procurement_type_id', 99999)
            ->set('form.procurement_method_id', 'bukan-nombor')
            ->set('form.details.0.project_category_id', 99999);

        $component->set('currentStep', 1)->call('nextStep')->assertHasErrors(['form.application_category' => 'exists']);
        $component->set('currentStep', 2)->call('nextStep')->assertHasErrors([
            'form.procurement_type_id' => 'exists',
            'form.procurement_method_id' => 'integer',
        ]);
        $component->set('currentStep', 3)->call('nextStep')->assertHasErrors(['form.details.0.project_category_id' => 'exists']);
    }

    public function test_draft_with_invalid_or_missing_master_is_not_saved_with_a_fallback(): void
    {
        $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class))
            ->set('form.procurement_type_id', 99999)
            ->call('saveDraft');

        $this->assertSame(0, Application::count());

        $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class))
            ->set('form.details.0.project_category_id', '')
            ->call('saveDraft');

        $this->assertSame(0, Application::count());
        $this->assertSame(0, ApplicationDetail::withTrashed()->count());

        // Tiada master aktif: tiada nilai lalai dicipta secara hard code
        ProjectCategory::query()->update(['is_active' => false]);
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->assertSet('form.details.0.project_category_id', '');
    }

    // --- PAPARAN ---

    public function test_display_uses_relationship_and_falls_back_to_snapshot(): void
    {
        $application = $this->createDraft();

        // Relationship menjadi sumber utama: nama master semasa dipaparkan
        ProcurementType::where('code', 'penyelenggaraan')->update(['name' => 'Penyelenggaraan ICT']);

        $this->actingAs($this->applicant)->get(route('application.view', $application->uuid))
            ->assertOk()
            ->assertSee('Pembangunan Sistem / Aplikasi')
            ->assertSee('Penyelenggaraan ICT')
            ->assertSee('Sebut Harga')
            ->assertSee('Penyelenggaraan / Sewaan / Langganan');

        // Tiada rekod master: snapshot (atau code bagi Kategori Permohonan) dipaparkan
        $application->forceFill([
            'application_category' => 'kod_lama',
            'procurement_type_id' => null,
            'procurement_type' => 'Jenis Lama',
            'procurement_method_id' => null,
            'procurement_method' => 'Kaedah Lama',
        ])->save();
        $application->details()->update(['project_category_id' => null, 'item_category' => 'Kategori Lama']);

        $this->actingAs($this->applicant)->get(route('application.view', $application->uuid))
            ->assertOk()
            ->assertSee('kod_lama')
            ->assertSee('Jenis Lama')
            ->assertSee('Kaedah Lama')
            ->assertSee('Kategori Lama');

        Livewire::actingAs($this->applicant)->test(ApplicationList::class)->assertSee('kod_lama');
    }

    public function test_application_list_shows_master_name(): void
    {
        $this->createDraft();

        Livewire::actingAs($this->applicant)->test(ApplicationList::class)
            ->assertSee('Pembangunan Sistem / Aplikasi')
            ->assertDontSee('pembangunan_sistem');
    }

    public function test_other_users_still_cannot_edit_the_application(): void
    {
        $application = $this->createDraft();
        $other = User::factory()->create(['agency_id' => $this->applicant->agency_id]);

        Livewire::actingAs($other)->test(ApplicationForm::class, ['uuid' => $application->uuid])->assertForbidden();
    }

    // --- PEMBANTU ---

    private function createDraft(): Application
    {
        $this->fillForm(Livewire::actingAs($this->applicant)->test(ApplicationForm::class), [
            'application_category' => 'pembangunan_sistem',
            'procurement_type' => 'penyelenggaraan',
            'procurement_method' => 'sebut_harga',
            'project_categories' => ['penyelenggaraan_sewaan_langganan'],
        ])->call('saveDraft')->assertHasNoErrors();

        return Application::latest('id')->firstOrFail();
    }

    /**
     * Isi borang dengan code master; ID dicari daripada pangkalan data.
     */
    private function fillForm($component, array $codes = [])
    {
        $categories = $codes['project_categories'] ?? ['projek_baharu'];

        return $component
            ->set('form.title', 'Projek Ujian Master Data')
            ->set('form.application_category', $codes['application_category'] ?? 'pembangunan_sistem')
            ->set('form.objectives', 'Objektif projek ujian yang lengkap.')
            ->set('form.project_scope', 'Skop projek ujian yang lengkap.')
            ->set('form.procurement_type_id', $this->masterId(ProcurementType::class, $codes['procurement_type'] ?? 'pembekalan_perkhidmatan_one_off'))
            ->set('form.procurement_method_id', $this->masterId(ProcurementMethod::class, $codes['procurement_method'] ?? 'sebut_harga'))
            ->set('form.ceiling_cost', 50000)
            ->set('form.estimated_cost', 20000)
            ->set('form.expected_duration_months', 12)
            ->set('form.outcome_code', 'Bajet Mengurus')
            ->set('form.details', array_map(fn ($code) => [
                'project_category_id' => $this->masterId(ProjectCategory::class, $code),
                'items' => [['technical_specifications' => "Item {$code}", 'unit_quantity' => 1, 'unit_cost' => 1000]],
            ], $categories));
    }
}
