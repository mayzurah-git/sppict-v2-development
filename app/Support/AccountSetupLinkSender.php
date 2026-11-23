<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AccountSetupLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * UM13: jana token broker `account_setup` dan hantar pautan "Tetapkan Kata Laluan" melalui e-mel.
 *
 * - Token baharu membatalkan token lama pengguna yang sama (broker memadam rekod sedia ada).
 * - Kata laluan TIDAK diubah di sini; pemanggil yang memutuskan (daftar/reset menetapkan
 *   kata laluan rawak yang tidak didedahkan kepada sesiapa).
 * - Kegagalan e-mel ditangkap & dilog tanpa token; pemanggil memaparkan status "e-mel gagal".
 * - Audit: `setup_link_sent` / `setup_link_failed` (tiada token direkodkan).
 */
class AccountSetupLinkSender
{
    public static function send(User $user, bool $isReset = false): bool
    {
        $broker = Password::broker('account_setup');
        $token = $broker->createToken($user);

        try {
            $user->notifyNow(new AccountSetupLink($token, $isReset));
        } catch (Throwable $exception) {
            // Token tidak sah digunakan kerana pengguna tidak pernah menerimanya.
            $broker->deleteToken($user);

            Log::warning('UM13: e-mel pautan tetapan kata laluan gagal dihantar.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            RoleChangeAudit::record($user, 'setup_link_failed', [], ['purpose' => $isReset ? 'reset' : 'setup']);

            return false;
        }

        RoleChangeAudit::record($user, 'setup_link_sent', [], ['purpose' => $isReset ? 'reset' : 'setup']);

        return true;
    }

    /**
     * Syarat Jana Semula oleh Urus Setia: akaun Aktif, belum pernah log masuk, bukan Superadmin.
     */
    public static function canResend(User $user): bool
    {
        return $user->isActive()
            && $user->last_login_at === null
            && ! $user->hasRole('superadmin');
    }
}
