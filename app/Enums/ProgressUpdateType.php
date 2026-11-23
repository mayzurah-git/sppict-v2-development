<?php

namespace App\Enums;

/**
 * SP3: jenis perubahan dalam sejarah kemajuan aktiviti (audit SP0 F.6; keputusan L.1).
 */
enum ProgressUpdateType: string
{
    case Completed = 'COMPLETED';
    case Uncompleted = 'UNCOMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Ditanda Siap',
            self::Uncompleted => 'Tanda Siap Dibatalkan',
        };
    }
}
