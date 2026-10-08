<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Database\Seeders\ApplicationMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Fasa U4: migration 2026_10_07_000009 memetakan data sedia ada kepada master data.
 */
class ApplicationMasterDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApplicationMasterDataSeeder::class);
    }

    public function test_existing_records_including_soft_deleted_are_mapped_by_table_and_column(): void
    {
        $maintenance = $this->insertApplication(['procurement_type' => 'Penyelenggaraan', 'procurement_method' => 'Rundingan Terus']);
        $trashed = $this->insertApplication([
            'procurement_type' => 'Pembekalan / Perkhidmatan Bermasa',
            'procurement_method' => 'Tender Terbuka',
            'deleted_at' => now(),
        ]);
        $draft = $this->insertApplication(['procurement_type' => null, 'procurement_method' => null, 'status' => 'DRAFT']);

        $detailMaintenance = $this->insertDetail($maintenance, 'Penyelenggaraan');
        $detailExpansion = $this->insertDetail($maintenance, 'Peluasan Sistem/Projek', trashed: true);
        $detailNew = $this->insertDetail($trashed, 'Projek Baharu', trashed: true);

        $auditsBefore = DB::table('audits')->count();
        $updatedAtBefore = DB::table('applications')->where('id', $maintenance)->value('updated_at');

        $this->runMigration();

        // Konteks Jenis Perolehan: snapshot kekal "Penyelenggaraan", FK → penyelenggaraan
        $application = DB::table('applications')->where('id', $maintenance)->first();
        $this->assertSame('Penyelenggaraan', $application->procurement_type);
        $this->assertSame($this->masterId('procurement_types', 'penyelenggaraan'), (int) $application->procurement_type_id);
        $this->assertSame($this->masterId('procurement_methods', 'rundingan_terus'), (int) $application->procurement_method_id);
        $this->assertEquals($updatedAtBefore, $application->updated_at);

        // Konteks Kategori Projek: snapshot dilabel semula, FK → penyelenggaraan_sewaan_langganan
        $detail = DB::table('application_details')->where('id', $detailMaintenance)->first();
        $this->assertSame('Penyelenggaraan / Sewaan / Langganan', $detail->item_category);
        $this->assertSame($this->masterId('project_categories', 'penyelenggaraan_sewaan_langganan'), (int) $detail->project_category_id);

        // Rekod soft-deleted turut dipetakan dan kekal soft-deleted
        $detail = DB::table('application_details')->where('id', $detailExpansion)->first();
        $this->assertSame('Peluasan Sistem / Projek', $detail->item_category);
        $this->assertSame($this->masterId('project_categories', 'peluasan_sistem_projek'), (int) $detail->project_category_id);
        $this->assertNotNull($detail->deleted_at);

        $this->assertSame($this->masterId('project_categories', 'projek_baharu'), (int) DB::table('application_details')->where('id', $detailNew)->value('project_category_id'));

        $application = DB::table('applications')->where('id', $trashed)->first();
        $this->assertSame($this->masterId('procurement_types', 'pembekalan_perkhidmatan_bermasa'), (int) $application->procurement_type_id);
        $this->assertNotNull($application->deleted_at);

        // Draf tanpa nilai dibiarkan NULL
        $application = DB::table('applications')->where('id', $draft)->first();
        $this->assertNull($application->procurement_type_id);
        $this->assertNull($application->procurement_method_id);

        $this->assertSame(3, DB::table('applications')->count());
        $this->assertSame(3, DB::table('application_details')->count());
        $this->assertSame($auditsBefore, DB::table('audits')->count());
    }

    public function test_unmatched_value_stops_migration_without_partial_changes(): void
    {
        $application = $this->insertApplication(['procurement_type' => 'Pembangunan Aplikasi', 'procurement_method' => 'Kaedah Tidak Wujud']);
        $this->insertDetail($application, 'Penyelenggaraan');

        try {
            $this->runMigration();
            $this->fail('Migration sepatutnya gagal kerana nilai tidak sepadan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("'Kaedah Tidak Wujud' (1 rekod)", $exception->getMessage());
        }

        $this->assertNull(DB::table('applications')->where('id', $application)->value('procurement_type_id'));
        $this->assertSame('Penyelenggaraan', DB::table('application_details')->value('item_category'));
        $this->assertNull(DB::table('application_details')->value('project_category_id'));
    }

    public function test_missing_master_data_stops_migration_and_is_not_created(): void
    {
        $application = $this->insertApplication(['procurement_type' => 'Pembangunan Aplikasi', 'procurement_method' => 'Sebut Harga']);
        DB::table('procurement_methods')->where('code', 'sebut_harga')->delete();

        $this->expectExceptionMessage("procurement_methods dengan code 'sebut_harga'");

        try {
            $this->runMigration();
        } finally {
            $this->assertSame(2, DB::table('procurement_methods')->count());
            $this->assertNull(DB::table('applications')->where('id', $application)->value('procurement_type_id'));
        }
    }

    public function test_migration_is_idempotent_and_maps_soft_deleted_master_data(): void
    {
        DB::table('project_categories')->where('code', 'khidmat_perunding_ict')->update(['deleted_at' => now(), 'is_active' => false]);

        $application = $this->insertApplication(['procurement_type' => 'Penyelenggaraan', 'procurement_method' => 'Sebut Harga']);
        $detail = $this->insertDetail($application, 'Khidmat Perunding ICT');

        $this->runMigration();
        $this->runMigration();

        $this->assertSame($this->masterId('project_categories', 'khidmat_perunding_ict'), (int) DB::table('application_details')->where('id', $detail)->value('project_category_id'));
        $this->assertSame(6, DB::table('project_categories')->count());
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_07_000009_map_existing_application_data_to_master_data.php');
        $migration->up();
    }

    private function masterId(string $table, string $code): int
    {
        return (int) DB::table($table)->where('code', $code)->value('id');
    }

    private function insertApplication(array $attributes): int
    {
        $this->sequence++;
        $agency = Agency::firstOrCreate(['code' => 'JKR'], ['name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $applicant = User::factory()->create(['agency_id' => $agency->id]);

        return DB::table('applications')->insertGetId($attributes + [
            'uuid' => (string) Str::uuid(),
            'reference_number' => sprintf('SPPICT/2026/U%04d', $this->sequence),
            'agency_id' => $agency->id,
            'applicant_id' => $applicant->id,
            'title' => 'Permohonan Ujian',
            'estimated_cost' => 10000,
            'status' => 'SUBMITTED',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }

    private function insertDetail(int $applicationId, string $category, bool $trashed = false): int
    {
        return DB::table('application_details')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'application_id' => $applicationId,
            'item_category' => $category,
            'technical_specifications' => 'Item ujian',
            'unit_quantity' => 1,
            'unit_cost' => 100,
            'total_cost' => 100,
            'deleted_at' => $trashed ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
