<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akaun dengan kata laluan sementara (`must_change_password = true`) hanya boleh menggunakan
 * halaman Tukar Kata Laluan (route `password.change`) dan log keluar sehingga kata laluan ditukar.
 *
 * Turut didaftarkan sebagai Livewire persistent middleware (AppServiceProvider) supaya tindakan
 * Livewire pada halaman yang dilindungi juga disekat. Nilai flag dibaca daripada rekod pengguna
 * yang dimuatkan semula oleh guard pada setiap permintaan, bukan daripada input browser.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            // Permintaan Livewire/AJAX tidak boleh mengikut redirect HTML; tolak terus.
            if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
                abort(403, 'Anda perlu menukar kata laluan sementara sebelum meneruskan.');
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
