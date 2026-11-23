<?php

namespace App\Enums;

/**
 * UM11: sebab akaun pengguna ditandakan Tidak Aktif (`users.inactive_reason`).
 */
enum InactiveReason: string
{
    case Retired = 'retired';
    case TransferredOut = 'transferred_out';
    case InactiveOver6Months = 'inactive_over_6_months';

    public function label(): string
    {
        return match ($this) {
            self::Retired => 'Bersara',
            self::TransferredOut => 'Bertukar Keluar',
            self::InactiveOver6Months => 'Tidak Aktif Melebihi 6 Bulan',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
