<?php

namespace App\Enums;

/**
 * Status akaun pengguna. Self Register bermula sebagai Pending sehingga diluluskan Urus Setia.
 */
enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';

    /**
     * UM11: status AKAUN (bukan status permohonan pendaftaran) bagi akaun yang pernah aktif dan
     * dinyahaktifkan oleh Urus Setia/Superadmin. Sebab disimpan dalam `users.inactive_reason`.
     */
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Kelulusan',
            self::Active => 'Aktif',
            self::Rejected => 'Ditolak',
            self::Inactive => 'Tidak Aktif',
        };
    }

    /**
     * Mesej kepada pengguna yang akaunnya belum/tidak aktif (log masuk disekat).
     */
    public function inactiveMessage(): ?string
    {
        return match ($this) {
            self::Pending => 'Akaun anda sedang menunggu kelulusan Urus Setia.',
            self::Rejected => 'Pendaftaran akaun anda tidak diluluskan. Sila hubungi Urus Setia.',
            self::Inactive => 'Akaun anda tidak aktif. Sila hubungi Urus Setia untuk maklumat lanjut.',
            self::Active => null,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
