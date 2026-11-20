<?php

namespace App\Enums;

/**
 * SP1: status kitaran projek dalam pemantauan (disimpan dalam `projects.status`, VARCHAR).
 *
 * Berasingan daripada peratus kemajuan: kemajuan 100% tidak menjadikan projek Selesai secara automatik
 * (audit SP0 H.10, keputusan I.6).
 */
enum ProjectStatus: string
{
    case NotStarted = 'NOT_STARTED';
    case InProgress = 'IN_PROGRESS';
    case PendingClosure = 'PENDING_CLOSURE';
    case Completed = 'COMPLETED';
    case Closed = 'CLOSED';

    /** Rekod sejarah SPPICT v1 (Bank Projek): baca sahaja. */
    case Historical = 'HISTORICAL';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Belum Dimulakan',
            self::InProgress => 'Dalam Pelaksanaan',
            self::PendingClosure => 'Menunggu Pengesahan Penutupan',
            self::Completed => 'Selesai',
            self::Closed => 'Ditutup',
            self::Historical => 'Rekod Sejarah',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
