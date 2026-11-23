<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya akaun berstatus aktif boleh menggunakan modul dalaman. Akaun yang menunggu
 * kelulusan atau ditolak dilog keluar dan dihantar semula ke halaman log masuk.
 * Role sahaja tidak dianggap bukti akaun telah diluluskan.
 *
 * Turut didaftarkan sebagai Livewire persistent middleware (AppServiceProvider) supaya
 * tindakan Livewire pada halaman dilindungi disemak semula pada setiap permintaan.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $message = $user->status?->inactiveMessage() ?? 'Akaun anda tidak aktif.';

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Permintaan Livewire/AJAX tidak boleh mengikut redirect HTML; tolak terus.
            if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
                abort(403, $message);
            }

            return redirect()->route('login')->with('status', $message);
        }

        return $next($request);
    }
}
