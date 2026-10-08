<?php

namespace App\Enums;

/**
 * Peranan pegawai bertanggungjawab bagi sesuatu permohonan/projek.
 */
enum OfficerRole: string
{
    case Primary = 'PRIMARY';
    case Secondary = 'SECONDARY';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Pegawai Utama',
            self::Secondary => 'Pegawai Kedua / Alternatif',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
