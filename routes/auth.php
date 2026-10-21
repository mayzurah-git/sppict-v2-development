<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('register', 'pages.auth.register')
        ->name('register');

    // Self Register: makluman selepas pendaftaran (akaun menunggu kelulusan, tidak log masuk).
    Volt::route('register/diterima', 'pages.auth.registration-pending')
        ->name('register.pending');

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

// Tukar kata laluan sementara (UM6): satu-satunya halaman dalaman yang dibenarkan semasa
// `must_change_password = true`. Sengaja tanpa middleware `password.changed` (elak redirect loop).
Route::middleware(['auth', 'active'])->group(function () {
    Volt::route('tukar-kata-laluan', 'pages.auth.change-password')
        ->name('password.change');
});

Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
