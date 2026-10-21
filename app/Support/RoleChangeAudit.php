<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Rekod audit OwenIt (custom event) bagi perubahan peranan Spatie, yang tidak diaudit secara
 * automatik oleh model User. Dipanggil dalam transaksi yang sama dengan perubahan peranan supaya
 * audit dibatalkan bersama jika transaksi gagal. Tiada kata laluan/token direkodkan.
 */
class RoleChangeAudit
{
    /**
     * @param  array<string, mixed>  $old  cth. ['roles' => ['pengurusan']]
     * @param  array<string, mixed>  $new  cth. ['roles' => ['pengguna_biasa']]
     */
    public static function record(User $user, string $event, array $old, array $new): void
    {
        $user->auditEvent = $event;
        $user->isCustomEvent = true;
        $user->auditCustomOld = $old;
        $user->auditCustomNew = $new;

        Event::dispatch(new AuditCustom($user));

        $user->isCustomEvent = false;
        $user->auditCustomOld = null;
        $user->auditCustomNew = null;
    }
}
