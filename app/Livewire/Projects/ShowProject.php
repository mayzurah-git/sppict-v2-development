<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Services\ProjectMonitoringService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * SP2: Maklumat Projek: ringkasan, struktur fasa / aktiviti dan jadual, penanda Menunggu JPICT, sejarah
 * pindaan jadual dan buka kunci struktur.
 *
 * Tindakan: Pinda Jadual (pegawai aktif, I.3), Buka Kunci Struktur dan Rekod JPICT (Urus Setia, L.3 / I.1).
 * Pengurusan dan Urus Setia melihat secara baca sahaja (I.9).
 */
#[Layout('layouts.app')]
class ShowProject extends Component
{
    #[Locked]
    public string $uuid;

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
            'progressTemplate',
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
        $this->showScheduleModal = $this->showUnlockModal = $this->showJpictModal = false;
        $this->resetErrorBag();
    }

    public function render()
    {
        // Kebenaran disemak semula pada setiap permintaan.
        $this->authorize('view', $this->project);

        return view('livewire.projects.show-project');
    }

    private function refreshProject(): void
    {
        unset($this->project, $this->scheduleHistory, $this->unlockHistory);
    }
}
