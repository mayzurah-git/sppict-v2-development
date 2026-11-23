<?php

namespace App\Enums;

/**
 * SP4: jenis peristiwa dalam sejarah penutupan projek (keputusan I.6).
 */
enum ClosureEventType: string
{
    case Requested = 'REQUESTED';
    case Withdrawn = 'WITHDRAWN';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Terminated = 'TERMINATED';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Penutupan Dimohon',
            self::Withdrawn => 'Permohonan Penutupan Ditarik Balik',
            self::Approved => 'Penutupan Disahkan (Selesai)',
            self::Rejected => 'Permohonan Penutupan Ditolak',
            self::Terminated => 'Projek Ditutup Tanpa Siap',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'text-emerald-800',
            self::Rejected, self::Terminated => 'text-red-800',
            default => 'text-slate-800',
        };
    }
}
