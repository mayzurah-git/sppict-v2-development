<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Notifications\AccountSetupLink;
use Illuminate\Support\Facades\Notification;

/**
 * UM13: ambil token pautan "Tetapkan Kata Laluan" terakhir yang dihantar (memerlukan Notification::fake()).
 */
trait CapturesSetupLinks
{
    private function sentToken(User $user, ?bool $isReset = null): string
    {
        $token = null;

        Notification::assertSentTo($user, AccountSetupLink::class, function (AccountSetupLink $notification) use (&$token, $isReset): bool {
            if ($isReset !== null && $notification->isReset !== $isReset) {
                return false;
            }

            $token = $notification->token;

            return true;
        });

        return $token;
    }
}
