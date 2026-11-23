<?php

namespace App\Enums;

/**
 * Sebab tamat tempoh tugasan pegawai bertanggungjawab.
 */
enum OfficerEndReason: string
{
    case TransferOut = 'TRANSFER_OUT';
    case Retirement = 'RETIREMENT';
    case Promotion = 'PROMOTION';
    case Reassignment = 'REASSIGNMENT';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::TransferOut => 'Bertukar Keluar Jabatan/Agensi',
            self::Retirement => 'Bersara',
            self::Promotion => 'Kenaikan Pangkat',
            self::Reassignment => 'Penyusunan Semula Tugas',
            self::Other => 'Lain-lain',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
