<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserDoesNotHaveRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Permission\Middleware\RoleMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Middleware route tidak diulang untuk permintaan Livewire secara lalai. Daftarkan supaya setiap
        // `/livewire/update` daripada halaman dilindungi disemak semula: status akaun aktif (UM7),
        // kata laluan sementara (UM6) dan peranan route (`role:` / `not-role:`) (UM7).
        Livewire::addPersistentMiddleware([
            EnsureUserIsActive::class,
            EnsurePasswordIsChanged::class,
            RoleMiddleware::class,
            EnsureUserDoesNotHaveRole::class,
        ]);

        // Polisi kata laluan berpusat (UM7): digunakan oleh Self Register, Tukar Kata Laluan wajib,
        // reset kata laluan melalui e-mel dan kemas kini kata laluan di Profil (`Password::defaults()`).
        // Kata laluan sedia ada tidak terjejas; polisi hanya dikenakan apabila kata laluan baharu ditetapkan.
        Password::defaults(fn () => Password::min(12)
            ->max(255)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols());
    }
}
