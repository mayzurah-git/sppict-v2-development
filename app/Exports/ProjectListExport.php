<?php

namespace App\Exports;

use App\Enums\ScheduleStatus;
use App\Models\Project;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * SP5 (eksport A, audit SP0 L.9): Senarai Projek ke Excel mengikut penapis skrin. Nilai kemajuan ialah
 * nilai cache (sama seperti skrin Senarai Projek).
 */
class ProjectListExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    /** @param  Collection<int, Project>  $projects */
    public function __construct(private Collection $projects)
    {
    }

    public function headings(): array
    {
        return [
            'No. Rujukan', 'Nama Projek', 'Kod Agensi', 'Jabatan/Agensi', 'Templat', 'Jenis Projek',
            'Tarikh Mula', 'Tarikh Tamat', 'Status Projek', 'Kemajuan Sebenar (%)', 'Kemajuan Dirancang (%)',
            'Varians', 'Status Jadual', 'Lampu', 'Menunggu JPICT', 'Jumlah Kos (RM)', 'Tarikh Penilaian',
        ];
    }

    public function array(): array
    {
        return $this->projects->map(function (Project $project) {
            $variance = $project->variance_percent === null ? null : (float) $project->variance_percent;

            return [
                $project->application?->reference_number ?? $project->legacy_reference ?? '',
                $project->application?->title ?? '',
                $project->agency?->code ?? '',
                $project->agency?->name ?? '',
                $project->progressTemplate ? 'Templat ' . $project->progressTemplate->code . ' – ' . $project->progressTemplate->name : '',
                $project->is_phased ? 'Berfasa (' . $project->phases_count . ' fasa)' : 'Tidak Berfasa',
                $project->planned_start_date?->format('d/m/Y') ?? '',
                $project->planned_end_date?->format('d/m/Y') ?? '',
                $project->status->label(),
                (float) $project->actual_progress_percent,
                $project->planned_progress_percent === null ? '' : (float) $project->planned_progress_percent,
                $variance ?? '',
                $variance === null ? 'Tiada data rancangan' : ScheduleStatus::fromVariance($variance)->label(),
                $variance === null ? '' : ScheduleStatus::indicatorLabel(ScheduleStatus::indicatorFor($variance)),
                $project->isAwaitingJpict() ? 'Ya' : 'Tidak',
                $project->totalCost() ?? '',
                $project->progress_evaluated_at?->format('d/m/Y') ?? '',
            ];
        })->all();
    }

    public function title(): string
    {
        return 'Senarai Projek';
    }
}
