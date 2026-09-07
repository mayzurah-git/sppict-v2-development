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

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full">
    <!-- Session Status -->
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="mb-8 animate-fade-in">
        <h1 class="text-2xl font-black text-slate-700 tracking-tight dark:text-slate-100">
            Log Masuk
        </h1>
        <p class="text-base md:text-lg text-slate-600 dark:text-slate-400 font-medium">
            Selamat Datang. Sila masukkan maklumat akaun anda
        </p>
    </div>

    <!-- Login Form -->
    <form wire:submit="login" class="space-y-5" novalidate autocomplete="off">

        <!-- Email Address -->
        <div class="group">
            <x-input-label for="email"
                class="block text-sm md:text-base font-semibold text-slate-700 dark:text-slate-300 mb-2"
                :value="__('E-mel')" />
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-4">
                    <svg class="h-5 w-5 text-slate-400 transition-colors group-focus-within:text-indigo-600"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5H4.5a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91A2.25 2.25 0 0 1 2.25 6.993V6.75" />
                    </svg>
                </div>
                <x-text-input wire:model="form.email" id="email"
                    class="w-full pl-11 pr-4 py-3 rounded-lg text-sm md:text-base focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition duration-200" type="email"
                    name="email" placeholder="nama@organisasi.com" />
            </div>
            <x-input-error :messages="$errors->get('form.email')" class="mt-2 text-sm" />
        </div>

        <!-- Password -->
        <div class="group">
            <x-input-label for="password"
                class="block text-sm md:text-base font-semibold text-slate-700 dark:text-slate-300 mb-2"
                :value="__('Kata Laluan')" />
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-4">
                    <svg class="h-5 w-5 text-slate-400 transition-colors group-focus-within:text-blue-700"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 5.25a3 3 0 1 1 4.243 4.243l-8.493 8.493H8.25V14.75l8.493-8.493Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m18 7.5-1.5-1.5" />
                    </svg>
                </div>
                <x-text-input wire:model="form.password" id="password"
                    class="w-full pl-11 pr-4 py-3 rounded-lg text-sm md:text-base focus:border-blue-700 focus:ring-2 focus:ring-blue-700/20 outline-none transition duration-200"
                    type="password" name="password" autocomplete="current-password" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-2 text-sm" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Ingat saya') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}" wire:navigate>
                    {{ ('Lupa Kata Laluan?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ 'Log Masuk' }}
            </x-primary-button>
        </div>
    </form>
</div>
