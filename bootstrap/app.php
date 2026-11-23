<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'not-role' => \App\Http\Middleware\EnsureUserDoesNotHaveRole::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
        ]);

        // UM7: sesi disahkan terhadap hash kata laluan semasa. Apabila kata laluan ditukar/direset,
        // sesi lain (termasuk kuki "Ingat saya") dibatalkan pada permintaan seterusnya. Sesi yang
        // melakukan pertukaran dikemas kini secara automatik pada akhir permintaan yang sama.
        // Dalam kumpulan `web` supaya turut meliputi `/livewire/update`.
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
