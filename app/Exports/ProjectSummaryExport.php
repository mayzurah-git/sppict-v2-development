<?php

namespace App\Exports;

use App\Enums\ScheduleStatus;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * SP5 (eksport C): Ringkasan Status Kemajuan Projek ke Excel (data sama dengan versi PDF).
 */
class ProjectSummaryExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    /** @param  array<string, mixed>  $summary  hasil ProjectSummaryService::build() */
    public function __construct(private array $summary)
    {
    }

    public function headings(): array
    {
        return [
            'Kod Agensi', 'Jabatan/Agensi', 'No. Rujukan', 'Nama Projek', 'Templat', 'Jenis Projek', 'Tarikh Mula',
            'Tarikh Tamat', 'Status Projek', 'Kemajuan Sebenar (%)', 'Kemajuan Dirancang (%)', 'Varians',
            'Status Jadual', 'Lampu', 'Laporan Terkini', 'Laporan Belum Dihantar', 'Menunggu JPICT', 'Jumlah Kos (RM)',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->summary['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                $rows[] = [
                    $group['agency'], $group['name'], $row['reference'], $row['title'], $row['template'], $row['phases'],
                    $row['start'], $row['end'], $row['status'], $row['actual'], $row['planned'] ?? '', $row['variance'] ?? '',
                    $row['schedule'], $row['indicator'] ? ScheduleStatus::indicatorLabel($row['indicator']) : '',
                    $row['latest_report'], $row['missing_reports'], $row['awaiting_jpict'] ? 'Ya' : 'Tidak', $row['cost'] ?? '',
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Ringkasan ' . $this->summary['date']->format('d-m-Y');
    }
}
