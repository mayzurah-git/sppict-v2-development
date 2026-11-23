<?php

namespace Tests\Feature;

use App\Livewire\MasterData\ApplicationCategoryManagement;
use App\Livewire\MasterData\ProcurementMethodManagement;
use App\Livewire\MasterData\ProcurementTypeManagement;
use App\Livewire\MasterData\ProjectCategoryManagement;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationDetail;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use Database\Seeders\ApplicationMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicationMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $urusSetia;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'pentadbir_urus_setia']);
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'pengguna_biasa']);

        $this->urusSetia = User::factory()->create();
        $this->urusSetia->assignRole('pentadbir_urus_setia');
    }

    public static function routeProvider(): array
    {
        return [
            'kategori permohonan' => ['master-data.application-categories'],
            'kategori projek' => ['master-data.project-categories'],
            'jenis perolehan' => ['master-data.procurement-types'],
            'kaedah perolehan' => ['master-data.procurement-methods'],
        ];
    }

    public static function componentProvider(): array
    {
        return [
            'kategori permohonan' => [ApplicationCategoryManagement::class, ApplicationCategory::class],
            'kategori projek' => [ProjectCategoryManagement::class, ProjectCategory::class],
            'jenis perolehan' => [ProcurementTypeManagement::class, ProcurementType::class],
            'kaedah perolehan' => [ProcurementMethodManagement::class, ProcurementMethod::class],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_pages_are_accessible_to_urus_setia_only(string $route): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $pengguna = User::factory()->create();
        $pengguna->assignRole('pengguna_biasa');

        $this->actingAs($this->urusSetia)->get(route($route))->assertOk();
        $this->actingAs($superadmin)->get(route($route))->assertForbidden();
        $this->actingAs($pengguna)->get(route($route))->assertForbidden();
    }

    public function test_navigation_shows_master_data_menu_to_urus_setia_only(): void
    {
        $this->actingAs($this->urusSetia)->get(route('dashboard'))
            ->assertSee(route('master-data.project-categories'), false);

        $pengguna = User::factory()->create();
        $pengguna->assignRole('pengguna_biasa');

        $this->actingAs($pengguna)->get(route('dashboard'))
            ->assertDontSee(route('master-data.project-categories'), false);
    }

    #[DataProvider('componentProvider')]
    public function test_non_urus_setia_cannot_use_component(string $component): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        Livewire::actingAs($superadmin)->test($component)->assertForbidden();
    }

    #[DataProvider('componentProvider')]
    public function test_record_can_be_created_updated_and_toggled(string $component, string $model): void
    {
        Livewire::actingAs($this->urusSetia)
            ->test($component)
            ->call('openModal')
            ->assertSet('sort_order', 1)
            ->set('name', '  Contoh Baharu / Ujian  ')
            ->call('save')
            ->assertHasNoErrors();

        // Kod dijana daripada nama jika dibiarkan kosong
        $record = $model::where('code', 'contoh_baharu_ujian')->firstOrFail();
        $this->assertSame('Contoh Baharu / Ujian', $record->name);
        $this->assertTrue($record->is_active);

        Livewire::actingAs($this->urusSetia)
            ->test($component)
            ->call('openModal', $record->uuid)
            ->assertSet('code', 'contoh_baharu_ujian')
            ->set('name', 'Contoh Dikemaskini')
            ->set('code', 'contoh_dikemaskini')
            ->call('save')
            ->assertHasNoErrors()
            ->call('setStatus', $record->uuid, false);

        $record->refresh();
        $this->assertSame('Contoh Dikemaskini', $record->name);
        $this->assertSame('contoh_dikemaskini', $record->code);
        $this->assertFalse($record->is_active);

        Livewire::actingAs($this->urusSetia)->test($component)->call('setStatus', $record->uuid, true);
        $this->assertTrue($record->fresh()->is_active);
    }

    #[DataProvider('componentProvider')]
    public function test_code_and_name_must_be_unique_and_code_must_be_slug(string $component, string $model): void
    {
        $model::create(['code' => 'sedia_ada', 'name' => 'Sedia Ada', 'sort_order' => 1]);

        Livewire::actingAs($this->urusSetia)
            ->test($component)
            ->call('openModal')
            ->set('code', 'sedia_ada')
            ->set('name', 'Sedia Ada')
            ->call('save')
            ->assertHasErrors(['code' => 'unique', 'name' => 'unique']);

        Livewire::actingAs($this->urusSetia)
            ->test($component)
            ->call('openModal')
            ->set('code', 'Kod Tidak-Sah')
            ->set('name', 'Nama Lain')
            ->call('save')
            ->assertHasErrors(['code' => 'regex']);

        $this->assertSame(1, $model::count());
    }

    public function test_changes_are_recorded_in_audit_trail(): void
    {
        config(['audit.console' => true]);

        $component = Livewire::actingAs($this->urusSetia)
            ->test(ProjectCategoryManagement::class)
            ->call('openModal')
            ->set('name', 'Kategori Audit')
            ->call('save');

        $record = ProjectCategory::where('code', 'kategori_audit')->firstOrFail();

        $component->call('openModal', $record->uuid)
            ->set('name', 'Kategori Audit Dikemaskini')
            ->call('save')
            ->call('setStatus', $record->uuid, false);

        $audits = $record->audits()->oldest('id')->get();

        $this->assertSame(['created', 'updated', 'updated'], $audits->pluck('event')->all());
        $this->assertSame([$this->urusSetia->id], $audits->pluck('user_id')->unique()->values()->all());
        $this->assertSame(['name' => 'Kategori Audit Dikemaskini'], $audits[1]->new_values);
        $this->assertFalse((bool) $audits[2]->new_values['is_active']);
    }

    public function test_search_status_filter_and_pagination(): void
    {
        foreach (range(1, 12) as $i) {
            ProcurementMethod::create(['code' => "kaedah_{$i}", 'name' => "Kaedah {$i}", 'sort_order' => $i, 'is_active' => $i !== 12]);
        }

        Livewire::actingAs($this->urusSetia)
            ->test(ProcurementMethodManagement::class)
            ->assertSee('Kaedah 10')
            ->assertDontSee('Kaedah 11')
            ->set('search', 'Kaedah 11')
            ->assertSee('Kaedah 11')
            ->assertDontSee('Kaedah 10')
            ->set('search', '')
            ->set('statusFilter', 'inactive')
            ->assertSee('Kaedah 12')
            ->assertDontSee('Kaedah 1<', false)
            ->set('statusFilter', 'active')
            ->assertDontSee('Kaedah 12');
    }

    public function test_project_category_sort_order_must_be_unique_within_prefix_range(): void
    {
        $this->seed(ApplicationMasterDataSeeder::class);

        Livewire::actingAs($this->urusSetia)
            ->test(ProjectCategoryManagement::class)
            ->call('openModal')
            ->assertSet('sort_order', 7)
            ->set('name', 'Kategori Ujian')
            ->set('sort_order', 1)
            ->call('save')
            ->assertHasErrors(['sort_order' => 'unique'])
            ->set('sort_order', 27)
            ->call('save')
            ->assertHasErrors(['sort_order' => 'max'])
            ->set('sort_order', 7)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('G. KATEGORI UJIAN', ProjectCategory::where('code', 'kategori_ujian')->first()->label());
    }

    public function test_record_in_use_cannot_be_deleted_or_change_code(): void
    {
        $this->seed(ApplicationMasterDataSeeder::class);

        // Rekod lama hanya mempunyai snapshot teks (FK belum diisi) dan telah di-soft delete
        $application = $this->makeApplication([
            'application_category' => 'pembangunan_sistem',
            'procurement_type' => 'Penyelenggaraan',
            'procurement_method' => 'Sebut Harga',
        ]);
        ApplicationDetail::create([
            'application_id' => $application->id,
            'item_category' => 'Projek Baharu',
            'technical_specifications' => 'Pelayan',
            'unit_quantity' => 1,
            'unit_cost' => 100,
            'total_cost' => 100,
        ])->delete();

        $inUse = [
            [ApplicationCategoryManagement::class, ApplicationCategory::where('code', 'pembangunan_sistem')->first()],
            [ProjectCategoryManagement::class, ProjectCategory::where('code', 'projek_baharu')->first()],
            [ProcurementTypeManagement::class, ProcurementType::where('code', 'penyelenggaraan')->first()],
            [ProcurementMethodManagement::class, ProcurementMethod::where('code', 'sebut_harga')->first()],
        ];

        foreach ($inUse as [$component, $record]) {
            Livewire::actingAs($this->urusSetia)
                ->test($component)
                ->call('delete', $record->uuid)
                ->call('openModal', $record->uuid)
                ->assertSet('codeLocked', true)
                ->set('code', 'kod_baharu')
                ->call('save')
                ->assertHasErrors('code');

            $this->assertNotSoftDeleted($record);
            $this->assertSame($record->code, $record->fresh()->code, $component);
        }

        // "Penyelenggaraan" Jenis Perolehan digunakan, tetapi Kategori Projek berlainan nama tidak terjejas
        $unused = ProjectCategory::where('code', 'penyelenggaraan_sewaan_langganan')->first();
        $this->assertFalse($unused->isInUse());

        Livewire::actingAs($this->urusSetia)
            ->test(ProjectCategoryManagement::class)
            ->call('delete', $unused->uuid);

        $this->assertSoftDeleted($unused);
    }

    public function test_record_linked_by_foreign_key_is_in_use(): void
    {
        $this->seed(ApplicationMasterDataSeeder::class);

        $type = ProcurementType::where('code', 'pembangunan_aplikasi')->first();
        $this->assertFalse($type->isInUse());

        $this->makeApplication(['procurement_type' => 'Teks Lain', 'procurement_type_id' => $type->id]);

        $this->assertTrue($type->isInUse());
    }

    private function makeApplication(array $attributes): Application
    {
        $agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $applicant = User::factory()->create(['agency_id' => $agency->id]);

        return Application::create($attributes + [
            'reference_number' => 'SPPICT/2026/M0001',
            'agency_id' => $agency->id,
            'applicant_id' => $applicant->id,
            'title' => 'Permohonan Ujian',
            'estimated_cost' => 10000,
            'status' => 'SUBMITTED',
        ]);
    }
}
