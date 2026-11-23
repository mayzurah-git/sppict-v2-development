<?php

namespace App\Enums;

/**
 * SP3: status laporan kemajuan bulanan (audit SP0 F.4). Laporan Dihantar dikunci.
 */
enum ProgressReportStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Dihantar',
        };
    }
}
