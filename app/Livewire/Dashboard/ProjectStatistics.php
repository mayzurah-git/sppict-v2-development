<?php

namespace App\Livewire\Dashboard;

use App\Models\Meeting;
use App\Models\Project;
use App\Services\DashboardStatisticsService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * SP5: statistik papan pemuka (keputusan pemilik 23/11/2026, audit SP0 L.9).
 *
 * - Pengurusan ($full = true): (a) statistik permohonan, (b) projek mengikut status, (c) ringkasan agensi,
 *   (d) kos projek Lulus JTICT mengikut agensi, (e) projek lewat. Paparan interaktif (reka bentuk diluluskan
 *   23/11/2026): penapis tahun, ukuran kos/bilangan, tapisan lampu, susunan jadual agensi.
 * - Urus Setia ($full = false): (b), (c), (e).
 * Penapis tahun terpakai pada angka utama, (a) dan (d).
 */
class ProjectStatistics extends Component
{
    #[Locked]
    public bool $full = false;

    public string $year = '';

    /** (d) Ukuran carta agensi: 'cost' atau 'count'. */
    public string $measure = 'cost';

    /** (e) Tapisan projek lewat mengikut lampu: '', 'AMBER' atau 'RED'. */
    public string $lamp = '';

    /** (a) Pecahan butiran permohonan: 'agency' atau 'category'. */
    public string $breakdown = 'agency';

    /** (c) Susunan jadual ringkasan agensi. */
    public string $sort = 'late';

    public string $direction = 'desc';

    private const SORTABLE = ['label', 'total', 'active', 'late', 'average'];

    public function mount(bool $full = false): void
    {
        Gate::authorize('viewStatistics', Project::class);
        $this->full = $full;
    }

    public function setYear(string $year): void
    {
        $this->year = ctype_digit($year) ? $year : '';
    }

    public function setMeasure(string $measure): void
    {
        $this->measure = $measure === 'count' ? 'count' : 'cost';
    }

    public function setLamp(string $lamp): void
    {
        $this->lamp = in_array($lamp, ['AMBER', 'RED'], true) && $this->lamp !== $lamp ? $lamp : '';
    }

    public function setBreakdown(string $breakdown): void
    {
        $this->breakdown = $breakdown === 'category' ? 'category' : 'agency';
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : ($column === 'label' ? 'asc' : 'desc');
        $this->sort = $column;
    }

    public function render(DashboardStatisticsService $statistics)
    {
        Gate::authorize('viewStatistics', Project::class);

        $year = ctype_digit($this->year) ? (int) $this->year : null;
        $agencySummary = $statistics->projectSummaryByAgency();
        $lateProjects = $statistics->lateProjects();

        if (! $this->full) {
            return view('livewire.dashboard.project-statistics', [
                'statistics' => $statistics,
                'selectedYear' => $year,
                'years' => [],
                'byYear' => collect(),
                'byAgency' => collect(),
                'byCategory' => collect(),
                'costByAgency' => collect(),
                'projectStatus' => $statistics->projectsByStatus(),
                'agencySummary' => $agencySummary,
                'lateProjects' => $lateProjects,
            ]);
        }

        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'late';
        $agencySummary = $agencySummary->sortBy(fn (array $row) => $sort === 'label' ? $row['label'] : ($row[$sort] ?? -1), SORT_REGULAR, $this->direction === 'desc')->values();

        $applications = $year === null ? $statistics->applications() : $statistics->applications()->where('year', $year);
        $approved = $applications->where('outcome', 'approved');

        return view('livewire.dashboard.management-overview', [
            'selectedYear' => $year,
            'years' => $statistics->years(),
            'meeting' => Meeting::currentJtict()?->load(['meetingName', 'meetingNumber', 'meetingYear']),
            'kpi' => [
                'applications' => $applications->count(),
                'approved' => $approved->count(),
                'approvedCost' => (float) $approved->sum('total'),
            ],
            'byYear' => $statistics->applicationsByYear()->sortKeys()->values(),
            'breakdownRows' => $this->breakdown === 'category' ? $statistics->applicationsByCategory($year) : $statistics->applicationsByAgency($year),
            'costByAgency' => $statistics->approvedCostByAgency($year)
                ->sortByDesc($this->measure === 'count' ? 'count' : 'cost')->values(),
            'projectStatus' => $statistics->projectsByStatus(),
            'agencySummary' => $agencySummary,
            'lateProjects' => $this->lamp === ''
                ? $lateProjects
                : $lateProjects->filter(fn (Project $project) => \App\Enums\ScheduleStatus::indicatorFor((float) $project->variance_percent) === $this->lamp)->values(),
            'lateTotal' => $lateProjects->count(),
        ]);
    }
}
