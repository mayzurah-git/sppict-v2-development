<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * UM11A: rekod `users.last_login_at` pada setiap log masuk berjaya akaun Aktif — termasuk log
 * masuk automatik melalui kuki "Ingat saya" (SessionGuard turut mencetuskan event Login).
 *
 * Dikemas kini terus melalui query (tanpa event model) supaya tidak menjana rekod audit bagi
 * setiap log masuk. Kegagalan merekod tidak menghalang log masuk.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        // Akaun pending/rejected/inactive dilog keluar serta-merta; jangan dikira sebagai aktiviti.
        if (! method_exists($user, 'isActive') || ! $user->isActive()) {
            return;
        }

        try {
            DB::table('users')->where('id', $user->getAuthIdentifier())->update(['last_login_at' => now()]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
