<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ClosureEventType;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Livewire\Projects\ShowProject;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\ProcurementType;
use App\Models\ProgressTemplate;
use App\Models\Project;
use App\Models\ProjectClosureEvent;
use App\Models\User;
use App\Services\ProjectClosureService;
use App\Services\ProjectMonitoringService;
use App\Services\ProjectProgressService;
use App\Services\ProjectReportService;
use Carbon\Carbon;
use Database\Seeders\ProgressTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * SP4: penutupan projek (keputusan I.6, audit SP0 L.8; butiran disahkan pemilik 23/11/2026).
 */
class ProjectClosureTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private Agency $agency;

    private User $primary;

    private User $secondary;

    private User $colleague;

    private User $urusSetia;

    private User $pengurusan;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2027-05-20 10:00'));
        $this->seed(ProgressTemplateSeeder::class);

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $btm = Agency::create(['code' => 'BTM', 'name' => 'Bahagian Teknologi Maklumat', 'category' => 'SUK', 'is_active' => true]);

        $this->primary = $this->user('pengguna_biasa', ['name' => 'Ahmad Pegawai Utama']);
        $this->secondary = $this->user('pengguna_biasa', ['name' => 'Bakar Pegawai Kedua']);
        $this->colleague = $this->user('pengguna_biasa', ['name' => 'Chong Rakan Agensi']);
        $this->urusSetia = $this->user('pentadbir_urus_setia', ['name' => 'Siti Urus Setia', 'agency_id' => $btm->id]);
        $this->pengurusan = $this->user('pengurusan', ['name' => 'Encik Pengurusan', 'agency_id' => $btm->id]);
    }

    public function test_closure_can_only_be_requested_when_all_activities_are_completed(): void
    {
        $project = $this->project();

        $this->assertNotNull($this->closure()->closureBlocker($project));
        $this->assertValidationError(fn () => $this->closure()->request($project, $this->primary, null), 'closure');

        $this->completeAll($project, except: 10);
        $this->assertValidationError(fn () => $this->closure()->request($project, $this->primary, null), 'closure');

        $this->completeAll($project);
        $this->assertNull($this->closure()->closureBlocker($project));

        foreach (['Rakan seagensi' => $this->colleague, 'Urus Setia' => $this->urusSetia, 'Pengurusan' => $this->pengurusan] as $label => $user) {
            $this->assertThrows(fn () => $this->closure()->request($project, $user, null), AuthorizationException::class, null, $label);
        }

        $this->closure()->request($project, $this->secondary, 'Semua kerja telah diserahkan.');

        $project->refresh();
        $this->assertSame(ProjectStatus::PendingClosure, $project->status);
        $this->assertSame('2027-04-30', $project->actual_completion_date->toDateString()); // aktiviti terakhir siap
        $event = ProjectClosureEvent::sole();
        $this->assertSame([ClosureEventType::Requested, 'Semua kerja telah diserahkan.', $this->secondary->id], [$event->event_type, $event->remarks, $event->actor_id]);
    }

    public function test_activities_are_locked_while_pending_but_monthly_reports_continue(): void
    {
        $project = $this->project();
        $this->completeAll($project);
        $this->closure()->request($project, $this->primary, null);
        $project->refresh();

        $this->assertFalse($this->primary->can('manage', $project));
        $this->assertTrue($this->primary->can('manageReports', $project));
        $this->assertFalse($this->urusSetia->can('unlockStructure', $project));

        $activity = $project->activities()->where('sequence', 9)->sole();
        $this->assertThrows(fn () => app(ProjectProgressService::class)->uncompleteActivity($activity, $this->primary, 'Cubaan'), AuthorizationException::class);
        $this->assertThrows(fn () => app(ProjectMonitoringService::class)->amendSchedule($project, $this->primary, ['planned_start_date' => '2027-01-01', 'planned_end_date' => '2028-01-31'], 'Cubaan'), AuthorizationException::class);

        // Laporan tertunggak masih boleh dihantar
        $report = app(ProjectReportService::class)->create($project, $this->primary, 2027, 4);
        app(ProjectReportService::class)->submit($report, $this->primary, ['work_summary' => 'Laporan April.']);
        $this->assertTrue($report->fresh()->isSubmitted());
    }

    public function test_officer_can_withdraw_and_urus_setia_can_reject_with_remarks(): void
    {
        $project = $this->project();
        $this->completeAll($project);

        $this->closure()->request($project, $this->primary, null);
        $this->closure()->withdraw($project, $this->secondary, 'Dokumen serahan perlu dikemas kini.');
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->assertNull($project->fresh()->actual_completion_date);

        $this->closure()->request($project, $this->primary, null);

        $this->assertThrows(fn () => $this->closure()->reject($project, $this->primary, 'Pegawai menolak sendiri'), AuthorizationException::class);
        $this->assertThrows(fn () => $this->closure()->approve($project, $this->pengurusan, null), AuthorizationException::class);
        $this->assertValidationError(fn () => $this->closure()->reject($project, $this->urusSetia, ' '), 'closureRemarks');

        $this->closure()->reject($project, $this->urusSetia, 'Akuan Siap Kerja belum ditandatangani.');

        $project->refresh();
        $this->assertSame(ProjectStatus::InProgress, $project->status);
        $this->assertTrue($this->primary->can('manage', $project));
        $this->assertSame(
            [ClosureEventType::Requested, ClosureEventType::Withdrawn, ClosureEventType::Requested, ClosureEventType::Rejected],
            ProjectClosureEvent::orderBy('id')->pluck('event_type')->all(),
        );
    }

    public function test_urus_setia_approval_completes_the_project_as_read_only(): void
    {
        $project = $this->project();
        $this->completeAll($project);
        $this->closure()->request($project, $this->primary, null);

        $this->closure()->approve($project, $this->urusSetia, null);

        $project->refresh();
        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertTrue($project->is_completed);
        $this->assertSame($this->urusSetia->id, $project->closed_by);
        $this->assertNotNull($project->closed_at);
        $this->assertSame('2027-04-30', $project->actual_completion_date->toDateString());

        // Baca sahaja; tiada buka semula
        foreach (['manage', 'manageReports', 'requestClosure', 'withdrawClosure', 'updateStructure'] as $ability) {
            $this->assertFalse($this->primary->can($ability, $project), $ability);
        }
        foreach (['decideClosure', 'terminate', 'unlockStructure'] as $ability) {
            $this->assertFalse($this->urusSetia->can($ability, $project), $ability);
        }
        $this->assertTrue($this->colleague->can('view', $project));

        $event = ProjectClosureEvent::latest('id')->first();
        $this->assertThrows(fn () => $event->update(['remarks' => 'Ubah']), LogicException::class);
        $this->assertThrows(fn () => $event->delete(), LogicException::class);
    }

    public function test_urus_setia_can_close_project_without_completion(): void
    {
        $project = $this->project();

        $this->assertThrows(fn () => $this->closure()->terminate($project, $this->primary, 'Pegawai'), AuthorizationException::class);
        $this->assertThrows(fn () => $this->closure()->terminate($project, $this->pengurusan, 'Pengurusan'), AuthorizationException::class);
        $this->assertValidationError(fn () => $this->closure()->terminate($project, $this->urusSetia, ''), 'closureRemarks');

        $this->closure()->terminate($project, $this->urusSetia, 'Projek dibatalkan kerana peruntukan ditarik balik.');

        $project->refresh();
        $this->assertSame(ProjectStatus::Closed, $project->status);
        $this->assertFalse($project->is_completed);
        $this->assertSame('Projek dibatalkan kerana peruntukan ditarik balik.', $project->closure_remarks);
        $this->assertFalse($this->primary->can('manage', $project));
        $this->assertFalse($this->urusSetia->can('terminate', $project));

        // Projek v1 (rekod sejarah) tidak ditutup melalui fungsi ini
        $legacy = Project::create(['agency_id' => $this->agency->id, 'source' => ProjectSource::V1, 'legacy_reference' => 'V1-001', 'status' => ProjectStatus::Historical]);
        $this->assertFalse($this->urusSetia->can('terminate', $legacy));
    }

    public function test_pending_closure_can_also_be_terminated(): void
    {
        $project = $this->project();
        $this->completeAll($project);
        $this->closure()->request($project, $this->primary, null);

        $this->closure()->terminate($project, $this->urusSetia, 'Ditamatkan atas keputusan pengurusan.');

        $this->assertSame(ProjectStatus::Closed, $project->fresh()->status);
        $this->assertNull($project->fresh()->actual_completion_date);
    }

    public function test_maintenance_project_closes_only_after_contract_end_date(): void
    {
        $project = $this->project('penyelenggaraan', '2027-12-31');
        $this->assertTrue($project->fresh()->isMaintenance());

        $this->completeAll($project);
        $this->assertSame('100.00', $project->fresh()->actual_progress_percent);

        $blocker = $this->closure()->closureBlocker($project->fresh());
        $this->assertStringContainsString('31/12/2027', $blocker);
        $this->assertValidationError(fn () => $this->closure()->request($project->fresh(), $this->primary, null), 'closure');
        $this->actingAs($this->primary)->get(route('projects.show', $project->uuid))->assertSee('100% – Dalam Pelaksanaan Kontrak');

        $this->travelTo(Carbon::parse('2027-12-31 09:00'));
        $this->assertNull($this->closure()->closureBlocker($project->fresh()));
        $this->closure()->request($project->fresh(), $this->primary, null);
        $this->assertSame(ProjectStatus::PendingClosure, $project->fresh()->status);
    }

    public function test_missing_monthly_reports_are_counted(): void
    {
        $project = $this->project(); // Jan–Mei 2027 = 5 bulan
        $this->assertSame(5, $this->closure()->missingReportCount($project));

        $report = app(ProjectReportService::class)->create($project, $this->primary, 2027, 1);
        $this->assertSame(5, $this->closure()->missingReportCount($project)); // Draf tidak dikira
        app(ProjectReportService::class)->submit($report, $this->primary, ['work_summary' => 'Januari.']);
        $this->assertSame(4, $this->closure()->missingReportCount($project));
    }

    public function test_closure_through_the_screens(): void
    {
        $project = $this->project();
        $this->completeAll($project);

        Livewire::actingAs($this->primary)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->assertSee('Mohon Penutupan')
            ->call('openClosureModal', 'request')
            ->set('closureRemarks', 'Projek siap sepenuhnya.')
            ->call('submitClosure')
            ->assertHasNoErrors()
            ->assertSee('menunggu pengesahan Urus Setia')
            ->assertDontSee('Tanda Siap');

        Livewire::actingAs($this->urusSetia)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->assertSee('Sahkan Penutupan')
            ->assertSee('laporan kemajuan bulanan belum dihantar')
            ->call('openClosureModal', 'reject')
            ->call('submitClosure')
            ->assertHasErrors('closureRemarks')
            ->call('openClosureModal', 'approve')
            ->call('submitClosure')
            ->assertHasNoErrors()
            ->assertSee('Selesai');

        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);

        // Pengurusan: tiada butang tindakan penutupan
        Livewire::actingAs($this->pengurusan)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->assertDontSee('Tutup Projek (Tanpa Siap)');
        Livewire::actingAs($this->pengurusan)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->call('openClosureModal', 'terminate')
            ->assertForbidden();
    }

    // --- PEMBANTU ---

    private function closure(): ProjectClosureService
    {
        return app(ProjectClosureService::class);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['agency_id' => $this->agency->id]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Projek Tidak Berfasa 01/01/2027 – $end; tarikh sasaran aktiviti ke-n = 15 hari bulan n.
     */
    private function project(string $procurementCode = 'pembangunan_aplikasi', string $end = '2027-12-31'): Project
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2027/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->primary->id,
            'title' => "Projek Penutupan {$this->sequence}",
            'procurement_type_id' => $this->masterId(ProcurementType::class, $procurementCode),
            'estimated_cost' => 150000,
            'total_cost' => 150000,
            'status' => 'CLOSED',
        ]);

        foreach ([OfficerRole::Primary->value => $this->primary, OfficerRole::Secondary->value => $this->secondary] as $role => $user) {
            $application->officers()->create(ApplicationOfficer::snapshotFrom($user) + ['role' => $role, 'started_at' => '2026-12-01']);
        }

        foreach ([Decision::Accepted, Decision::Approved] as $item) {
            ApplicationDecision::create([
                'application_id' => $application->id,
                'stage' => $item->stage(),
                'decision' => $item,
                'from_status' => $item->stage() === DecisionStage::PreJtict ? 'COMPLETE' : 'IN_JTICT',
                'to_status' => $item->targetStatus()->value,
                'remarks' => 'Ulasan keputusan ujian.',
                'decision_date' => '2026-12-15',
                'decided_by' => $this->urusSetia->id,
            ]);
        }

        $template = ProgressTemplate::activeFor($application->procurement_type_id);
        $dates = [];
        foreach ($template->activities as $index => $activity) {
            $dates[$activity->id] = sprintf('2027-%02d-15', $index + 1);
        }

        return app(ProjectMonitoringService::class)->start($application->fresh(), $this->primary, [
            'is_phased' => false,
            'planned_start_date' => '2027-01-01',
            'planned_end_date' => $end,
            'phases' => [['name' => '', 'activities' => $dates]],
        ]);
    }

    /**
     * Tandakan aktiviti siap terus (dokumen wajib diuji dalam ProjectProgressTest); aktiviti terakhir siap 30/04/2027.
     */
    private function completeAll(Project $project, ?int $except = null): void
    {
        $project->activities()->when($except, fn ($q) => $q->where('sequence', '!=', $except))
            ->update(['is_completed' => true, 'actual_completion_date' => '2027-03-01', 'completed_by' => $this->primary->id, 'completed_at' => now()]);
        $project->activities()->where('is_completed', true)->orderByDesc('sequence')->limit(1)
            ->update(['actual_completion_date' => '2027-04-30']);
        $project->update(['structure_locked_at' => now()]);

        app(\App\Services\ProgressCalculator::class)->refresh($project->fresh());
    }

    private function assertValidationError(callable $callback, string $key): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), 'Ralat sebenar: ' . json_encode($e->errors(), JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->fail("ValidationException dengan kunci {$key} dijangka.");
    }
}
