<?php

use App\Support\PasswordPolicyMessages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            // UM7A: polisi berpusat + kata laluan baharu mesti berbeza daripada kata laluan semasa.
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
            ], PasswordPolicyMessages::forField('password', 'Kata Laluan Baharu') + [
                'current_password.current_password' => 'Kata Laluan Semasa tidak tepat.',
            ], [
                'current_password' => 'Kata Laluan Semasa',
                'password' => 'Kata Laluan Baharu',
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    public function with(): array
    {
        return ['theme' => \App\Support\RoleTheme::for(Auth::user())];
    }
}; ?>

@php
    $field = 'block w-full h-11 rounded-xl border-slate-300 text-[15px] text-slate-900 shadow-none ' . $theme['input'];
    $label = 'block text-[13px] font-bold text-slate-700';
@endphp

{{-- Senarai syarat & meter kekuatan hanya panduan di pelayar; pengesahan sebenar ialah Password::defaults() di pelayan. --}}
<section aria-labelledby="tajuk-katalaluan" class="bg-white border border-slate-200 rounded-[18px] overflow-hidden"
    x-data="{
        password: $wire.entangle('password'),
        confirmation: $wire.entangle('password_confirmation'),
        show: false,
        get checks() {
            const pw = this.password ?? '';
            return [
                { label: 'Sekurang-kurangnya 12 aksara', ok: pw.length >= 12 },
                { label: 'Huruf besar (A–Z)', ok: /\p{Lu}/u.test(pw) },
                { label: 'Huruf kecil (a–z)', ok: /\p{Ll}/u.test(pw) },
                { label: 'Nombor (0–9)', ok: /\d/.test(pw) },
                { label: 'Simbol (cth. ! @ # $)', ok: /[\p{Z}\p{S}\p{P}]/u.test(pw) },
            ];
        },
        get score() { return this.checks.filter((c) => c.ok).length },
        get tone() { return this.score >= 5 ? 'bg-green-600' : (this.score >= 3 ? 'bg-amber-500' : 'bg-red-600') },
        get strength() {
            if (! this.password) return { text: 'Masukkan kata laluan baharu', cls: 'text-slate-500' };
            if (this.score >= 5) return { text: 'Kuat — memenuhi semua syarat', cls: 'text-green-700' };
            return this.score >= 3 ? { text: 'Sederhana', cls: 'text-amber-700' } : { text: 'Lemah', cls: 'text-red-700' };
        },
        get mismatch() { return (this.confirmation ?? '') !== '' && this.confirmation !== this.password },
    }">
    <form wire:submit="updatePassword">
        <header class="px-6 pt-5 flex items-start gap-3.5">
            <span aria-hidden="true" class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center {{ $theme['bar'] }} {{ $theme['accent'] }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
            </span>
            <div>
                <h2 id="tajuk-katalaluan" class="text-[17px] font-extrabold text-slate-900">{{ __('Tukar Kata Laluan') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Kata laluan baharu mesti berbeza daripada kata laluan semasa.') }}</p>
            </div>
        </header>

        <div class="px-6 pt-5 pb-6 flex flex-wrap gap-6">
            <div class="flex-1 basis-[300px] min-w-0 space-y-4">
                <div class="space-y-1.5">
                    <label for="update_password_current_password" class="{{ $label }}">{{ __('Kata Laluan Semasa') }}</label>
                    <input wire:model="current_password" id="update_password_current_password" name="current_password" :type="show ? 'text' : 'password'" type="password" autocomplete="current-password" class="{{ $field }}" />
                    <x-input-error :messages="$errors->get('current_password')" />
                </div>

                <div class="space-y-1.5">
                    <label for="update_password_password" class="{{ $label }}">{{ __('Kata Laluan Baharu') }}</label>
                    <input x-model="password" id="update_password_password" name="password" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" aria-describedby="kekuatan-katalaluan syarat-katalaluan" class="{{ $field }}" />
                    <div aria-hidden="true" class="grid grid-cols-5 gap-1 pt-1">
                        <template x-for="i in 5" :key="i">
                            <span class="h-[5px] rounded-full" :class="i <= score ? tone : 'bg-slate-200'"></span>
                        </template>
                    </div>
                    <p id="kekuatan-katalaluan" class="text-xs font-bold" :class="strength.cls" x-text="strength.text">Masukkan kata laluan baharu</p>
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <div class="space-y-1.5">
                    <label for="update_password_password_confirmation" class="{{ $label }}">{{ __('Pengesahan Kata Laluan Baharu') }}</label>
                    <input x-model="confirmation" id="update_password_password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" type="password" autocomplete="new-password" class="{{ $field }}" />
                    <p x-show="mismatch" x-cloak style="display: none" class="text-xs font-semibold text-red-700">{{ __('Pengesahan tidak sepadan dengan Kata Laluan Baharu.') }}</p>
                    <x-input-error :messages="$errors->get('password_confirmation')" />
                </div>

                <label class="inline-flex items-center gap-2 min-h-8 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" x-model="show" class="w-[18px] h-[18px] rounded border-slate-300 {{ $theme['input'] }}" />
                    {{ __('Papar kata laluan') }}
                </label>
            </div>

            <div id="syarat-katalaluan" class="flex-1 basis-60 min-w-0 self-start rounded-[14px] border px-[18px] py-4 {{ $theme['panel'] }}">
                <p class="mb-2.5 text-[13px] font-extrabold">{{ __('Syarat kata laluan') }}</p>
                <ul class="space-y-2">
                    <template x-for="check in checks" :key="check.label">
                        <li class="flex items-center gap-2 text-sm" :class="check.ok ? 'text-green-900' : 'text-slate-600'">
                            <span aria-hidden="true" class="w-5 h-5 shrink-0 rounded-full flex items-center justify-center text-white" :class="check.ok ? 'bg-green-600' : 'bg-slate-300'">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10" /></svg>
                            </span>
                            <span x-text="check.label"></span>
                            <span class="sr-only" x-text="check.ok ? '(dipenuhi)' : '(belum dipenuhi)'"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div class="px-6 py-3.5 bg-slate-50/70 border-t border-slate-100 flex flex-wrap items-center justify-end gap-3">
            <x-action-message class="me-auto inline-flex items-center gap-1.5 text-sm font-bold text-green-700" on="password-updated">
                <svg aria-hidden="true" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" /></svg>
                {{ __('Kata laluan dikemas kini.') }}
            </x-action-message>
            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword"
                class="min-h-11 px-6 rounded-xl border bg-white text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 {{ $theme['buttonOutline'] }}">
                {{ __('Tukar Kata Laluan') }}
            </button>
        </div>
    </form>
</section>
