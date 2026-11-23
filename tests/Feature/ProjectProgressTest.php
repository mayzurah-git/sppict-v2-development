<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Enums\ProgressReportStatus;
use App\Enums\ProgressUpdateType;
use App\Livewire\Projects\ProgressReportPage;
use App\Livewire\Projects\ShowProject;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\ProcurementType;
use App\Models\ProgressTemplate;
use App\Models\Project;
use App\Models\ProjectProgressActivity;
use App\Models\ProjectProgressAttachment;
use App\Models\ProjectProgressReport;
use App\Models\ProjectProgressUpdate;
use App\Models\User;
use App\Services\ProjectMonitoringService;
use App\Services\ProjectProgressService;
use App\Services\ProjectReportService;
use Carbon\Carbon;
use Database\Seeders\ProgressTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * SP3: tanda siap aktiviti, dokumen sokongan dan laporan kemajuan bulanan (audit SP0 J-SP3; keputusan L.1,
 * L.3, I.8, I.10, L.7).
 *
 * Nota: SQLite in-memory; kunci baris (lockForUpdate) tidak dapat dibuktikan secara serentak.
 */
class ProjectProgressTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData, OpensJtictMeeting;

    private Agency $agency;

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

        Storage::fake(ProjectProgressAttachment::DISK);
        $this->travelTo(Carbon::parse('2027-05-20 10:00'));
        $this->seed(ProgressTemplateSeeder::class);

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $other = Agency::create(['code' => 'JPN', 'name' => 'Agensi JPN', 'category' => 'SUK', 'is_active' => true]);
        $btm = Agency::create(['code' => 'BTM', 'name' => 'Bahagian Teknologi Maklumat', 'category' => 'SUK', 'is_active' => true]);

        $this->primary = $this->user('pengguna_biasa', ['name' => 'Ahmad Pegawai Utama']);
        $this->secondary = $this->user('pengguna_biasa', ['name' => 'Bakar Pegawai Kedua']);
        $this->colleague = $this->user('pengguna_biasa', ['name' => 'Chong Rakan Agensi']);
        $this->outsider = $this->user('pengguna_biasa', ['name' => 'Dina Agensi Lain', 'agency_id' => $other->id]);
        $this->urusSetia = $this->user('pentadbir_urus_setia', ['name' => 'Siti Urus Setia', 'agency_id' => $btm->id]);
        $this->pengurusan = $this->user('pengurusan', ['name' => 'Encik Pengurusan', 'agency_id' => $btm->id]);
        $this->superadmin = $this->user('superadmin', ['name' => 'Super Admin', 'agency_id' => $btm->id]);
    }

    // --- TANDA SIAP (L.1, L.3, I.8) ---

    public function test_required_document_must_be_uploaded_before_activity_is_completed(): void
    {
        $project = $this->project();
        $activity = $this->activity($project, 1); // Penyediaan Dokumen Tender: dokumen wajib

        $this->assertTrue($activity->requires_document);
        $this->assertValidationError(fn () => $this->progress()->completeActivity($activity, $this->primary, '2027-02-10', null), 'document');
        $this->assertFalse($activity->fresh()->is_completed);
        $this->assertFalse($project->fresh()->isStructureLocked());

        $attachment = $this->progress()->uploadAttachment($project, $this->primary, $this->pdf(), activity: $activity);
        Storage::disk(ProjectProgressAttachment::DISK)->assertExists($attachment->file_path);
        $this->assertStringStartsWith("projects/{$project->uuid}/progress/", $attachment->file_path);
        $this->assertSame($activity->document_label, $attachment->document_label);

        $this->progress()->completeActivity($activity, $this->secondary, '2027-02-10', 'Dokumen tender diluluskan.');

        $activity->refresh();
        $this->assertTrue($activity->is_completed);
        $this->assertSame('2027-02-10', $activity->actual_completion_date->toDateString());
        $this->assertSame($this->secondary->id, $activity->completed_by);
        $this->assertNotNull($activity->completed_at);

        $update = ProjectProgressUpdate::sole();
        $this->assertSame([ProgressUpdateType::Completed, '2027-02-10', 'Dokumen tender diluluskan.', $this->secondary->id],
            [$update->change_type, $update->completion_date->toDateString(), $update->remarks, $update->updated_by]);

        // L.3: tanda siap pertama mengunci struktur
        $this->assertTrue($project->fresh()->isStructureLocked());
        $this->assertFalse($this->primary->can('updateStructure', $project->fresh()));
    }

    public function test_activity_without_required_document_can_be_completed_directly(): void
    {
        $project = $this->project();
        $training = $this->activity($project, 9); // Latihan / TOT

        $this->assertFalse($training->requires_document);
        $this->progress()->completeActivity($training, $this->primary, '2027-05-01', null);

        $this->assertTrue($training->fresh()->is_completed);
    }

    public function test_completion_date_is_required_and_not_in_the_future(): void
    {
        $project = $this->project();
        $training = $this->activity($project, 9);

        $this->assertValidationError(fn () => $this->progress()->completeActivity($training, $this->primary, '', null), 'completionDate');
        $this->assertValidationError(fn () => $this->progress()->completeActivity($training, $this->primary, '2027-05-21', null), 'completionDate');
        $this->assertValidationError(fn () => $this->progress()->completeActivity($training, $this->primary, '2027-02-30', null), 'completionDate');

        $this->progress()->completeActivity($training, $this->primary, '2027-05-20', null);
        $this->assertValidationError(fn () => $this->progress()->completeActivity($training, $this->primary, '2027-05-20', null), 'activity');
        $this->assertSame(1, ProjectProgressUpdate::count());
    }

    public function test_uncompleting_requires_remarks_and_keeps_history(): void
    {
        $project = $this->project();
        $training = $this->activity($project, 9);
        $this->progress()->completeActivity($training, $this->primary, '2027-04-30', null);

        $this->assertValidationError(fn () => $this->progress()->uncompleteActivity($training, $this->primary, '  '), 'remarks');

        $this->progress()->uncompleteActivity($training, $this->secondary, 'Latihan belum dilaksanakan sepenuhnya.');

        $training->refresh();
        $this->assertFalse($training->is_completed);
        $this->assertNull($training->actual_completion_date);
        $this->assertNull($training->completed_by);

        $history = ProjectProgressUpdate::orderBy('id')->get();
        $this->assertSame([ProgressUpdateType::Completed, ProgressUpdateType::Uncompleted], $history->pluck('change_type')->all());
        $this->assertSame('2027-04-30', $history[1]->completion_date->toDateString()); // tarikh yang dibatalkan
        $this->assertSame('Latihan belum dilaksanakan sepenuhnya.', $history[1]->remarks);

        // Struktur kekal dikunci (hanya Urus Setia membuka semula)
        $this->assertTrue($project->fresh()->isStructureLocked());
        $this->assertValidationError(fn () => $this->progress()->uncompleteActivity($training, $this->primary, 'Lagi'), 'activity');

        // Append-only
        $this->assertThrows(fn () => $history[0]->update(['remarks' => 'Ubah']), LogicException::class);
        $this->assertThrows(fn () => $history[0]->delete(), LogicException::class);
    }

    public function test_only_active_officers_can_update_progress(): void
    {
        $project = $this->project();
        $training = $this->activity($project, 9);

        foreach (['Rakan seagensi' => $this->colleague, 'Agensi lain' => $this->outsider, 'Urus Setia' => $this->urusSetia, 'Pengurusan' => $this->pengurusan, 'Superadmin' => $this->superadmin] as $label => $user) {
            $this->assertThrows(fn () => $this->progress()->completeActivity($training, $user, '2027-05-01', null), AuthorizationException::class, null, $label);
            $this->assertThrows(fn () => $this->progress()->uploadAttachment($project, $user, $this->pdf(), activity: $training), AuthorizationException::class, null, $label);
            $this->assertThrows(fn () => $this->reports()->create($project, $user, 2027, 4), AuthorizationException::class, null, $label);
        }

        $this->assertFalse($training->fresh()->is_completed);
        $this->assertSame(0, ProjectProgressAttachment::count());
        $this->assertSame(0, ProjectProgressReport::count());
    }

    // --- DOKUMEN (I.8) ---

    public function test_only_pdf_up_to_25_mb_is_accepted(): void
    {
        $project = $this->project();
        $activity = $this->activity($project, 1);

        $renamed = UploadedFile::fake()->createWithContent('palsu.pdf', 'Ini bukan PDF sebenar.');
        $this->assertValidationError(fn () => $this->progress()->uploadAttachment($project, $this->primary, $renamed, activity: $activity), 'upload');

        $word = UploadedFile::fake()->create('dokumen.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertValidationError(fn () => $this->progress()->uploadAttachment($project, $this->primary, $word, activity: $activity), 'upload');

        $tooLarge = UploadedFile::fake()->createWithContent('besar.pdf', "%PDF-1.4\n" . str_repeat('A', 25601 * 1024));
        $this->assertValidationError(fn () => $this->progress()->uploadAttachment($project, $this->primary, $tooLarge, activity: $activity), 'upload');

        $this->assertSame(0, ProjectProgressAttachment::count());

        $this->progress()->uploadAttachment($project, $this->primary, $this->pdf(), activity: $activity);
        $this->assertSame(1, ProjectProgressAttachment::count());
    }

    public function test_required_document_of_completed_activity_cannot_be_deleted(): void
    {
        $project = $this->project();
        $activity = $this->activity($project, 1);
        $document = $this->progress()->uploadAttachment($project, $this->primary, $this->pdf(), activity: $activity);
        $this->progress()->completeActivity($activity, $this->primary, '2027-02-10', null);

        $this->assertValidationError(fn () => $this->progress()->deleteAttachment($document, $this->primary), 'attachment');

        // Dokumen pengganti dimuat naik: dokumen lama boleh dipadam
        $this->progress()->uploadAttachment($project, $this->primary, $this->pdf('pengganti.pdf'), activity: $activity);
        $this->progress()->deleteAttachment($document, $this->primary);

        $this->assertSoftDeleted($document);
        Storage::disk(ProjectProgressAttachment::DISK)->assertExists($document->file_path); // fail kekal untuk audit
        $this->assertThrows(fn () => $this->progress()->deleteAttachment($document->fresh() ?? $document, $this->colleague), AuthorizationException::class);
    }

    public function test_attachment_download_follows_project_access(): void
    {
        $project = $this->project();
        $document = $this->progress()->uploadAttachment($project, $this->primary, $this->pdf(), activity: $this->activity($project, 1));
        $url = route('projects.attachments.show', [$project->uuid, $document->uuid]);

        foreach ([$this->primary, $this->colleague, $this->urusSetia, $this->pengurusan] as $user) {
            $this->actingAs($user)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }

        $this->actingAs($this->outsider)->get($url)->assertForbidden();
        $this->actingAs($this->superadmin)->get($url)->assertForbidden();

        // Dokumen projek lain melalui UUID projek ini: 404
        $otherProject = $this->project();
        $this->actingAs($this->primary)->get(route('projects.attachments.show', [$otherProject->uuid, $document->uuid]))->assertNotFound();
    }

    // --- LAPORAN BULANAN (I.10, L.7) ---

    public function test_one_report_per_month_within_project_period(): void
    {
        $project = $this->project(); // tempoh bermula 01/01/2027; hari ini 20/05/2027

        $this->assertSame([[2027, 5], [2027, 4], [2027, 3], [2027, 2], [2027, 1]], $this->reports()->availablePeriods($project));

        $report = $this->reports()->create($project, $this->primary, 2027, 4);
        $this->assertSame(ProgressReportStatus::Draft, $report->status);
        $this->assertSame($this->primary->id, $report->created_by);

        $this->assertValidationError(fn () => $this->reports()->create($project, $this->secondary, 2027, 4), 'period');   // berganda
        $this->assertValidationError(fn () => $this->reports()->create($project, $this->primary, 2027, 6), 'period');     // masa hadapan
        $this->assertValidationError(fn () => $this->reports()->create($project, $this->primary, 2026, 12), 'period');    // sebelum projek
        $this->assertValidationError(fn () => $this->reports()->create($project, $this->primary, 2027, 13), 'period');

        $this->assertSame(1, ProjectProgressReport::count());
        $this->assertNotContains([2027, 4], $this->reports()->availablePeriods($project));
    }

    public function test_due_date_follows_first_meeting_deadline_on_or_after_month_end(): void
    {
        $project = $this->project();

        $this->meeting('2027-03-15', 'Bil. 1/2027');               // sebelum akhir Mac: tidak dikira bagi Mac
        $june = $this->meeting('2027-06-10', 'Bil. 3/2027');
        $may = $this->meeting('2027-05-05', 'Bil. 2/2027');
        $this->meeting('2027-08-01', 'Bil. 4/2027');
        $this->meeting('2027-04-30', 'Bil. 9/2027')->delete();    // dipadam: tidak dikira

        // Mac (akhir 31/03): 30/04 dipadam, jadi 05/05
        $march = $this->reports()->create($project, $this->primary, 2027, 3);
        $this->assertSame('2027-05-05', $march->due_date->toDateString());
        $this->assertSame($may->id, $march->due_meeting_id);

        // April (akhir 30/04): 05/05
        $april = $this->reports()->create($project, $this->primary, 2027, 4);
        $this->assertSame('2027-05-05', $april->due_date->toDateString());

        // Mei (akhir 31/05): 05/05 sudah sebelum akhir bulan, jadi 10/06
        $mayReport = $this->reports()->create($project, $this->primary, 2027, 5);
        $this->assertSame('2027-06-10', $mayReport->due_date->toDateString());
        $this->assertSame($june->id, $mayReport->due_meeting_id);

        // Tiada mesyuarat selepas Ogos: tiada tarikh akhir, tidak ditanda Lewat
        $this->travelTo(Carbon::parse('2027-10-05 09:00'));
        $september = $this->reports()->create($project, $this->primary, 2027, 9);
        $this->assertNull($september->due_date);
    }

    public function test_submitting_snapshots_activities_and_locks_the_report(): void
    {
        $project = $this->project();
        $this->meeting('2027-05-05', 'Bil. 2/2027'); // tarikh akhir laporan Mac telah berlalu

        $tender = $this->activity($project, 1);
        $this->progress()->uploadAttachment($project, $this->primary, $this->pdf(), activity: $tender);
        $this->progress()->completeActivity($tender, $this->primary, '2027-03-10', null);       // siap dalam Mac
        $training = $this->activity($project, 9);
        $this->progress()->completeActivity($training, $this->primary, '2027-04-15', null);     // siap selepas Mac

        $report = $this->reports()->create($project, $this->primary, 2027, 3);

        $this->assertValidationError(fn () => $this->reports()->submit($report, $this->primary, ['work_summary' => '  ']), 'work_summary');

        $this->reports()->saveDraft($report, $this->primary, ['work_summary' => 'Draf awal', 'issues_and_challenges' => 'Kelewatan pembekal']);
        $this->assertSame('Draf awal', $report->fresh()->work_summary);

        $this->reports()->submit($report, $this->secondary, [
            'work_summary' => 'Dokumen tender siap dan diluluskan.',
            'issues_and_challenges' => 'Kelewatan pembekal',
            'corrective_action_plan' => 'Mesyuarat bersama pembekal.',
        ]);

        $report->refresh()->load('activitySnapshots');
        $this->assertTrue($report->isSubmitted());
        $this->assertSame('2027-03-31', $report->evaluation_date->toDateString());
        $this->assertTrue($report->is_late);                    // dihantar 20/05 > 05/05
        $this->assertSame($this->secondary->id, $report->submitted_by);
        $this->assertNotNull($report->submitted_at);
        $this->assertSame(
            ApplicationOfficer::where('application_id', $project->application_id)->where('user_id', $this->secondary->id)->value('id'),
            $report->application_officer_id,
        );
        $this->assertNull($report->actual_progress_percent); // dikira dalam SP4

        $this->assertCount(10, $report->activitySnapshots);
        $rows = $report->activitySnapshots->keyBy('sequence');
        $this->assertTrue($rows[1]->is_completed);
        $this->assertSame('2027-03-10', $rows[1]->actual_completion_date->toDateString());
        $this->assertFalse($rows[9]->is_completed);             // siap selepas tarikh penilaian
        $this->assertNull($rows[9]->actual_completion_date);
        $this->assertTrue($rows[1]->is_planned_due);            // sasaran 15/01
        $this->assertTrue($rows[3]->is_planned_due);            // sasaran 15/03
        $this->assertFalse($rows[4]->is_planned_due);           // sasaran 15/04
        $this->assertSame('5.00', $rows[1]->weight);

        // Dikunci
        $this->assertValidationError(fn () => $this->reports()->saveDraft($report, $this->primary, ['work_summary' => 'Ubah']), 'report');
        $this->assertValidationError(fn () => $this->reports()->submit($report, $this->primary, ['work_summary' => 'Ubah']), 'report');
        $this->assertValidationError(fn () => $this->progress()->uploadAttachment($project, $this->primary, $this->pdf(), report: $report), 'upload');
        $this->assertThrows(fn () => $report->update(['work_summary' => 'Ubah']), LogicException::class);
        $this->assertThrows(fn () => $report->delete(), LogicException::class);
    }

    public function test_current_month_report_uses_today_and_is_not_late_without_passed_deadline(): void
    {
        $project = $this->project();
        $this->meeting('2027-06-10', 'Bil. 3/2027');

        $report = $this->reports()->create($project, $this->primary, 2027, 5);
        $this->reports()->submit($report, $this->primary, ['work_summary' => 'Kemajuan Mei.']);

        $report->refresh();
        $this->assertSame('2027-05-20', $report->evaluation_date->toDateString());
        $this->assertFalse($report->is_late);
    }

    // --- SKRIN (LIVEWIRE) ---

    public function test_officer_completes_activity_and_submits_report_through_the_screens(): void
    {
        $project = $this->project();
        $tender = $this->activity($project, 1);

        $component = Livewire::actingAs($this->primary)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->call('openActivityModal', $tender->uuid)
            ->set('completionDate', '2027-02-10')
            ->call('completeActivity')
            ->assertHasErrors('document');

        // Muat naik melalui modal diuji dalam test_document_upload_through_activity_modal (tanpa perjalanan masa).
        $this->progress()->uploadAttachment($project, $this->primary, $this->pdf(), activity: $tender);

        $component->call('completeActivity')
            ->assertHasNoErrors()
            ->assertSet('showActivityModal', false)
            ->assertSee('Ditanda Siap');

        $this->assertTrue($tender->fresh()->is_completed);

        $page = Livewire::actingAs($this->primary)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->set('reportPeriod', '2027-4')
            ->call('createReport');
        $report = ProjectProgressReport::sole();
        $page->assertRedirect(route('projects.reports.show', [$project->uuid, $report->uuid]));

        Livewire::actingAs($this->primary)->test(ProgressReportPage::class, ['project' => $project->uuid, 'report' => $report->uuid])
            ->call('submit')
            ->assertHasErrors('work_summary')
            ->set('work_summary', 'Kerja bulan April.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Pencapaian Aktiviti');

        $this->assertTrue($report->fresh()->isSubmitted());

        // Paparan baca sahaja
        $this->actingAs($this->pengurusan)->get(route('projects.reports.show', [$project->uuid, $report->uuid]))->assertOk()->assertSee('Kerja bulan April.')->assertDontSee('Hantar Laporan');
        $this->actingAs($this->outsider)->get(route('projects.reports.show', [$project->uuid, $report->uuid]))->assertForbidden();
        $this->actingAs($this->pengurusan)->get(route('projects.show', $project->uuid))->assertDontSee('Tanda Siap')->assertDontSee('Cipta Laporan');
    }

    public function test_document_upload_through_activity_modal(): void
    {
        // Pembersihan fail sementara Livewire (> 24 jam) memadam fail ujian jika masa dikembara; guna masa sebenar.
        $this->travelBack();
        $project = $this->project();
        $tender = $this->activity($project, 1);

        Livewire::actingAs($this->primary)->test(ShowProject::class, ['uuid' => $project->uuid])
            ->call('openActivityModal', $tender->uuid)
            ->set('upload', UploadedFile::fake()->createWithContent('palsu.pdf', 'Bukan PDF'))
            ->call('uploadActivityDocument')
            ->assertHasErrors('upload')
            ->set('upload', $this->pdf('sst.pdf'))
            ->call('uploadActivityDocument')
            ->assertHasNoErrors()
            ->assertSee('sst.pdf');

        $this->assertSame(['sst.pdf'], $tender->attachments()->pluck('file_name')->all());
    }

    // --- PEMBANTU ---

    private function progress(): ProjectProgressService
    {
        return app(ProjectProgressService::class);
    }

    private function reports(): ProjectReportService
    {
        return app(ProjectReportService::class);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['agency_id' => $this->agency->id]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Projek Templat D Tidak Berfasa: 01/01/2027 – 31/12/2027, tarikh sasaran aktiviti ke-n = 15 hari bulan n.
     */
    private function project(): Project
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2027/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->primary->id,
            'title' => "Projek Kemajuan {$this->sequence}",
            'procurement_type_id' => $this->masterId(ProcurementType::class, 'pembangunan_aplikasi'),
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

        $dates = [];
        foreach (ProgressTemplate::where('code', 'D')->sole()->activities as $index => $activity) {
            $dates[$activity->id] = sprintf('2027-%02d-15', $index + 1);
        }

        return app(ProjectMonitoringService::class)->start($application->fresh(), $this->primary, [
            'is_phased' => false,
            'planned_start_date' => '2027-01-01',
            'planned_end_date' => '2027-12-31',
            'phases' => [['name' => '', 'activities' => $dates]],
        ]);
    }

    private function activity(Project $project, int $sequence): ProjectProgressActivity
    {
        return $project->activities()->where('sequence', $sequence)->sole();
    }

    private function pdf(string $name = 'dokumen.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    private function meeting(string $deadline, string $series)
    {
        return $this->openJtictMeeting(['series' => $series, 'progress_update_deadline' => $deadline]);
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
