<?php

namespace App\Support;

use App\Models\User;

/**
 * Skema warna antara muka mengikut peranan (bar menu, pautan, aksen papan pemuka & halaman Profil):
 * Superadmin indigo, Urus Setia hijau, Pengguna Biasa biru langit. Peranan lain menggunakan
 * skema lalai (hijau SPPICT).
 *
 * Kelas Tailwind ditulis penuh di sini supaya dikesan oleh `content` dalam tailwind.config.js.
 */
class RoleTheme
{
    private const THEMES = [
        'superadmin' => [
            'bar' => 'bg-indigo-950',
            'active' => 'border-indigo-300 text-white',
            'idle' => 'border-transparent text-indigo-200 hover:text-white',
            'muted' => 'text-indigo-200',
            'accent' => 'text-indigo-300',
            'avatar' => 'bg-indigo-300 text-indigo-950',
            'focus' => 'focus-visible:ring-indigo-300',
            'mobileIdle' => 'text-indigo-200 hover:text-white hover:bg-white/5',
            'link' => 'text-indigo-800',
            'canvas' => 'bg-indigo-50/50',
            'soft' => 'bg-indigo-50 text-indigo-800',
            'eyebrow' => 'text-indigo-800',
            'button' => 'bg-indigo-900 hover:bg-indigo-950 text-white focus-visible:ring-indigo-500',
            'buttonOutline' => 'border-indigo-900 text-indigo-900 hover:bg-indigo-50 focus-visible:ring-indigo-500',
            'input' => 'focus:border-indigo-700 focus:ring-indigo-700/20',
            'inputWithin' => 'focus-within:border-indigo-700 focus-within:ring-indigo-700/20',
            'panel' => 'bg-indigo-50 border-indigo-100 text-indigo-950',
            'dots' => 'text-indigo-400',
        ],
        'pengguna_biasa' => [
            'bar' => 'bg-sky-700',
            'active' => 'border-yellow-200 text-white',
            'idle' => 'border-transparent text-sky-100 hover:text-white',
            'muted' => 'text-sky-200',
            'accent' => 'text-yellow-200',
            'avatar' => 'bg-yellow-200 text-sky-900',
            'focus' => 'focus-visible:ring-yellow-200',
            'mobileIdle' => 'text-sky-100 hover:text-white hover:bg-white/10',
            'link' => 'text-sky-700',
            'canvas' => 'bg-sky-50/60',
            'soft' => 'bg-sky-50 text-sky-700',
            'eyebrow' => 'text-sky-700',
            'button' => 'bg-sky-700 hover:bg-sky-800 text-white focus-visible:ring-sky-500',
            'buttonOutline' => 'border-sky-700 text-sky-700 hover:bg-sky-50 focus-visible:ring-sky-500',
            'input' => 'focus:border-sky-700 focus:ring-sky-700/20',
            'inputWithin' => 'focus-within:border-sky-700 focus-within:ring-sky-700/20',
            'panel' => 'bg-sky-50 border-sky-100 text-sky-950',
            'dots' => 'text-sky-400',
        ],
        'default' => [
            'bar' => 'bg-brand-dark',
            'active' => 'border-amber-400 text-white',
            'idle' => 'border-transparent text-brand-soft hover:text-white',
            'muted' => 'text-brand-muted',
            'accent' => 'text-amber-400',
            'avatar' => 'bg-amber-400 text-brand-dark',
            'focus' => 'focus-visible:ring-amber-400',
            'mobileIdle' => 'text-brand-soft hover:text-white hover:bg-white/5',
            'link' => 'text-green-900',
            'canvas' => 'bg-brand-canvas',
            'soft' => 'bg-green-50 text-green-800',
            'eyebrow' => 'text-green-800',
            'button' => 'bg-green-800 hover:bg-green-900 text-white focus-visible:ring-green-600',
            'buttonOutline' => 'border-green-800 text-green-800 hover:bg-green-50 focus-visible:ring-green-600',
            'input' => 'focus:border-green-700 focus:ring-green-700/20',
            'inputWithin' => 'focus-within:border-green-700 focus-within:ring-green-700/20',
            'panel' => 'bg-green-50 border-green-100 text-green-950',
            'dots' => 'text-green-500',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function for(?User $user): array
    {
        foreach (['superadmin', 'pengguna_biasa'] as $role) {
            if ($user?->hasRole($role)) {
                return self::THEMES[$role];
            }
        }

        // Urus Setia, Pengurusan & lain-lain: hijau SPPICT.
        return self::THEMES['default'];
    }
}
