<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\DecisionStage;
use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ProcurementType;
use App\Models\ProgressTemplate;
use App\Models\ProgressTemplateActivity;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\ProgressTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * SP1: struktur pangkalan data dan templat pemberat Modul Status Kemajuan Projek.
 */
class ProgressTemplateTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private Agency $agency;

    private User $user;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProgressTemplateSeeder::class);

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    }

    // --- TEMPLAT ---

    public function test_four_templates_with_31_activities_are_seeded_and_each_totals_100(): void
    {
        $this->assertSame(4, ProgressTemplate::count());
        $this->assertSame(31, ProgressTemplateActivity::count());

        $counts = ['A' => 8, 'B' => 8, 'C' => 5, 'D' => 10];

        foreach (ProgressTemplate::with('activities')->get() as $template) {
            $this->assertSame(1, $template->version);
            $this->assertTrue($template->is_active);
            $this->assertCount($counts[$template->code], $template->activities, "Templat {$template->code}");
            $this->assertSame(100.0, $template->totalWeight(), "Jumlah pemberat Templat {$template->code}");
        }
    }

    public function test_templates_map_to_procurement_types_by_code(): void
    {
        $expected = [
            'pembekalan_perkhidmatan_one_off' => 'A',
            'pembekalan_perkhidmatan_bermasa' => 'B',
            'penyelenggaraan' => 'C',
            'pembangunan_aplikasi' => 'D',
        ];

        foreach ($expected as $procurementCode => $templateCode) {
            $template = ProgressTemplate::activeFor($this->masterId(ProcurementType::class, $procurementCode));
            $this->assertSame($templateCode, $template?->code, $procurementCode);
        }

        $this->assertNull(ProgressTemplate::activeFor(null));
    }

    public function test_mapping_survives_procurement_type_rename(): void
    {
        // Nama master boleh ditukar oleh Urus Setia (I.12); pemetaan kekal melalui kod/FK.
        ProcurementType::where('code', 'pembangunan_aplikasi')->update(['name' => 'Nama Baharu']);

        $this->assertSame('D', ProgressTemplate::activeFor($this->masterId(ProcurementType::class, 'pembangunan_aplikasi'))->code);
    }

    public function test_only_template_d_allows_phases(): void
    {
        $this->assertSame(['D'], ProgressTemplate::where('allows_phases', true)->pluck('code')->all());
        $this->assertSame('Pembangunan Sistem / Aplikasi', ProgressTemplate::where('code', 'D')->value('name'));
    }

    public function test_template_d_activities_match_specification(): void
    {
        $activities = ProgressTemplate::where('code', 'D')->sole()->activities
            ->map(fn ($activity) => [$activity->sequence, $activity->name, (float) $activity->weight, $activity->requires_document])
            ->all();

        $this->assertSame([
            [1, 'Penyediaan Dokumen Tender / Sebut Harga / Kelulusan', 5.0, true],
            [2, 'Proses Perolehan', 10.0, true],
            [3, 'Surat Setuju Terima (SST)', 5.0, true],
            [4, 'Mesyuarat Kickoff', 5.0, true],
            [5, 'Dokumen Kontrak Perjanjian', 5.0, true],
            [6, 'Pembangunan Aplikasi / Penghantaran', 30.0, true],
            [7, 'User Acceptance Test (UAT) / Provisional Acceptance Test (PAT)', 20.0, true],
            [8, 'Final Acceptance Test (FAT)', 10.0, true],
            [9, 'Latihan / TOT', 5.0, false],
            [10, 'Akuan Siap Kerja / Serahan', 5.0, true],
        ], $activities);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ProgressTemplateSeeder::class);

        $this->assertSame(4, ProgressTemplate::count());
        $this->assertSame(31, ProgressTemplateActivity::count());
    }

    public function test_procurement_type_master_name_for_template_d_is_aligned(): void
    {
        $this->assertSame('Pembangunan Sistem / Aplikasi', ProcurementType::where('code', 'pembangunan_aplikasi')->value('name'));
    }

    public function test_template_in_use_cannot_be_changed(): void
    {
        $template = ProgressTemplate::where('code', 'A')->sole();
        $activity = $template->activities->first();

        // Belum digunakan: boleh diubah
        $activity->update(['document_label' => 'Label dikemas kini']);

        $this->project(['progress_template_id' => $template->id]);
        $this->assertTrue($template->fresh()->isInUse());

        $this->assertThrows(fn () => $activity->fresh()->update(['weight' => 50]), LogicException::class);
        $this->assertThrows(fn () => $activity->fresh()->delete(), LogicException::class);
        $this->assertThrows(fn () => $template->activities()->create(['sequence' => 99, 'name' => 'Baharu', 'weight' => 1]), LogicException::class);
        $this->assertSame('5.00', $activity->fresh()->weight);
    }

    // --- KELAYAKAN LULUS JTICT ---

    public function test_only_applications_whose_latest_jtict_decision_is_approved_are_eligible(): void
    {
        $approved = $this->decided('Lulus', Decision::Approved);
        $this->decided('Tidak Lulus', Decision::NotApproved);
        $this->decided('Kaji Semula', Decision::Review);
        $this->decided('Pra JTICT Ditolak', Decision::Rejected);
        $this->application('Tiada keputusan', 'IN_JTICT');

        $deleted = $this->decided('Lulus tetapi dipadam', Decision::Approved);
        $deleted->delete();

        $copy = $this->application('Salinan diluluskan', 'CLOSED');
        $copy->forceFill(['copied_from_application_id' => $approved->id])->save();
        $this->decide($copy, Decision::Approved);

        $this->assertEqualsCanonicalizing(
            ['Lulus', 'Salinan diluluskan'],
            Application::approvedByJtict()->pluck('title')->all(),
        );

        $this->assertTrue($approved->fresh()->isApprovedByJtict());
        $this->assertFalse(Application::where('title', 'Kaji Semula')->sole()->isApprovedByJtict());
    }

    // --- JADUAL PROJECTS ---

    public function test_one_project_per_application(): void
    {
        $application = $this->decided('Lulus', Decision::Approved);
        $this->project(['application_id' => $application->id]);

        $this->expectException(QueryException::class);
        $this->project(['application_id' => $application->id]);
    }

    public function test_legacy_projects_may_have_no_application(): void
    {
        // Banyak projek v1 tanpa permohonan dibenarkan walaupun application_id unik
        $first = $this->project(['source' => ProjectSource::V1, 'legacy_reference' => 'V1-001', 'status' => ProjectStatus::Historical]);
        $second = $this->project(['source' => ProjectSource::V1, 'legacy_reference' => 'V1-002', 'status' => ProjectStatus::Historical]);

        $this->assertNull($first->application_id);
        $this->assertNull($second->application_id);
        $this->assertTrue($first->fresh()->isLegacy());
        $this->assertTrue($first->fresh()->isHistorical());

        $this->expectException(QueryException::class);
        $this->project(['source' => ProjectSource::V1, 'legacy_reference' => 'V1-001']);
    }

    public function test_new_project_defaults(): void
    {
        $project = $this->project()->fresh();

        $this->assertSame(ProjectSource::V2, $project->source);
        $this->assertSame(ProjectStatus::NotStarted, $project->status);
        $this->assertFalse($project->is_phased);
        $this->assertFalse($project->isStructureLocked());
        $this->assertSame('0.00', $project->actual_progress_percent);
        $this->assertNull($project->planned_progress_percent);
        $this->assertNull($project->variance_percent);
    }

    // --- PEMBANTU ---

    private function project(array $attributes = []): Project
    {
        return Project::create($attributes + ['agency_id' => $this->agency->id]);
    }

    private function application(string $title, string $status): Application
    {
        $this->sequence++;

        return Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->user->id,
            'title' => $title,
            'estimated_cost' => 10000,
            'status' => $status,
        ]);
    }

    private function decided(string $title, Decision $decision): Application
    {
        $application = $this->application($title, 'CLOSED');

        if ($decision->stage() === DecisionStage::Jtict) {
            $this->decide($application, Decision::Accepted, 'COMPLETE', 'IN_JTICT');
        }

        $this->decide($application, $decision);

        return $application;
    }

    private function decide(Application $application, Decision $decision, string $from = 'IN_JTICT', ?string $to = null): void
    {
        ApplicationDecision::create([
            'application_id' => $application->id,
            'stage' => $decision->stage(),
            'decision' => $decision,
            'from_status' => $from,
            'to_status' => $to ?? $decision->targetStatus()->value,
            'remarks' => 'Ulasan keputusan ujian.',
            'decision_date' => today(),
            'decided_by' => $this->user->id,
        ]);
    }
}
