<?php

namespace App\Enums;

/**
 * SP2: status aktiviti projek, diterbitkan (tidak disimpan) daripada tanda siap dan tarikh sasaran
 * pada tarikh penilaian (audit SP0 F.3).
 */
enum ProjectActivityStatus: string
{
    case Completed = 'COMPLETED';
    case Late = 'LATE';
    case Pending = 'PENDING';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai',
            self::Late => 'Lewat',
            self::Pending => 'Belum Siap',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Completed => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            self::Late => 'bg-red-50 text-red-800 ring-red-200',
            self::Pending => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}
