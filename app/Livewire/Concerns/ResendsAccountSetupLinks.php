<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\AccountSetupLinkSender;
use Illuminate\Support\Facades\RateLimiter;

/**
 * UM13: Jana Semula pautan "Tetapkan Kata Laluan" oleh Urus Setia.
 *
 * Hanya bagi akaun Aktif yang belum pernah log masuk (bukan Superadmin). Kata laluan tidak diubah;
 * token lama dibatalkan. Komponen pengguna mesti menyediakan `ensureUrusSetia()`.
 */
trait ResendsAccountSetupLinks
{
    use RateLimitsActions;

    public function resendSetupLink(string $uuid): void
    {
        $this->ensureUrusSetia();

        $user = User::where('uuid', $uuid)->firstOrFail();

        abort_unless(AccountSetupLinkSender::canResend($user), 403);

        // Had kadar: 3 / 10 minit bagi akaun sasaran yang sama, 20 / 10 minit bagi setiap Urus Setia.
        $limits = [
            [$this->rateLimitKey('setup-link-resend:target', (string) auth()->id(), $user->uuid), 3],
            [$this->rateLimitKey('setup-link-resend:actor', (string) auth()->id()), 20],
        ];

        foreach ($limits as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                session()->flash('error', $this->rateLimitMessage($key) . ' Pautan tidak dijana semula.');

                return;
            }
        }

        foreach ($limits as [$key]) {
            RateLimiter::hit($key, 600);
        }

        $this->afterSetupLinkAttempt($user, AccountSetupLinkSender::send($user));
    }

    protected function afterSetupLinkAttempt(User $user, bool $sent): void
    {
        if ($sent) {
            session()->flash('message', "Pautan Tetapkan Kata Laluan baharu telah dihantar ke {$user->email}.");
        } else {
            session()->flash('error', "E-mel gagal dihantar kepada {$user->name} ({$user->email}). Sila cuba Jana Semula.");
        }
    }
}
