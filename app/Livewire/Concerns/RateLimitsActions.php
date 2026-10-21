<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Had kadar bagi tindakan Livewire sensitif (UM7), mengikut corak `LoginForm`.
 *
 * Kunci limiter dibina daripada identiti yang dinormalisasi (huruf kecil) dan/atau IP/ID pengguna;
 * kata laluan tidak pernah dimasukkan ke dalam kunci. Mesej tidak mendedahkan sama ada akaun wujud.
 */
trait RateLimitsActions
{
    /**
     * Rekod satu cubaan; tolak dengan ralat validation jika had telah dicapai.
     *
     * @throws ValidationException
     */
    protected function enforceRateLimit(string $key, int $maxAttempts, int $decaySeconds, string $errorField): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                $errorField => $this->rateLimitMessage($key),
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    protected function rateLimitMessage(string $key): string
    {
        $seconds = RateLimiter::availableIn($key);
        $minutes = (int) ceil($seconds / 60);

        return $seconds > 60
            ? "Terlalu banyak cubaan. Sila cuba semula dalam {$minutes} minit."
            : "Terlalu banyak cubaan. Sila cuba semula dalam {$seconds} saat.";
    }

    /**
     * Kunci limiter berasaskan identiti dinormalisasi supaya perubahan huruf besar/kecil
     * atau ruang tidak memintas had.
     */
    protected function rateLimitKey(string $action, string ...$parts): string
    {
        $normalised = array_map(fn (string $part) => Str::transliterate(Str::lower(trim($part))), $parts);

        return $action . '|' . implode('|', $normalised);
    }
}
