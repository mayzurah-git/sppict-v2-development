<?php

namespace App\Services;

use App\Enums\ProgressReportStatus;
use App\Enums\ProjectStatus;
use App\Models\Agency;
use App\Models\Project;
use App\Models\ProjectProgressReport;
use Illuminate\Support\Collection;

/**
 * SP5 (eksport C, audit SP0 L.9): Ringkasan Status Kemajuan Projek pada tarikh eksport, dikumpulkan
 * mengikut Jabatan/Agensi, untuk kertas mesyuarat.
 *
 * Projek aktif (Dalam Pelaksanaan, Menunggu Pengesahan Penutupan) dikira secara langsung dengan
 * ProgressCalculator pada hari ini (bukan nilai cache). Bilangan projek Selesai / Ditutup dipaparkan
 * sebagai ringkasan.
 */
class ProjectSummaryService
{
    public function __construct(
        private ProgressCalculator $calculator,
        private ProjectClosureService $closure,
    ) {
    }

    /**
     * @return array{
     *   date: \Carbon\CarbonInterface,
     *   agency: ?Agency,
     *   groups: Collection<int, array{agency: string, name: string, rows: list<array<string, mixed>>}>,
     *   totals: array{active: int, late: int, completed: int, closed: int}
     * }
     */
    public function build(?int $agencyId = null): array
    {
        $date = today();

        $projects = Project::query()
            ->where('status', '!=', ProjectStatus::Historical->value)
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->with(['application:id,uuid,reference_number,title,total_cost,estimated_cost', 'agency:id,code,name', 'progressTemplate:id,code,name', 'phases.activities'])
            ->get();

        $active = $projects->filter(fn (Project $project) => in_array($project->status, DashboardStatisticsService::ACTIVE_STATUSES, true));

        $latestReports = ProjectProgressReport::query()
            ->whereIn('project_id', $active->modelKeys())
            ->where('status', ProgressReportStatus::Submitted->value)
            ->orderByDesc('report_year')->orderByDesc('report_month')
            ->get(['project_id', 'report_year', 'report_month'])
            ->unique('project_id')
            ->keyBy('project_id');

        $late = 0;
        $groups = $active
            ->sortBy(fn (Project $project) => [$project->agency?->code ?? '', $project->application?->reference_number ?? ''])
            ->groupBy('agency_id')
            ->map(function (Collection $items) use ($date, $latestReports, &$late) {
                $agency = $items->first()->agency;

                $rows = $items->map(function (Project $project) use ($date, $latestReports, &$late) {
                    $result = $this->calculator->forProject($project, $date);
                    $variance = $result->variance();
                    if ($variance !== null && $variance < 0) {
                        $late++;
                    }
                    $report = $latestReports->get($project->id);

                    return [
                        'reference' => $project->application?->reference_number ?? $project->legacy_reference ?? '-',
                        'title' => $project->application?->title ?? '-',
                        'template' => $project->progressTemplate ? 'Templat ' . $project->progressTemplate->code : '-',
                        'phases' => $project->is_phased ? $project->phases->count() . ' fasa' : 'Tidak Berfasa',
                        'start' => $project->planned_start_date?->format('d/m/Y') ?? '-',
                        'end' => $project->planned_end_date?->format('d/m/Y') ?? '-',
                        'status' => $project->status->label(),
                        'actual' => $result->actual,
                        'planned' => $result->planned,
                        'variance' => $variance,
                        'schedule' => $result->status()?->label() ?? 'Tiada data rancangan',
                        'indicator' => $result->indicator(),
                        'latest_report' => $report ? ProjectProgressReport::MONTHS[$report->report_month] . ' ' . $report->report_year : 'Tiada',
                        'missing_reports' => $this->closure->missingReportCount($project),
                        'awaiting_jpict' => $project->isAwaitingJpict(),
                        'cost' => $project->totalCost(),
                    ];
                })->values()->all();

                return ['agency' => $agency?->code ?? 'Tidak dinyatakan', 'name' => $agency?->name ?? '', 'rows' => $rows];
            })
            ->values();

        return [
            'date' => $date,
            'agency' => $agencyId ? Agency::withTrashed()->find($agencyId) : null,
            'groups' => $groups,
            'totals' => [
                'active' => $active->count(),
                'late' => $late,
                'completed' => $projects->where('status', ProjectStatus::Completed)->count(),
                'closed' => $projects->where('status', ProjectStatus::Closed)->count(),
            ],
        ];
    }
}
