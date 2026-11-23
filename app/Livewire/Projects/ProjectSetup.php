<?php

namespace App\Livewire\Projects;

use App\Models\Application;
use App\Models\ProgressTemplate;
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
 * SP2: Mulakan Pemantauan (permohonan Lulus JTICT) dan Pinda Struktur (sebelum struktur dikunci).
 *
 * Borang: Berfasa / Tidak Berfasa (Templat D sahaja), bilangan fasa dan aktiviti setiap fasa, tarikh mula /
 * tamat projek (Mulakan sahaja) dan satu tarikh sasaran bagi setiap aktiviti. Semua peraturan disemak semula
 * oleh ProjectMonitoringService; pemberat tidak disimpan dalam sifat awam komponen.
 *
 * $phases: [ ['name' => string, 'activities' => [template_activity_id => ['selected' => bool, 'target_date' => 'Y-m-d']]] ]
 */
#[Layout('layouts.app')]
class ProjectSetup extends Component
{
    #[Locked]
    public ?string $applicationUuid = null;

    #[Locked]
    public ?string $projectUuid = null;

    public bool $isPhased = false;

    public string $plannedStartDate = '';

    public string $plannedEndDate = '';

    public array $phases = [];

    public function mount(?string $application = null, ?string $project = null): void
    {
        if ($project !== null) {
            $model = Project::where('uuid', $project)->firstOrFail();
            $this->authorize('updateStructure', $model);
            $this->projectUuid = $model->uuid;
            $this->fillFromProject($model);

            return;
        }

        $model = Application::where('uuid', (string) $application)->firstOrFail();
        $this->authorize('start', [Project::class, $model]);
        $this->applicationUuid = $model->uuid;
        $this->phases = [$this->blankPhase(selectAll: true)];
    }

    #[Computed]
    public function application(): Application
    {
        return $this->projectUuid
            ? $this->project->application
            : Application::with(['agency:id,code,name', 'procurementType'])->where('uuid', $this->applicationUuid)->firstOrFail();
    }

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectUuid
            ? Project::with(['application.agency:id,code,name', 'phases.activities'])->where('uuid', $this->projectUuid)->firstOrFail()
            : null;
    }

    #[Computed]
    public function template(): ?ProgressTemplate
    {
        $template = $this->project
            ? $this->project->progressTemplate
            : ProgressTemplate::activeFor($this->application->procurement_type_id);

        return $template?->load('activities');
    }

    /**
     * Tarikh sasaran aktiviti sedia ada, kunci "nombor_fasa:id_aktiviti_templat" (dipaparkan baca sahaja;
     * pindaan melalui Pinda Jadual dengan justifikasi).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function existingDates(): array
    {
        if (! $this->project) {
            return [];
        }

        $dates = [];
        foreach ($this->project->phases as $phase) {
            foreach ($phase->activities as $activity) {
                $dates[$phase->phase_number . ':' . $activity->progress_template_activity_id] = $activity->target_date?->toDateString() ?? '';
            }
        }

        return $dates;
    }

    /** Aktiviti yang telah ditanda siap tidak boleh dikeluarkan (selepas struktur dibuka semula). */
    #[Computed]
    public function completedKeys(): array
    {
        if (! $this->project) {
            return [];
        }

        $keys = [];
        foreach ($this->project->phases as $phase) {
            foreach ($phase->activities->where('is_completed', true) as $activity) {
                $keys[] = $phase->phase_number . ':' . $activity->progress_template_activity_id;
            }
        }

        return $keys;
    }

    public function updatedIsPhased(): void
    {
        if (! $this->template?->allows_phases) {
            $this->isPhased = false;
        }

        if ($this->isPhased) {
            if (count($this->phases) < 2) {
                $this->phases[] = $this->blankPhase();
            }
        } else {
            // Tidak Berfasa: satu fasa dengan semua aktiviti; tarikh fasa pertama dikekalkan.
            $first = $this->phases[0] ?? $this->blankPhase();
            $phase = $this->blankPhase(selectAll: true);
            foreach ($phase['activities'] as $id => $row) {
                $phase['activities'][$id]['target_date'] = $first['activities'][$id]['target_date'] ?? '';
            }
            $this->phases = [$phase];
        }

        $this->resetErrorBag();
    }

    public function addPhase(): void
    {
        if ($this->isPhased && count($this->phases) < Project::MAX_PHASES) {
            $this->phases[] = $this->blankPhase();
        }
    }

    public function removePhase(int $index): void
    {
        if ($this->isPhased && count($this->phases) > 2 && isset($this->phases[$index])) {
            unset($this->phases[$index]);
            $this->phases = array_values($this->phases);
            $this->resetErrorBag();
        }
    }

    public function save(ProjectMonitoringService $service)
    {
        $this->resetErrorBag();

        $input = [
            'is_phased' => $this->isPhased,
            'planned_start_date' => $this->plannedStartDate,
            'planned_end_date' => $this->plannedEndDate,
            'phases' => collect($this->phases)->map(fn (array $phase) => [
                'name' => $phase['name'] ?? '',
                'activities' => collect($phase['activities'] ?? [])
                    ->filter(fn (array $row) => ! empty($row['selected']))
                    ->map(fn (array $row) => $row['target_date'] ?? '')
                    ->all(),
            ])->all(),
        ];

        try {
            if ($this->projectUuid) {
                $this->authorize('updateStructure', $this->project);
                $project = $service->updateStructure($this->project, Auth::user(), $input);
                session()->flash('message', 'Struktur projek berjaya dikemas kini.');
            } else {
                $this->authorize('start', [Project::class, $this->application]);
                $project = $service->start($this->application, Auth::user(), $input);
                session()->flash('message', 'Pemantauan projek telah dimulakan.');
            }
        } catch (AuthorizationException $e) {
            session()->flash('error', $e->getMessage());

            return $this->redirectRoute('projects.index');
        }

        return $this->redirectRoute('projects.show', $project->uuid);
    }

    public function render()
    {
        return view('livewire.projects.project-setup', [
            'templateActivities' => $this->template?->activities ?? new Collection(),
        ]);
    }

    private function fillFromProject(Project $project): void
    {
        $project->loadMissing('phases.activities');

        $this->isPhased = (bool) $project->is_phased;
        $this->plannedStartDate = $project->planned_start_date?->toDateString() ?? '';
        $this->plannedEndDate = $project->planned_end_date?->toDateString() ?? '';

        $this->phases = $project->phases->map(function ($phase) {
            $row = $this->blankPhase();
            $row['name'] = (string) $phase->name;
            foreach ($phase->activities as $activity) {
                $row['activities'][$activity->progress_template_activity_id] = [
                    'selected' => true,
                    'target_date' => $activity->target_date?->toDateString() ?? '',
                ];
            }

            return $row;
        })->values()->all();
    }

    private function blankPhase(bool $selectAll = false): array
    {
        $activities = [];
        foreach (($this->template?->activities ?? []) as $activity) {
            $activities[$activity->id] = ['selected' => $selectAll, 'target_date' => ''];
        }

        return ['name' => '', 'activities' => $activities];
    }
}
