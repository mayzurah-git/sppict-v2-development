<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Penjana kata laluan sementara berpusat (UM7A).
 *
 * Kata laluan yang dijana sentiasa mematuhi polisi berpusat `Password::defaults()` (min 12,
 * huruf besar & kecil, nombor, simbol) supaya tiada laluan penetapan kata laluan memintas polisi.
 * Digunakan oleh pendaftaran Urus Setia, Pengurusan Pengguna Superadmin, arahan provisioning
 * Superadmin dan seeder akaun demo. Pengguna wajib menukarnya (`must_change_password`).
 */
class TemporaryPassword
{
    public static function generate(int $length = 16): string
    {
        do {
            $password = Str::password(max($length, 12));
        } while (! self::satisfiesPolicy($password));

        return $password;
    }

    public static function satisfiesPolicy(string $password): bool
    {
        return validator(['password' => $password], ['password' => Password::default()])->passes();
    }
}
