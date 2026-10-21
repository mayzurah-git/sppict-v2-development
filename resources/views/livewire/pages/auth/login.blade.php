<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        // Kata laluan sementara: terus ke halaman Tukar Kata Laluan (turut dikuatkuasakan oleh middleware `password.changed`).
        if (auth()->user()->must_change_password) {
            $this->redirectRoute('password.change', navigate: true);

            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

@php
    // Ralat `form.email` dengan e-mel yang sah formatnya datang daripada pengesahan akaun
    // (kelayakan salah, sekatan cubaan, akaun tidak aktif): papar sebagai kotak amaran, bukan di bawah medan.
    $authError = $errors->has('form.email') && filter_var($form->email, FILTER_VALIDATE_EMAIL)
        ? $errors->first('form.email')
        : null;
    $emailFieldError = $authError ? null : $errors->first('form.email');
    $inputBase = 'block w-full h-12 pl-11 pr-11 rounded-[10px] bg-white text-[15px] font-medium text-slate-900 placeholder:text-slate-400 shadow-none transition focus:ring-4';
    $inputOk = 'border-slate-300 focus:border-green-700 focus:ring-green-700/15';
    $inputBad = 'border-red-700 focus:border-red-700 focus:ring-red-700/15';
@endphp

<div class="w-full flex flex-col gap-7">
    <x-auth-session-status class="p-3.5 rounded-[10px] bg-green-50 border border-green-200 text-green-800" :status="session('status')" />

    <div class="flex flex-col gap-2">
        <h2 class="text-3xl font-black tracking-tight text-slate-900">Log Masuk</h2>
        <p class="text-base text-slate-600">Selamat datang. Sila masukkan maklumat akaun anda.</p>
    </div>

    @if ($authError)
        <div role="alert" class="flex gap-3 p-4 rounded-[10px] bg-red-50 border border-red-200 text-red-800">
            <svg aria-hidden="true" class="flex-none w-5 h-5 mt-px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16.5v.01" /></svg>
            <span class="flex flex-col gap-0.5 text-sm">
                <strong class="font-bold">Log masuk tidak berjaya</strong>
                <span class="leading-relaxed">{{ $authError }}</span>
            </span>
        </div>
    @endif

    <form wire:submit="login" class="flex flex-col gap-5" novalidate>
        <!-- E-mel -->
        <div class="flex flex-col gap-2">
            <label for="email" class="text-sm font-bold text-slate-700">E-mel</label>
            <div class="relative">
                <svg aria-hidden="true" class="pointer-events-none absolute left-3.5 top-3.5 w-5 h-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
                <input wire:model="form.email" id="email" type="email" name="email" autocomplete="username" placeholder="nama@ns.gov.my"
                    @class([$inputBase, $inputBad => $errors->has('form.email'), $inputOk => ! $errors->has('form.email')])
                    @if ($errors->has('form.email')) aria-invalid="true" @endif />
            </div>
            @if ($emailFieldError)
                <p class="text-[13px] font-semibold text-red-700">{{ $emailFieldError }}</p>
            @endif
        </div>

        <!-- Kata Laluan -->
        <div class="flex flex-col gap-2" x-data="{ show: false }">
            <div class="flex justify-between items-baseline gap-3">
                <label for="password" class="text-sm font-bold text-slate-700">Kata Laluan</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-semibold text-green-900 hover:text-green-950 hover:underline">Lupa kata laluan?</a>
                @endif
            </div>
            <div class="relative">
                <svg aria-hidden="true" class="pointer-events-none absolute left-3.5 top-3.5 w-5 h-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                <input wire:model="form.password" id="password" name="password" autocomplete="current-password" placeholder="••••••••"
                    :type="show ? 'text' : 'password'" type="password"
                    @class([$inputBase, $inputBad => $errors->has('form.password'), $inputOk => ! $errors->has('form.password')])
                    @if ($errors->has('form.password')) aria-invalid="true" @endif />
                <button type="button" @click="show = ! show" :aria-label="show ? 'Sembunyikan kata laluan' : 'Tunjukkan kata laluan'" aria-label="Tunjukkan kata laluan"
                    class="absolute right-1 top-0.5 w-11 h-11 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-700">
                    <svg x-show="! show" aria-hidden="true" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12s4-6 9-6 9 6 9 6-4 6-9 6-9-6-9-6z" /><circle cx="12" cy="12" r="3" /></svg>
                    <svg x-show="show" x-cloak style="display: none" aria-hidden="true" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18" /><path d="M10.6 6.1A9.8 9.8 0 0 1 12 6c5 0 9 6 9 6a16 16 0 0 1-2.6 3.2M6.2 7.6C4.1 9.1 3 12 3 12s4 6 9 6a8.6 8.6 0 0 0 3.9-.9" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" /></svg>
                </button>
            </div>
            @error('form.password')
                <p class="text-[13px] font-semibold text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <!-- Ingat Saya -->
        <label for="remember" class="inline-flex items-center gap-2.5 min-h-8 text-sm text-slate-600 cursor-pointer">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember" class="w-[18px] h-[18px] rounded border-slate-300 text-green-900 focus:ring-green-700">
            Ingat saya
        </label>

        <button type="submit" wire:loading.attr="disabled" wire:target="login"
            class="h-12 w-full flex items-center justify-center gap-2.5 rounded-[10px] bg-green-900 text-white text-[15px] font-bold tracking-[0.01em] transition hover:bg-green-800 focus:outline-none focus-visible:ring-[3px] focus-visible:ring-amber-500 focus-visible:ring-offset-2 disabled:bg-[#4b6b58] disabled:cursor-progress">
            <svg wire:loading wire:target="login" aria-hidden="true" class="w-[18px] h-[18px] animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 3a9 9 0 1 0 9 9" /></svg>
            <span wire:loading.remove wire:target="login">Log Masuk</span>
            <span wire:loading wire:target="login">Mengesahkan…</span>
        </button>
    </form>

    <!-- MyDigital ID: dipaparkan sahaja; integrasi sebenar belum dibangunkan. -->
    <div class="flex items-center gap-3.5 -my-1">
        <span class="flex-1 h-px bg-slate-200"></span>
        <span class="text-[13px] font-semibold text-slate-500">atau</span>
        <span class="flex-1 h-px bg-slate-200"></span>
    </div>

    <div class="flex flex-col gap-2">
        <button type="button" disabled aria-describedby="mydid-note"
            class="h-12 w-full flex items-center justify-center gap-3 rounded-[10px] border border-slate-300 bg-white text-[15px] font-semibold text-slate-700 shadow-sm cursor-not-allowed opacity-70">
            <span>Log masuk dengan</span>
            <img src="{{ asset('images/mydigital-id.png') }}" alt="MyDigital ID" class="w-[116px] h-8 object-cover" />
        </button>
        <p id="mydid-note" class="text-xs text-center text-slate-500">Log masuk melalui MyDigital ID akan datang.</p>
    </div>

    @if (Route::has('register'))
        <div class="flex flex-col gap-3 items-center text-center">
            <p class="text-sm text-slate-600">
                Belum mempunyai akaun?
                <a href="{{ route('register') }}" wire:navigate class="font-bold text-green-900 hover:text-green-950 hover:underline">Daftar di sini</a>
            </p>
            <p class="text-xs leading-relaxed text-slate-500 max-w-[340px]">Akaun baharu perlu diluluskan oleh Urus Setia sebelum boleh log masuk.</p>
        </div>
    @endif
</div>
