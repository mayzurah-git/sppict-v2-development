<?php

use App\Livewire\Concerns\RateLimitsActions;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use RateLimitsActions;

    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // UM7: had kadar berasaskan IP (10 / 15 minit) dan e-mel dinormalisasi (3 / 15 minit).
        $this->enforceRateLimit($this->rateLimitKey('password-reset-request:ip', (string) request()->ip()), 10, 900, 'email');
        $this->enforceRateLimit($this->rateLimitKey('password-reset-request:email', $this->email), 3, 900, 'email');

        // Broker Laravel turut mengehadkan 1 pautan / 60 saat bagi setiap akaun (`auth.passwords.users.throttle`).
        Password::sendResetLink($this->only('email'));

        // UM7: mesej seragam supaya tidak mendedahkan sama ada e-mel berdaftar atau sedang dihadkan.
        $this->reset('email');

        session()->flash('status', 'Jika e-mel tersebut berdaftar, pautan set semula kata laluan akan dihantar ke e-mel berkenaan.');
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Lupa kata laluan? Masukkan e-mel rasmi anda dan pautan untuk menetapkan semula kata laluan akan dihantar ke e-mel tersebut.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('E-mel Rasmi')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Hantar Pautan Set Semula Kata Laluan') }}
            </x-primary-button>
        </div>
    </form>
</div>
