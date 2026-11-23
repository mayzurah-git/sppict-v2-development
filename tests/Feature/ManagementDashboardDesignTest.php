<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Livewire\Dashboard\ProjectStatistics;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reka bentuk interaktif Papan Pemuka Pengurusan (diluluskan 23/11/2026) dan skema warna
 * Pengurusan "Arang & Emas" (pilihan A).
 */
class ManagementDashboardDesignTest extends TestCase
{
    use RefreshDatabase;

    private Agency $jkr;

    private Agency $jpn;

    private User $pengurusan;

    private User $urusSetia;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->jkr = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'SUK', 'is_active' => true]);
        $this->jpn = Agency::create(['code' => 'JPN', 'name' => 'Jabatan Pendaftaran Negara', 'category' => 'SUK', 'is_active' => true]);

        $this->pengurusan = User::factory()->create(['agency_id' => $this->jkr->id]);
        $this->pengurusan->assignRole('pengurusan');
        $this->urusSetia = User::factory()->create(['agency_id' => $this->jkr->id]);
        $this->urusSetia->assignRole('pentadbir_urus_setia');
    }

    private function approved(Agency $agency, string $year, float $total, ?string $title = null): Application
    {
        $this->seq++;
        $application = Application::create([
            'reference_number' => "SPPICT/{$year}/" . str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'agency_id' => $agency->id,
            'applicant_id' => $this->urusSetia->id,
            'title' => $title ?? "Permohonan {$this->seq}",
            'estimated_cost' => $total,
            'total_cost' => $total,
            'status' => 'CLOSED',
        ]);

        foreach ([[Decision::Accepted, 'COMPLETE'], [Decision::Approved, 'IN_JTICT']] as [$decision, $from]) {
            ApplicationDecision::create([
                'application_id' => $application->id, 'stage' => $decision->stage(), 'decision' => $decision,
                'from_status' => $from, 'to_status' => $decision->targetStatus()->value,
                'remarks' => 'Ujian.', 'decision_date' => today(), 'decided_by' => $this->urusSetia->id,
            ]);
        }

        return $application;
    }

    private function project(Agency $agency, float $actual, float $planned, string $title): Project
    {
        $application = $this->approved($agency, '2026', 0, $title);

        return Project::forceCreate([
            'application_id' => $application->id, 'agency_id' => $agency->id, 'status' => 'IN_PROGRESS',
            'planned_start_date' => '2026-01-01', 'planned_end_date' => '2026-12-31',
            'actual_progress_percent' => $actual, 'planned_progress_percent' => $planned, 'variance_percent' => $actual - $planned,
        ]);
    }

    public function test_dashboard_uses_charcoal_and_gold_theme_and_shows_kpis(): void
    {
        $this->approved($this->jkr, '2025', 1_500_000);
        $this->approved($this->jpn, '2026', 500_000);

        $this->actingAs($this->pengurusan)->get('/dashboard')
            ->assertOk()
            ->assertSee('bg-slate-800', false)
            ->assertSee('bg-slate-100/70', false)
            ->assertSee('Gambaran Keseluruhan Projek ICT')
            ->assertSeeInOrder(['Permohonan diterima', 'Lulus JTICT', 'Kos diluluskan', 'RM 2.00 juta', 'Projek aktif'])
            ->assertSee('Tiada Mesyuarat JTICT dibuka');

        // Urus Setia kekal hijau.
        $this->actingAs($this->urusSetia)->get('/dashboard')->assertOk()->assertDontSee('bg-slate-800', false);
    }

    public function test_year_measure_lamp_sort_and_breakdown_controls(): void
    {
        $this->approved($this->jkr, '2025', 300_000);
        $this->approved($this->jpn, '2025', 100_000);
        $this->approved($this->jpn, '2025', 100_000);
        $this->project($this->jkr, 40, 50, 'Projek Kuning');   // -10: Kuning
        $this->project($this->jpn, 20, 60, 'Projek Merah');    // -40: Merah

        $component = Livewire::actingAs($this->pengurusan)->test(ProjectStatistics::class, ['full' => true])
            ->call('setYear', '2025')
            ->assertSet('year', '2025')
            ->assertSee('RM 300,000.00');

        // Ukuran: Bilangan menyusun JPN (2 projek) di hadapan JKR.
        $component->call('setMeasure', 'count')
            ->assertSeeInOrder(['2 projek', '1 projek']);

        // Lampu: tapis senarai lewat; klik semula untuk kosongkan.
        $component->call('setLamp', 'RED')
            ->assertSee('Projek Merah')->assertDontSee('Projek Kuning')
            ->call('setLamp', 'RED')
            ->assertSet('lamp', '')
            ->assertSee('Projek Kuning');
        $component->call('setLamp', 'GREEN')->assertSet('lamp', '');

        // Susunan jadual agensi.
        $component->call('sortBy', 'label')->assertSet('sort', 'label')->assertSet('direction', 'asc')
            ->call('sortBy', 'label')->assertSet('direction', 'desc')
            ->call('sortBy', 'bukan')->assertSet('sort', 'label');

        // Pecahan butiran permohonan.
        $component->call('setBreakdown', 'category')->assertSee('Mengikut Kategori Permohonan')->assertSet('breakdown', 'category')
            ->call('setYear', 'bukan')->assertSet('year', '');
    }
}
