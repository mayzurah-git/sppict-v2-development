<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\ScheduleField;
use App\Livewire\Projects\ProjectSetup;
use App\Livewire\Projects\ShowProject;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\ProcurementType;
use App\Models\ProgressTemplate;
use App\Models\Project;
use App\Models\ProjectScheduleChange;
use App\Models\ProjectStructureUnlock;
use App\Models\User;
use App\Services\ProjectMonitoringService;
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
 * SP2: Mulakan Pemantauan, struktur fasa / aktiviti, jadual, akses (audit SP0 J-SP2; keputusan I.1, I.3, I.9,
 * I.11, L.2, L.3).
 *
 * Nota: dijalankan pada SQLite in-memory. Kunci baris (lockForUpdate) dan permintaan serentak sebenar tidak
 * dapat dibuktikan pada SQLite; klik berganda disimulasikan secara berurutan (indeks unik + semakan policy).
 */
class ProjectMonitoringTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private const START = '2027-01-01';

    private const END = '2027-12-31';

    private Agency $agency;

    private Agency $otherAgency;

    private User $primary;

    private User $secondary;

    private User $colleague;

    private User $outsider;

    private User $urusSetia;

    private User $pengurusan;

    private User $superadmin;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProgressTemplateSeeder::class);

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->otherAgency = Agency::create(['code' => 'JPN', 'name' => 'Agensi JPN', 'category' => 'SUK', 'is_active' => true]);
        $btm = Agency::create(['code' => 'BTM', 'name' => 'Bahagian Teknologi Maklumat', 'category' => 'SUK', 'is_active' => true]);

        $this->primary = $this->user('pengguna_biasa', ['name' => 'Ahmad Pegawai Utama']);
        $this->secondary = $this->user('pengguna_biasa', ['name' => 'Bakar Pegawai Kedua']);
        $this->colleague = $this->user('pengguna_biasa', ['name' => 'Chong Rakan Agensi']);
        $this->outsider = $this->user('pengguna_biasa', ['name' => 'Dina Agensi Lain', 'agency_id' => $this->otherAgency->id]);
        $this->urusSetia = $this->user('pentadbir_urus_setia', ['name' => 'Siti Urus Setia', 'agency_id' => $btm->id]);
        $this->pengurusan = $this->user('pengurusan', ['name' => 'Encik Pengurusan', 'agency_id' => $btm->id]);
        $this->superadmin = $this->user('superadmin', ['name' => 'Super Admin', 'agency_id' => $btm->id]);
    }

    // --- MULAKAN PEMANTAUAN ---

    public function test_primary_officer_starts_non_phased_monitoring_with_template_snapshot(): void
    {
        $application = $this->approvedApplication();

        $project = $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));

        $project->refresh()->load('phases.activities');
        $this->assertSame($application->id, $project->application_id);
        $this->assertSame($this->agency->id, $project->agency_id);
        $this->assertSame('D', $project->progressTemplate->code);
        $this->assertSame(ProjectStatus::InProgress, $project->status);
        $this->assertSame(ProjectSource::V2, $project->source);
        $this->assertFalse($project->is_phased);
        $this->assertSame(self::START, $project->planned_start_date->toDateString());
        $this->assertSame(self::END, $project->planned_end_date->toDateString());
        $this->assertSame($this->primary->id, $project->started_by);
        $this->assertNotNull($project->started_at);
        $this->assertFalse($project->isStructureLocked());

        // Tidak Berfasa = satu fasa dengan semua aktiviti templat (jumlah 100)
        $this->assertCount(1, $project->phases);
        $phase = $project->phases->first();
        $this->assertSame(1, $phase->phase_number);
        $this->assertCount(10, $phase->activities);
        $this->assertSame(100.0, $phase->totalWeight());

        // Snapshot pemberat / nama / dokumen daripada templat
        $template = ProgressTemplate::where('code', 'D')->sole()->activities->keyBy('id');
        foreach ($phase->activities as $activity) {
            $source = $template[$activity->progress_template_activity_id];
            $this->assertSame([$source->sequence, $source->name, $source->weight, $source->requires_document, $source->document_label],
                [$activity->sequence, $activity->name, $activity->weight, $activity->requires_document, $activity->document_label]);
            $this->assertNotNull($activity->target_date);
            $this->assertFalse($activity->is_completed);
        }

        // Penetapan pertama tidak direkodkan sebagai pindaan (I.3)
        $this->assertSame(0, ProjectScheduleChange::count());
    }

    public function test_secondary_officer_can_start_monitoring(): void
    {
        $application = $this->approvedApplication();

        $this->assertTrue($this->secondary->can('start', [Project::class, $application]));
        $this->service()->start($application, $this->secondary, $this->singlePhaseInput($application));

        $this->assertSame(1, Project::count());
    }

    public function test_monitoring_cannot_be_started_twice(): void
    {
        $application = $this->approvedApplication();
        $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));

        // Klik berganda / pegawai kedua serentak: ditolak, tiada projek berganda.
        $this->assertFalse($this->secondary->can('start', [Project::class, $application->fresh()]));
        $this->assertThrows(fn () => $this->service()->start($application, $this->secondary, $this->singlePhaseInput($application)), AuthorizationException::class);

        $this->assertSame(1, Project::count());
    }

    public function test_only_active_officers_of_an_approved_application_can_start(): void
    {
        $application = $this->approvedApplication();

        // Pegawai Kedua yang telah tamat tugasan
        $application->officers()->role(OfficerRole::Secondary)->update(['ended_at' => today()]);

        foreach ([
            'Rakan seagensi bukan pegawai' => $this->colleague,
            'Pegawai Kedua tamat tugasan' => $this->secondary,
            'Agensi lain' => $this->outsider,
            'Urus Setia' => $this->urusSetia,
            'Pengurusan' => $this->pengurusan,
            'Superadmin' => $this->superadmin,
        ] as $label => $user) {
            $this->assertFalse($user->can('start', [Project::class, $application->fresh()]), $label);
            $this->assertThrows(fn () => $this->service()->start($application, $user, $this->singlePhaseInput($application)), AuthorizationException::class, null, $label);
        }

        // Pegawai Utama yang telah bertukar agensi
        $this->primary->update(['agency_id' => $this->otherAgency->id]);
        $this->assertFalse($this->primary->fresh()->can('start', [Project::class, $application->fresh()]));

        $this->assertSame(0, Project::count());
    }

    public function test_applications_not_approved_by_jtict_cannot_be_monitored(): void
    {
        foreach ([Decision::NotApproved, Decision::Review] as $decision) {
            $application = $this->approvedApplication(decision: $decision);
            $this->assertFalse($this->primary->can('start', [Project::class, $application]), $decision->value);
        }

        $deleted = $this->approvedApplication();
        $deleted->delete();
        $this->assertFalse($this->primary->can('start', [Project::class, $deleted]));
    }

    public function test_procurement_type_without_active_template_is_blocked(): void
    {
        $application = $this->approvedApplication('penyelenggaraan');
        ProgressTemplate::where('code', 'C')->update(['is_active' => false]);

        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, ['planned_start_date' => self::START, 'planned_end_date' => self::END]), 'template');
        $this->assertSame(0, Project::count());
    }

    // --- PROJEK BERFASA (L.3) ---

    public function test_phases_are_only_allowed_for_template_d(): void
    {
        $application = $this->approvedApplication('pembekalan_perkhidmatan_one_off');
        $ids = $this->templateIds('A');

        $input = $this->phasedInput([array_slice($ids, 0, 3), array_slice($ids, 3)]);

        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $input), 'is_phased');
        $this->assertSame(0, Project::count());
    }

    public function test_phased_template_d_project_with_activities_chosen_per_phase(): void
    {
        $application = $this->approvedApplication();
        $ids = $this->templateIds('D');

        // Fasa 1: semua aktiviti; Fasa 2: Pembangunan, UAT/PAT, FAT (aktiviti sama boleh berada dalam fasa berlainan)
        $project = $this->service()->start($application, $this->primary, $this->phasedInput([$ids, [$ids[5], $ids[6], $ids[7]]], ['Fasa Asas', 'Modul Tambahan']));

        $project->load('phases.activities');
        $this->assertTrue($project->is_phased);
        $this->assertSame([1, 2], $project->phases->pluck('phase_number')->all());
        $this->assertSame(['Fasa Asas', 'Modul Tambahan'], $project->phases->pluck('name')->all());
        $this->assertSame(100.0, $project->phases[0]->totalWeight());
        $this->assertSame(60.0, $project->phases[1]->totalWeight()); // dikira berkadar dalam SP4
        $this->assertSame(13, $project->activities()->count());
    }

    public function test_phased_project_requires_two_to_ten_phases_each_with_an_activity(): void
    {
        $application = $this->approvedApplication();
        $ids = $this->templateIds('D');

        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $this->phasedInput([$ids])), 'phases');
        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $this->phasedInput(array_fill(0, 11, [$ids[0]]))), 'phases');
        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $this->phasedInput([$ids, []])), 'phases.1.activities');

        // Aktiviti di luar templat projek
        $foreign = $this->templateIds('A')[0];
        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $this->phasedInput([$ids, [$foreign]])), 'phases.1.activities');

        $this->assertSame(0, Project::count());
    }

    // --- TARIKH ---

    public function test_project_and_target_dates_are_validated(): void
    {
        $application = $this->approvedApplication();
        $ids = $this->templateIds('D');

        $missing = $this->singlePhaseInput($application);
        $missing['phases'][0]['activities'][$ids[2]] = '';
        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $missing), "phases.0.activities.{$ids[2]}");

        $outside = $this->singlePhaseInput($application);
        $outside['phases'][0]['activities'][$ids[9]] = '2028-01-15';
        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $outside), "phases.0.activities.{$ids[9]}");

        $invalid = $this->singlePhaseInput($application);
        $invalid['phases'][0]['activities'][$ids[0]] = '2027-02-30';
        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $invalid), "phases.0.activities.{$ids[0]}");

        $reversed = $this->singlePhaseInput($application);
        $reversed['planned_end_date'] = '2026-12-31';
        $this->assertValidationError(fn () => $this->service()->start($application, $this->primary, $reversed), 'planned_end_date');

        $this->assertSame(0, Project::count());
    }

    // --- STRUKTUR & KUNCI (L.3) ---

    public function test_structure_can_be_changed_before_lock_keeping_existing_target_dates(): void
    {
        $application = $this->approvedApplication();
        $project = $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));
        $ids = $this->templateIds('D');
        $originalDate = $project->activities()->where('progress_template_activity_id', $ids[0])->sole()->target_date->toDateString();
        $this->assertSame('2027-01-15', $originalDate);

        $input = $this->phasedInput([$ids, [$ids[5], $ids[6]]]);
        $input['phases'][0]['activities'][$ids[0]] = '2027-11-30'; // diabaikan: pindaan tarikh melalui Pinda Jadual
        $this->service()->updateStructure($project, $this->secondary, $input);

        $project->refresh()->load('phases.activities');
        $this->assertTrue($project->is_phased);
        $this->assertCount(2, $project->phases);
        $this->assertSame($originalDate, $project->phases[0]->activities->firstWhere('progress_template_activity_id', $ids[0])->target_date->toDateString());
        $this->assertSame('2027-06-15', $project->phases[1]->activities->firstWhere('progress_template_activity_id', $ids[5])->target_date->toDateString());
        $this->assertSame(0, ProjectScheduleChange::count());

        // Kembali kepada Tidak Berfasa: fasa 2 di-soft delete (rekod kekal)
        $this->service()->updateStructure($project, $this->primary, ['is_phased' => false, 'phases' => []]);
        $project->refresh();
        $this->assertFalse($project->is_phased);
        $this->assertSame(1, $project->phases()->count());
        $this->assertSame(1, $project->phases()->onlyTrashed()->count());
        $this->assertSame(10, $project->activities()->count());

        // Pilih semula fasa 2: rekod dipulihkan, bukan pendua
        $this->service()->updateStructure($project, $this->primary, $this->phasedInput([$ids, [$ids[5]]]));
        $this->assertSame(2, $project->phases()->count());
        $this->assertSame(0, $project->phases()->onlyTrashed()->count());
    }

    public function test_locked_structure_can_only_be_reopened_by_urus_setia_with_remarks(): void
    {
        $application = $this->approvedApplication();
        $project = $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));
        $ids = $this->templateIds('D');

        // SP3 akan menetapkan ini apabila aktiviti pertama ditanda siap.
        $project->activities()->where('progress_template_activity_id', $ids[0])->update(['is_completed' => true, 'actual_completion_date' => '2027-01-20']);
        $project->update(['structure_locked_at' => now()]);
        $project->refresh();

        $this->assertFalse($this->primary->can('updateStructure', $project));
        $this->assertThrows(fn () => $this->service()->updateStructure($project, $this->primary, $this->phasedInput([$ids, [$ids[5]]])), AuthorizationException::class);

        foreach (['Pegawai Utama' => $this->primary, 'Pengurusan' => $this->pengurusan, 'Superadmin' => $this->superadmin] as $label => $user) {
            $this->assertFalse($user->can('unlockStructure', $project), $label);
        }
        $this->assertThrows(fn () => $this->service()->unlockStructure($project, $this->primary, 'Cubaan'), AuthorizationException::class);
        $this->assertValidationError(fn () => $this->service()->unlockStructure($project, $this->urusSetia, '  '), 'unlockRemarks');

        $this->service()->unlockStructure($project, $this->urusSetia, 'Pegawai memohon menambah fasa kedua.');
        $project->refresh();
        $this->assertFalse($project->isStructureLocked());
        $unlock = ProjectStructureUnlock::sole();
        $this->assertSame($this->urusSetia->id, $unlock->unlocked_by);
        $this->assertNotNull($unlock->locked_at);

        // Aktiviti yang telah ditanda siap tidak boleh dikeluarkan
        $this->assertValidationError(fn () => $this->service()->updateStructure($project, $this->primary, $this->phasedInput([array_slice($ids, 1), [$ids[5]]])), 'phases');

        // Tambah fasa dengan aktiviti siap dikekalkan: berjaya, dan struktur dikunci semula
        $this->service()->updateStructure($project, $this->primary, $this->phasedInput([$ids, [$ids[5]]]));
        $this->assertTrue($project->fresh()->isStructureLocked());

        // Sejarah buka kunci append-only
        $this->assertThrows(fn () => $unlock->update(['remarks' => 'Ubah']), LogicException::class);
        $this->assertThrows(fn () => $unlock->delete(), LogicException::class);
    }

    // --- PINDAAN JADUAL (I.3) ---

    public function test_schedule_amendment_requires_justification_and_keeps_history(): void
    {
        $application = $this->approvedApplication();
        $project = $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));
        $activity = $project->activities()->orderBy('sequence')->first();
        $oldTarget = $activity->target_date->toDateString();

        $input = [
            'planned_start_date' => self::START,
            'planned_end_date' => '2028-03-31',
            'activities' => [$activity->uuid => '2027-02-28'],
        ];

        $this->assertValidationError(fn () => $this->service()->amendSchedule($project, $this->primary, $input, ''), 'justification');
        $this->assertValidationError(fn () => $this->service()->amendSchedule($project, $this->primary, ['planned_start_date' => self::START, 'planned_end_date' => self::END], 'Tiada perubahan'), 'schedule');
        $this->assertSame(0, ProjectScheduleChange::count());

        $count = $this->service()->amendSchedule($project, $this->secondary, $input, 'Pembekal memohon lanjutan masa.');

        $this->assertSame(2, $count);
        $this->assertSame('2028-03-31', $project->fresh()->planned_end_date->toDateString());
        $this->assertSame('2027-02-28', $activity->fresh()->target_date->toDateString());

        $changes = ProjectScheduleChange::orderBy('id')->get();
        $this->assertCount(2, $changes);
        $this->assertSame(1, $changes->pluck('batch_uuid')->unique()->count());
        $this->assertSame([ScheduleField::PlannedEndDate, self::END, '2028-03-31'], [$changes[0]->field, $changes[0]->old_value->toDateString(), $changes[0]->new_value->toDateString()]);
        $this->assertSame([ScheduleField::TargetDate, $activity->id, $oldTarget, '2027-02-28'], [$changes[1]->field, $changes[1]->project_progress_activity_id, $changes[1]->old_value->toDateString(), $changes[1]->new_value->toDateString()]);
        $this->assertSame($this->secondary->id, $changes[0]->changed_by);
        $this->assertSame('Pembekal memohon lanjutan masa.', $changes[0]->justification);

        // Pindaan kedua tidak menimpa sejarah terdahulu
        $this->service()->amendSchedule($project, $this->primary, ['planned_start_date' => self::START, 'planned_end_date' => '2028-06-30'], 'Lanjutan kedua.');
        $this->assertSame(3, ProjectScheduleChange::count());
        $this->assertSame('2028-03-31', ProjectScheduleChange::orderBy('id')->first()->new_value->toDateString());

        // Append-only
        $this->assertThrows(fn () => $changes[0]->update(['justification' => 'Ubah']), LogicException::class);
        $this->assertThrows(fn () => $changes[0]->delete(), LogicException::class);
    }

    public function test_schedule_amendment_rejects_target_dates_outside_project_period(): void
    {
        $application = $this->approvedApplication();
        $project = $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));
        $last = $project->activities()->orderByDesc('sequence')->first();

        // Memendekkan tempoh projek sehingga tarikh sasaran sedia ada berada di luar tempoh
        $this->assertValidationError(
            fn () => $this->service()->amendSchedule($project, $this->primary, ['planned_start_date' => self::START, 'planned_end_date' => '2027-03-31'], 'Pendekkan tempoh'),
            "activities.{$last->uuid}",
        );
        $this->assertSame(self::END, $project->fresh()->planned_end_date->toDateString());
    }

    public function test_only_active_officers_can_amend_schedule(): void
    {
        $application = $this->approvedApplication();
        $project = $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));

        foreach (['Rakan seagensi' => $this->colleague, 'Agensi lain' => $this->outsider, 'Urus Setia' => $this->urusSetia, 'Pengurusan' => $this->pengurusan, 'Superadmin' => $this->superadmin] as $label => $user) {
            $this->assertFalse($user->can('manage', $project), $label);
            $this->assertThrows(fn () => $this->service()->amendSchedule($project, $user, ['planned_start_date' => self::START, 'planned_end_date' => '2028-01-31'], 'Cubaan'), AuthorizationException::class, null, $label);
        }

        // Pegawai yang telah diganti tidak lagi boleh mengemas kini (I.11)
        $application->officers()->role(OfficerRole::Primary)->update(['ended_at' => today()]);
        $this->assertFalse($this->primary->can('manage', $project->fresh()));

        // Projek baca sahaja
        $project->update(['status' => ProjectStatus::Closed]);
        $this->assertFalse($this->secondary->can('manage', $project->fresh()));
    }

    // --- AKSES (I.9) ---

    public function test_project_access_by_role_and_agency(): void
    {
        $application = $this->approvedApplication();
        $project = $this->service()->start($application, $this->primary, $this->singlePhaseInput($application));

        foreach ([$this->primary, $this->colleague, $this->urusSetia, $this->pengurusan] as $user) {
            $this->actingAs($user)->get(route('projects.index'))->assertOk();
            $this->actingAs($user)->get(route('projects.show', $project->uuid))->assertOk()->assertSee($application->title);
        }

        // Akses merentas agensi melalui UUID ditolak
        $this->actingAs($this->outsider)->get(route('projects.show', $project->uuid))->assertForbidden();
        $this->actingAs($this->outsider)->get(route('projects.index'))->assertOk()->assertDontSee($application->title);

        // Superadmin tiada akses
        $this->actingAs($this->superadmin)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($this->superadmin)->get(route('projects.show', $project->uuid))->assertForbidden();
        $this->assertFalse($this->superadmin->can('view', $project));

        // Urus Setia & Pengurusan: baca sahaja
        $this->actingAs($this->pengurusan)->get(route('projects.show', $project->uuid))->assertDontSee('Pinda Jadual');
        $this->actingAs($this->urusSetia)->get(route('projects.show', $project->uuid))->assertDontSee('Pinda Jadual');
        $this->actingAs($this->primary)->get(route('projects.show', $project->uuid))->assertSee('Pinda Jadual')->assertSee('Pinda Struktur');

        // Halaman struktur & mula: pegawai aktif sahaja
        $this->actingAs($this->outsider)->get(route('projects.structure', $project->uuid))->assertForbidden();
        $this->actingAs($this->pengurusan)->get(route('projects.structure', $project->uuid))->assertForbidden();
        $this->actingAs($this->primary)->get(route('projects.structure', $project->uuid))->assertOk();
    }

    public function test_legacy_project_access_uses_project_agency(): void
    {
        $legacy = Project::create(['agency_id' => $this->agency->id, 'source' => ProjectSource::V1, 'legacy_reference' => 'V1-2019-001', 'status' => ProjectStatus::Historical]);

        $this->assertTrue($this->colleague->can('view', $legacy));
        $this->assertFalse($this->outsider->can('view', $legacy));
        $this->assertFalse($this->primary->can('manage', $legacy));
        $this->assertFalse($this->urusSetia->can('recordJpict', $legacy));
        $this->assertFalse($legacy->isAwaitingJpict());
    }

    // --- JPICT (I.1) ---

    public function test_awaiting_jpict_marker_and_optional_recording_by_urus_setia(): void
    {
        $large = $this->approvedApplication(total: 600000);
        $project = $this->service()->start($large, $this->primary, $this->singlePhaseInput($large));

        $this->assertTrue($project->requiresJpict());
        $this->assertTrue($project->isAwaitingJpict());
        $this->actingAs($this->primary)->get(route('projects.show', $project->uuid))->assertSee('Menunggu JPICT');
        $this->actingAs($this->primary)->get(route('projects.index'))->assertSee('Menunggu JPICT');

        $this->assertFalse($this->primary->can('recordJpict', $project));
        $this->assertFalse($this->pengurusan->can('recordJpict', $project));
        $this->assertThrows(fn () => $this->service()->recordJpict($project, $this->primary, '2027-01-10', 'JPICT 1/2027'), AuthorizationException::class);
        $this->assertValidationError(fn () => $this->service()->recordJpict($project, $this->urusSetia, today()->addDay()->toDateString(), null), 'jpictDate');

        $this->service()->recordJpict($project, $this->urusSetia, today()->toDateString(), 'Mesyuarat JPICT Bil. 1/2027');

        $project->refresh();
        $this->assertFalse($project->isAwaitingJpict());
        $this->assertSame($this->urusSetia->id, $project->jpict_recorded_by);
        $this->assertSame('Mesyuarat JPICT Bil. 1/2027', $project->jpict_reference);

        // Projek bawah RM500,000: tiada penanda, tiada rekod JPICT
        $small = $this->approvedApplication(total: 499999.99);
        $smallProject = $this->service()->start($small, $this->primary, $this->singlePhaseInput($small));
        $this->assertFalse($smallProject->isAwaitingJpict());
        $this->assertFalse($this->urusSetia->can('recordJpict', $smallProject));
    }

    // --- SKRIN (LIVEWIRE) ---

    public function test_officer_starts_monitoring_through_the_screens(): void
    {
        $application = $this->approvedApplication();

        $this->actingAs($this->primary)->get(route('application.view', $application->uuid))->assertSee('Mulakan Pemantauan');
        $this->actingAs($this->colleague)->get(route('application.view', $application->uuid))->assertDontSee('Mulakan Pemantauan');
        $this->actingAs($this->primary)->get(route('projects.index'))->assertSee($application->reference_number)->assertSee('Mulakan Pemantauan');
        $this->actingAs($this->colleague)->get(route('projects.start', $application->uuid))->assertForbidden();

        $component = Livewire::actingAs($this->primary)->test(ProjectSetup::class, ['application' => $application->uuid])
            ->set('plannedStartDate', self::START)
            ->set('plannedEndDate', self::END);

        foreach ($this->singlePhaseInput($application)['phases'][0]['activities'] as $id => $date) {
            $component->set("phases.0.activities.{$id}.target_date", $date);
        }

        $project = null;
        $component->call('save')->assertHasNoErrors();
        $project = Project::sole();
        $component->assertRedirect(route('projects.show', $project->uuid));
        $this->assertCount(10, $project->activities);

        $this->actingAs($this->primary)->get(route('application.view', $application->uuid))->assertSee('Lihat Pemantauan Projek');

        // Pinda Jadual melalui skrin: justifikasi wajib
        $activity = $project->activities()->orderBy('sequence')->first();
        Livewire::actingAs($this->primary)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->call('openScheduleModal')
            ->set("targetDates.{$activity->uuid}", '2027-01-31')
            ->call('saveSchedule')
            ->assertHasErrors('justification')
            ->set('justification', 'Mesyuarat kickoff dianjakkan.')
            ->call('saveSchedule')
            ->assertHasNoErrors()
            ->assertSee('Mesyuarat kickoff dianjakkan.');

        $this->assertSame('2027-01-31', $activity->fresh()->target_date->toDateString());
    }

    public function test_phased_setup_screen_validates_through_service(): void
    {
        $application = $this->approvedApplication();
        $ids = $this->templateIds('D');

        Livewire::actingAs($this->primary)->test(ProjectSetup::class, ['application' => $application->uuid])
            ->set('isPhased', true)
            ->assertCount('phases', 2)
            ->set('plannedStartDate', self::START)
            ->set('plannedEndDate', self::END)
            ->call('save')
            // Fasa 1 bermula dengan semua aktiviti dipilih (tarikh belum diisi); Fasa 2 kosong.
            ->assertHasErrors(["phases.0.activities.{$ids[0]}", 'phases.1.activities'])
            ->set("phases.1.activities.{$ids[5]}.selected", true)
            ->call('save')
            ->assertHasNoErrors('phases.1.activities')
            ->assertHasErrors("phases.1.activities.{$ids[5]}");

        $this->assertSame(0, Project::count());
    }

    // --- PEMBANTU ---

    private function service(): ProjectMonitoringService
    {
        return app(ProjectMonitoringService::class);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['agency_id' => $this->agency->id]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Permohonan dengan Pegawai Utama / Kedua aktif dan keputusan JTICT (lalai Lulus).
     */
    private function approvedApplication(string $procurementCode = 'pembangunan_aplikasi', float $total = 150000, Decision $decision = Decision::Approved): Application
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->primary->id,
            'title' => "Projek Ujian Pemantauan {$this->sequence}",
            'procurement_type_id' => $this->masterId(ProcurementType::class, $procurementCode),
            'estimated_cost' => $total,
            'total_cost' => $total,
            'status' => 'CLOSED',
        ]);

        foreach ([OfficerRole::Primary->value => $this->primary, OfficerRole::Secondary->value => $this->secondary] as $role => $user) {
            $application->officers()->create(ApplicationOfficer::snapshotFrom($user) + ['role' => $role, 'started_at' => today()->subMonth()]);
        }

        foreach ([Decision::Accepted, $decision] as $item) {
            ApplicationDecision::create([
                'application_id' => $application->id,
                'stage' => $item->stage(),
                'decision' => $item,
                'from_status' => $item->stage() === DecisionStage::PreJtict ? 'COMPLETE' : 'IN_JTICT',
                'to_status' => $item->targetStatus()->value,
                'remarks' => 'Ulasan keputusan ujian.',
                'decision_date' => today(),
                'decided_by' => $this->urusSetia->id,
            ]);
        }

        return $application->fresh();
    }

    /** @return list<int> ID aktiviti templat mengikut urutan */
    private function templateIds(string $code): array
    {
        return ProgressTemplate::where('code', $code)->sole()->activities->pluck('id')->all();
    }

    /**
     * Tidak Berfasa: tarikh sasaran setiap aktiviti templat, sebulan selepas yang sebelumnya.
     */
    private function singlePhaseInput(Application $application): array
    {
        $template = ProgressTemplate::activeFor($application->procurement_type_id);
        $dates = [];
        foreach ($template->activities as $index => $activity) {
            $dates[$activity->id] = sprintf('2027-%02d-15', $index + 1);
        }

        return [
            'is_phased' => false,
            'planned_start_date' => self::START,
            'planned_end_date' => self::END,
            'phases' => [['name' => '', 'activities' => $dates]],
        ];
    }

    /**
     * @param  list<list<int>>  $phases  ID aktiviti templat bagi setiap fasa
     */
    private function phasedInput(array $phases, array $names = []): array
    {
        return [
            'is_phased' => true,
            'planned_start_date' => self::START,
            'planned_end_date' => self::END,
            'phases' => collect($phases)->map(fn (array $ids, int $index) => [
                'name' => $names[$index] ?? '',
                'activities' => collect($ids)->mapWithKeys(fn (int $id) => [$id => '2027-06-15'])->all(),
            ])->all(),
        ];
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
