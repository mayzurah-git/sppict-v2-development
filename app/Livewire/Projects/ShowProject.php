<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\ProjectProgressActivity;
use App\Models\ProjectProgressAttachment;
use App\Services\ProgressCalculator;
use App\Services\ProjectClosureService;
use App\Services\ProjectMonitoringService;
use App\Support\Progress\ProgressResult;
use App\Services\ProjectProgressService;
use App\Services\ProjectReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * SP2: Maklumat Projek: ringkasan, struktur fasa / aktiviti dan jadual, penanda Menunggu JPICT, sejarah
 * pindaan jadual dan buka kunci struktur.
 * SP3: tanda siap / batal tanda aktiviti, dokumen sokongan aktiviti, sejarah kemas kini aktiviti dan
 * senarai laporan kemajuan bulanan.
 *
 * Tindakan: Pinda Jadual dan kemas kini kemajuan (pegawai aktif, I.3 / I.11), Buka Kunci Struktur dan Rekod
 * JPICT (Urus Setia, L.3 / I.1). Pengurusan dan Urus Setia melihat secara baca sahaja (I.9).
 */
#[Layout('layouts.app')]
class ShowProject extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $uuid;

    // SP3: Kemas Kini Aktiviti (tanda siap / batal tanda / dokumen)
    public bool $showActivityModal = false;

    #[Locked]
    public ?string $activityUuid = null;

    public string $completionDate = '';

    public string $activityRemarks = '';

    public $upload = null;

    // SP3: Cipta laporan bulanan ("tahun-bulan")
    public string $reportPeriod = '';

    // SP4: Penutupan projek (request | withdraw | approve | reject | terminate)
    public bool $showClosureModal = false;

    #[Locked]
    public string $closureAction = '';

    public string $closureRemarks = '';

    /** Tindakan penutupan => [ability policy, kaedah service, mesej berjaya]. */
    private const CLOSURE_ACTIONS = [
        'request' => ['requestClosure', 'request', 'Permohonan penutupan telah dihantar kepada Urus Setia.'],
        'withdraw' => ['withdrawClosure', 'withdraw', 'Permohonan penutupan telah ditarik balik.'],
        'approve' => ['decideClosure', 'approve', 'Penutupan projek telah disahkan. Projek kini Selesai.'],
        'reject' => ['decideClosure', 'reject', 'Permohonan penutupan telah ditolak. Projek kembali Dalam Pelaksanaan.'],
        'terminate' => ['terminate', 'terminate', 'Projek telah ditutup tanpa siap.'],
    ];

    // Pinda Jadual
    public bool $showScheduleModal = false;

    public string $plannedStartDate = '';

    public string $plannedEndDate = '';

    /** @var array<string, string> uuid aktiviti => tarikh sasaran */
    public array $targetDates = [];

    public string $justification = '';

    // Buka Kunci Struktur
    public bool $showUnlockModal = false;

    public string $unlockRemarks = '';

    // Rekod JPICT
    public bool $showJpictModal = false;

    public string $jpictDate = '';

    public string $jpictReference = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->authorize('view', $this->project);
    }

    #[Computed]
    public function project(): Project
    {
        return Project::with([
            'application.agency:id,code,name',
            'application.primaryOfficer',
            'application.secondaryOfficer',
            'application.latestJtictDecision',
            'agency:id,code,name',
            'progressTemplate.procurementType',
            'closer:id,name',
            'phases.activities',
            'starter:id,name',
            'jpictRecorder:id,name',
        ])->where('uuid', $this->uuid)->firstOrFail();
    }

    /**
     * Sejarah pindaan jadual dikumpulkan mengikut pindaan (satu justifikasi).
     */
    #[Computed]
    public function scheduleHistory(): Collection
    {
        return $this->project->scheduleChanges()->with(['changer:id,name', 'activity.phase'])->get()->groupBy('batch_uuid');
    }

    #[Computed]
    public function unlockHistory(): Collection
    {
        return $this->project->structureUnlocks()->with('unlocker:id,name')->get();
    }

    /** SP4: kemajuan pada hari ini, dikira secara langsung (bukan nilai cache). */
    #[Computed]
    public function progress(): ProgressResult
    {
        return app(ProgressCalculator::class)->forProject($this->project, today());
    }

    /** SP3: dokumen aktiviti, dikumpulkan mengikut aktiviti. */
    #[Computed]
    public function activityAttachments(): Collection
    {
        return $this->project->attachments()->whereNotNull('project_progress_activity_id')
            ->with('uploader:id,name')->latest('id')->get()->groupBy('project_progress_activity_id');
    }

    /** SP3: sejarah tanda siap / batal tanda. */
    #[Computed]
    public function progressHistory(): Collection
    {
        return $this->project->progressUpdates()->with(['updater:id,name', 'activity.phase'])->get();
    }

    #[Computed]
    public function reports(): Collection
    {
        return $this->project->progressReports()->with('submitter:id,name')
            ->orderByDesc('report_year')->orderByDesc('report_month')->get();
    }

    /** SP3: bulan yang boleh dilaporkan (belum mempunyai laporan). */
    #[Computed]
    public function availablePeriods(): array
    {
        return app(ProjectReportService::class)->availablePeriods($this->project);
    }

    #[Computed]
    public function selectedActivity(): ?ProjectProgressActivity
    {
        return $this->activityUuid
            ? $this->project->activities()->with('phase')->where('uuid', $this->activityUuid)->first()
            : null;
    }

    // --- SP3: KEMAS KINI AKTIVITI ---

    public function openActivityModal(string $activityUuid): void
    {
        $this->authorize('manage', $this->project);

        $this->resetErrorBag();
        $this->activityUuid = $this->project->activities()->where('uuid', $activityUuid)->firstOrFail()->uuid;
        unset($this->selectedActivity);
        $this->completionDate = '';
        $this->activityRemarks = '';
        $this->upload = null;
        $this->showActivityModal = true;
    }

    public function uploadActivityDocument(ProjectProgressService $service): void
    {
        $this->authorize('manage', $this->project);
        $this->resetErrorBag();

        $activity = $this->selectedActivity ?? abort(404);

        if (! $this->guard(fn () => $service->uploadAttachment($this->project, Auth::user(), $this->upload, activity: $activity))) {
            return;
        }

        $this->upload = null;
        unset($this->activityAttachments);
        session()->flash('modal_message', 'Dokumen berjaya dimuat naik.');
    }

    public function deleteDocument(string $attachmentUuid, ProjectProgressService $service): void
    {
        $this->authorize('manage', $this->project);
        $this->resetErrorBag();

        $attachment = ProjectProgressAttachment::where('project_id', $this->project->id)->where('uuid', $attachmentUuid)->firstOrFail();

        if ($this->guard(fn () => $service->deleteAttachment($attachment, Auth::user()))) {
            unset($this->activityAttachments);
        }
    }

    public function completeActivity(ProjectProgressService $service): void
    {
        $this->authorize('manage', $this->project);
        $this->resetErrorBag();

        $activity = $this->selectedActivity ?? abort(404);

        if ($this->guard(fn () => $service->completeActivity($activity, Auth::user(), $this->completionDate, $this->activityRemarks))) {
            $this->showActivityModal = false;
            $this->refreshProject();
            session()->flash('message', "Aktiviti \"{$activity->name}\" telah ditanda siap.");
        }
    }

    public function uncompleteActivity(ProjectProgressService $service): void
    {
        $this->authorize('manage', $this->project);
        $this->resetErrorBag();

        $activity = $this->selectedActivity ?? abort(404);

        if ($this->guard(fn () => $service->uncompleteActivity($activity, Auth::user(), $this->activityRemarks))) {
            $this->showActivityModal = false;
            $this->refreshProject();
            session()->flash('message', "Tanda siap aktiviti \"{$activity->name}\" telah dibatalkan.");
        }
    }

    // --- SP4: PENUTUPAN PROJEK ---

    #[Computed]
    public function closureHistory(): Collection
    {
        return $this->project->closureEvents()->with('actor:id,name')->get();
    }

    #[Computed]
    public function closureBlocker(): ?string
    {
        return app(ProjectClosureService::class)->closureBlocker($this->project);
    }

    #[Computed]
    public function missingReportCount(): int
    {
        return app(ProjectClosureService::class)->missingReportCount($this->project);
    }

    public function openClosureModal(string $action): void
    {
        $definition = self::CLOSURE_ACTIONS[$action] ?? abort(404);
        $this->authorize($definition[0], $this->project);

        $this->resetErrorBag();
        $this->closureAction = $action;
        $this->closureRemarks = '';
        $this->showClosureModal = true;
    }

    public function submitClosure(ProjectClosureService $service): void
    {
        [$ability, $method, $message] = self::CLOSURE_ACTIONS[$this->closureAction] ?? abort(404);
        $this->authorize($ability, $this->project);
        $this->resetErrorBag();

        if ($this->guard(fn () => $service->{$method}($this->project, Auth::user(), $this->closureRemarks))) {
            $this->showClosureModal = false;
            $this->refreshProject();
            session()->flash('message', $message);
        }
    }

    // --- SP3: LAPORAN BULANAN ---

    public function createReport(ProjectReportService $service)
    {
        $this->authorize('manageReports', $this->project);
        $this->resetErrorBag();

        if (! preg_match('/^(\d{4})-(\d{1,2})$/', $this->reportPeriod, $match)) {
            $this->addError('period', 'Sila pilih bulan laporan.');

            return null;
        }

        $report = null;
        if (! $this->guard(function () use ($service, $match, &$report) {
            $report = $service->create($this->project, Auth::user(), (int) $match[1], (int) $match[2]);
        })) {
            return null;
        }

        return $this->redirectRoute('projects.reports.show', ['project' => $this->project->uuid, 'report' => $report->uuid]);
    }

    // --- PINDA JADUAL ---

    public function openScheduleModal(): void
    {
        $this->authorize('manage', $this->project);

        $this->resetErrorBag();
        $this->plannedStartDate = $this->project->planned_start_date?->toDateString() ?? '';
        $this->plannedEndDate = $this->project->planned_end_date?->toDateString() ?? '';
        $this->targetDates = $this->project->phases
            ->flatMap(fn ($phase) => $phase->activities)
            ->mapWithKeys(fn ($activity) => [$activity->uuid => $activity->target_date?->toDateString() ?? ''])
            ->all();
        $this->justification = '';
        $this->showScheduleModal = true;
    }

    public function saveSchedule(ProjectMonitoringService $service): void
    {
        $this->authorize('manage', $this->project);
        $this->resetErrorBag();

        try {
            $count = $service->amendSchedule($this->project, Auth::user(), [
                'planned_start_date' => $this->plannedStartDate,
                'planned_end_date' => $this->plannedEndDate,
                'activities' => $this->targetDates,
            ], $this->justification);
        } catch (AuthorizationException $e) {
            $this->showScheduleModal = false;
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->showScheduleModal = false;
        $this->refreshProject();
        session()->flash('message', "Jadual projek berjaya dipinda ({$count} perubahan tarikh direkodkan).");
    }

    // --- BUKA KUNCI STRUKTUR (URUS SETIA) ---

    public function openUnlockModal(): void
    {
        $this->authorize('unlockStructure', $this->project);

        $this->resetErrorBag();
        $this->unlockRemarks = '';
        $this->showUnlockModal = true;
    }

    public function unlockStructure(ProjectMonitoringService $service): void
    {
        $this->authorize('unlockStructure', $this->project);
        $this->resetErrorBag();

        try {
            $service->unlockStructure($this->project, Auth::user(), $this->unlockRemarks);
        } catch (AuthorizationException $e) {
            $this->showUnlockModal = false;
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->showUnlockModal = false;
        $this->refreshProject();
        session()->flash('message', 'Struktur projek telah dibuka semula. Pegawai projek boleh meminda fasa dan aktiviti.');
    }

    // --- REKOD JPICT (URUS SETIA) ---

    public function openJpictModal(): void
    {
        $this->authorize('recordJpict', $this->project);

        $this->resetErrorBag();
        $this->jpictDate = $this->project->jpict_approved_at?->toDateString() ?? '';
        $this->jpictReference = (string) $this->project->jpict_reference;
        $this->showJpictModal = true;
    }

    public function saveJpict(ProjectMonitoringService $service): void
    {
        $this->authorize('recordJpict', $this->project);
        $this->resetErrorBag();

        try {
            $service->recordJpict($this->project, Auth::user(), $this->jpictDate, $this->jpictReference);
        } catch (AuthorizationException $e) {
            $this->showJpictModal = false;
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->showJpictModal = false;
        $this->refreshProject();
        session()->flash('message', 'Kelulusan JPICT telah direkodkan.');
    }

    public function closeModals(): void
    {
        $this->showScheduleModal = $this->showUnlockModal = $this->showJpictModal = $this->showActivityModal = $this->showClosureModal = false;
        $this->upload = null;
        $this->resetErrorBag();
    }

    /**
     * Jalankan tindakan service; AuthorizationException dipaparkan sebagai mesej (ValidationException
     * dikendalikan oleh Livewire). Pulangkan true jika berjaya.
     */
    private function guard(callable $action): bool
    {
        try {
            $action();
        } catch (AuthorizationException $e) {
            $this->closeModals();
            $this->refreshProject();
            session()->flash('error', $e->getMessage());

            return false;
        }

        return true;
    }

    public function render()
    {
        // Kebenaran disemak semula pada setiap permintaan.
        $this->authorize('view', $this->project);

        return view('livewire.projects.show-project');
    }

    private function refreshProject(): void
    {
        unset($this->project, $this->progress, $this->closureHistory, $this->closureBlocker, $this->missingReportCount, $this->scheduleHistory, $this->unlockHistory, $this->activityAttachments,
            $this->progressHistory, $this->reports, $this->availablePeriods, $this->selectedActivity);
    }
}
