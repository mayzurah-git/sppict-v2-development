<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\DecisionStage;
use App\Exports\ProjectListExport;
use App\Exports\ProjectSummaryExport;
use App\Livewire\Dashboard\ProjectStatistics;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationDecision;
use App\Models\Project;
use App\Models\User;
use App\Services\DashboardStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * SP5: papan pemuka Pengurusan / Urus Setia dan eksport A & C (keputusan pemilik 23/11/2026, audit SP0 L.9).
 */
class ProjectDashboardTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private Agency $jkr;

    private Agency $jpn;

    private User $pengurusan;

    private User $urusSetia;

    private User $pengguna;

    private User $superadmin;

    private array $categories;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->jkr = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'SUK', 'is_active' => true]);
        $this->jpn = Agency::create(['code' => 'JPN', 'name' => 'Jabatan Pendaftaran Negara', 'category' => 'SUK', 'is_active' => true]);

        $this->pengurusan = $this->user('pengurusan');
        $this->urusSetia = $this->user('pentadbir_urus_setia');
        $this->pengguna = $this->user('pengguna_biasa');
        $this->superadmin = $this->user('superadmin');

        $this->categories = ApplicationCategory::orderBy('id')->limit(2)->pluck('name', 'code')->all();
    }

    // --- (a) STATISTIK PERMOHONAN ---

    public function test_application_statistics_by_year_agency_and_category(): void
    {
        [$catA, $catB] = array_keys($this->categories);

        $this->application('SPPICT/2025/0001', $this->jkr, $catA, 'CLOSED', Decision::Approved, 100000);
        $this->application('SPPICT/2026/0001', $this->jkr, $catA, 'CLOSED', Decision::NotApproved);
        $this->application('SPPICT/2026/0002', $this->jpn, $catB, 'CLOSED', Decision::Review);
        $this->application('SPPICT/2026/0003', $this->jpn, $catB, 'CLOSED', Decision::Approved, 250000.50);
        $this->application('SPPICT/2026/0004', $this->jpn, null, 'CLOSED', Decision::Rejected);   // Ditolak Pra JTICT
        $this->application('SPPICT/2026/0005', $this->jkr, $catA, 'SUBMITTED');                  // Dalam Proses
        $this->application('SPPICT/2026/DRAFT-0001', $this->jkr, $catA, 'DRAFT');                // Draf: tidak dikira
        $this->application('SPPICT/2026/0006', $this->jkr, $catA, 'SUBMITTED')->delete();         // Dipadam: tidak dikira

        $stats = new DashboardStatisticsService();

        $byYear = $stats->applicationsByYear();
        $this->assertSame(['2026', '2025'], $byYear->pluck('label')->values()->all());
        $this->assertSame(['in_process' => 1, 'approved' => 1, 'not_approved' => 1, 'review' => 1, 'rejected' => 1], $byYear[2026]['counts']);
        $this->assertSame(5, $byYear[2026]['total']);
        $this->assertSame(1, $byYear[2025]['counts']['approved']);
        $this->assertSame([2026, 2025], $stats->years());

        $byAgency = $stats->applicationsByAgency(2026)->keyBy('label');
        $this->assertSame(2, $byAgency['JKR']['total']);
        $this->assertSame(3, $byAgency['JPN']['total']);
        $this->assertSame(3, $stats->applicationsByAgency()->keyBy('label')['JKR']['total']); // semua tahun

        $byCategory = $stats->applicationsByCategory(2026)->keyBy('label');
        $this->assertSame(2, $byCategory[$this->categories[$catA]]['total']);
        $this->assertSame(2, $byCategory[$this->categories[$catB]]['total']);
        $this->assertSame(1, $byCategory['Tidak dinyatakan']['total']);

        // (d) Kos projek Lulus JTICT = Jumlah Keseluruhan permohonan
        $cost = $stats->approvedCostByAgency()->keyBy('label');
        $this->assertSame([1, 100000.0], [$cost['JKR']['count'], $cost['JKR']['cost']]);
        $this->assertSame([1, 250000.5], [$cost['JPN']['count'], $cost['JPN']['cost']]);
        $this->assertSame(['JPN'], $stats->approvedCostByAgency(2026)->pluck('label')->all());
    }

    // --- (b), (c), (e) PROJEK ---

    public function test_project_status_agency_summary_and_late_projects(): void
    {
        $this->project($this->jkr, 'IN_PROGRESS', 40, 50, -10);    // Kuning
        $this->project($this->jkr, 'IN_PROGRESS', 20, 60, -40);    // Merah
        $this->project($this->jkr, 'COMPLETED', 100, 100, 0);
        $this->project($this->jpn, 'IN_PROGRESS', 70, 60, 10);     // Hijau
        $this->project($this->jpn, 'PENDING_CLOSURE', 100, 100, 0); // Hijau
        $this->project($this->jpn, 'CLOSED', 30, 80, -50);         // ditutup: bukan lewat aktif
        $this->project($this->jpn, 'IN_PROGRESS', 10, null, null); // tiada data rancangan
        Project::create(['agency_id' => $this->jpn->id, 'source' => 'V1', 'legacy_reference' => 'V1-1', 'status' => 'HISTORICAL']);

        $stats = new DashboardStatisticsService();

        $status = $stats->projectsByStatus();
        $this->assertSame(8, $status['total']);
        $this->assertSame(4, $status['statuses']['IN_PROGRESS']);
        $this->assertSame(1, $status['statuses']['PENDING_CLOSURE']);
        $this->assertSame(1, $status['statuses']['HISTORICAL']);
        $this->assertSame(['GREEN' => 2, 'AMBER' => 1, 'RED' => 1, 'NONE' => 1], $status['indicators']);

        $summary = $stats->projectSummaryByAgency()->keyBy('label');
        $this->assertSame(['total' => 3, 'active' => 2, 'completed' => 1, 'closed' => 0, 'late' => 2, 'average' => 30.0],
            array_intersect_key($summary['JKR'], array_flip(['total', 'active', 'completed', 'closed', 'late', 'average'])));
        $this->assertSame(['total' => 4, 'active' => 3, 'completed' => 0, 'closed' => 1, 'late' => 0, 'average' => 60.0],
            array_intersect_key($summary['JPN'], array_flip(['total', 'active', 'completed', 'closed', 'late', 'average'])));

        $late = $stats->lateProjects();
        $this->assertSame(['-40.00', '-10.00'], $late->pluck('variance_percent')->all());
    }

    // --- PAPAN PEMUKA & AKSES ---

    public function test_dashboards_by_role(): void
    {
        $this->application('SPPICT/2026/0001', $this->jkr, null, 'CLOSED', Decision::Approved, 120000);
        $this->project($this->jkr, 'IN_PROGRESS', 20, 60, -40, 'Projek Lewat Teruk');

        $this->actingAs($this->pengurusan)->get('/dashboard')->assertOk()
            ->assertSee('Papan Pemuka Pengurusan')
            ->assertSee('Statistik Permohonan Projek')
            ->assertSee('Bilangan Projek mengikut Status Kemajuan')
            ->assertSee('Ringkasan Projek mengikut Jabatan/Agensi')
            ->assertSee('Statistik Projek mengikut Kos Jabatan/Agensi')
            ->assertSee('RM 120,000.00')
            ->assertSee('Senarai Projek Lewat (1)')
            ->assertSee('Projek Lewat Teruk');

        $this->actingAs($this->urusSetia)->get('/dashboard')->assertOk()
            ->assertSee('Pemantauan Projek')
            ->assertSee('Senarai Projek Lewat (1)')
            ->assertDontSee('Statistik Permohonan Projek');

        $this->actingAs($this->pengguna)->get('/dashboard')->assertOk()->assertDontSee('Senarai Projek Lewat');

        $this->assertFalse($this->pengguna->can('viewStatistics', Project::class));
        $this->assertFalse($this->superadmin->can('viewStatistics', Project::class));
        Livewire::actingAs($this->pengguna)->test(ProjectStatistics::class)->assertForbidden();
    }

    public function test_year_filter_on_management_statistics(): void
    {
        [$catA] = array_keys($this->categories);
        $this->application('SPPICT/2025/0001', $this->jkr, $catA, 'CLOSED', Decision::Approved, 1000);
        $this->application('SPPICT/2026/0001', $this->jpn, $catA, 'CLOSED', Decision::Approved, 2000);

        Livewire::actingAs($this->pengurusan)->test(ProjectStatistics::class, ['full' => true])
            ->assertSee('RM 3,000.00')
            ->set('year', '2025')
            ->assertSee('RM 1,000.00')
            ->assertDontSee('RM 3,000.00');
    }

    // --- EKSPORT ---

    public function test_project_list_export_follows_screen_scope_and_filters(): void
    {
        Excel::fake();
        $this->project($this->jkr, 'IN_PROGRESS', 40, 50, -10, 'Projek JKR Satu');
        $this->project($this->jkr, 'COMPLETED', 100, 100, 0, 'Projek JKR Dua');
        $this->project($this->jpn, 'IN_PROGRESS', 70, 60, 10, 'Projek JPN');

        $file = 'senarai-projek-' . today()->format('Ymd') . '.xlsx';

        // Pengguna biasa: agensi sendiri sahaja (penapis agensi diabaikan)
        $this->actingAs($this->pengguna)->get(route('projects.export.list', ['agency' => $this->jpn->id]))->assertOk();
        Excel::assertDownloaded($file, function (ProjectListExport $export) {
            $titles = array_column($export->array(), 1);
            sort($titles);

            return $titles === ['Projek JKR Dua', 'Projek JKR Satu'];
        });

        // Pengurusan dengan penapis status
        $this->actingAs($this->pengurusan)->get(route('projects.export.list', ['status' => 'IN_PROGRESS']))->assertOk();
        Excel::assertDownloaded($file, function (ProjectListExport $export) {
            $rows = $export->array();
            $titles = array_column($rows, 1);
            sort($titles);

            return $titles === ['Projek JKR Satu', 'Projek JPN']
                && in_array('Lewat', array_column($rows, 12), true)
                && count($export->headings()) === count($rows[0]);
        });

        $this->actingAs($this->superadmin)->get(route('projects.export.list'))->assertForbidden();
    }

    public function test_progress_summary_export_for_urus_setia_and_pengurusan_only(): void
    {
        $this->project($this->jkr, 'IN_PROGRESS', 0, 0, 0, 'Projek Ringkasan');

        $this->actingAs($this->pengguna)->get(route('projects.summary.pdf'))->assertForbidden();
        $this->actingAs($this->superadmin)->get(route('projects.summary.pdf'))->assertForbidden();
        $this->actingAs($this->pengguna)->get(route('projects.summary.excel'))->assertForbidden();

        $this->actingAs($this->pengurusan)->get(route('projects.summary.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        Excel::fake();
        $this->actingAs($this->urusSetia)->get(route('projects.summary.excel', ['agency' => $this->jkr->id]))->assertOk();
        Excel::assertDownloaded('ringkasan-status-kemajuan-' . today()->format('Ymd') . '.xlsx', function (ProjectSummaryExport $export) {
            $rows = $export->array();

            return count($rows) === 1 && $rows[0][0] === 'JKR' && $rows[0][3] === 'Projek Ringkasan';
        });
    }

    // --- PEMBANTU ---

    private function user(string $role): User
    {
        $user = User::factory()->create(['agency_id' => $this->jkr->id]);
        $user->assignRole($role);

        return $user;
    }

    private function application(string $reference, Agency $agency, ?string $category, string $status, ?Decision $decision = null, float $total = 50000): Application
    {
        $application = Application::create([
            'reference_number' => $reference,
            'agency_id' => $agency->id,
            'applicant_id' => $this->pengguna->id,
            'title' => "Permohonan {$reference}",
            'application_category' => $category,
            'estimated_cost' => $total,
            'total_cost' => $total,
            'status' => $status,
        ]);

        if ($decision) {
            if ($decision->stage() === DecisionStage::Jtict) {
                $this->decide($application, Decision::Accepted, 'COMPLETE');
            }
            $this->decide($application, $decision, $decision->stage() === DecisionStage::Jtict ? 'IN_JTICT' : 'COMPLETE');
        }

        return $application;
    }

    private function decide(Application $application, Decision $decision, string $from): void
    {
        ApplicationDecision::create([
            'application_id' => $application->id,
            'stage' => $decision->stage(),
            'decision' => $decision,
            'from_status' => $from,
            'to_status' => $decision->targetStatus()->value,
            'remarks' => 'Ulasan ujian.',
            'decision_date' => today(),
            'decided_by' => $this->urusSetia->id,
        ]);
    }

    private int $projectSequence = 0;

    private function project(Agency $agency, string $status, float $actual, ?float $planned, ?float $variance, ?string $title = null): Project
    {
        $this->projectSequence++;
        $application = $this->application('SPPICT/2027/' . str_pad((string) $this->projectSequence, 4, '0', STR_PAD_LEFT), $agency, null, 'CLOSED', Decision::Approved, 0);
        $application->update(['title' => $title ?? "Projek {$this->projectSequence}"]);

        return Project::forceCreate([
            'application_id' => $application->id,
            'agency_id' => $agency->id,
            'status' => $status,
            'planned_start_date' => '2027-01-01',
            'planned_end_date' => '2027-12-31',
            'actual_progress_percent' => $actual,
            'planned_progress_percent' => $planned,
            'variance_percent' => $variance,
        ]);
    }
}
