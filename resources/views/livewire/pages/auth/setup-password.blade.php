<?php

use App\Livewire\Concerns\RateLimitsActions;
use App\Support\PasswordPolicyMessages;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/**
 * UM13: Tetapkan Kata Laluan melalui pautan e-mel (broker `account_setup`, sah 72 jam) bagi akaun
 * yang didaftar atau diset semula oleh pentadbir. Token sekali guna; dipadam selepas berjaya.
 */
new #[Layout('layouts.guest')] class extends Component
{
    use RateLimitsActions;

    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    public function setupPassword(): void
    {
        // Had kadar penghantaran borang bagi setiap IP (10 / 15 minit).
        $this->enforceRateLimit($this->rateLimitKey('password-setup-submit', (string) request()->ip()), 10, 900, 'email');

        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], PasswordPolicyMessages::forField('password', 'Kata Laluan'), [
            'password' => 'Kata Laluan',
        ]);

        $status = Password::broker('account_setup')->reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            // Mesej seragam (tidak membezakan e-mel tidak berdaftar dengan token tidak sah/tamat tempoh).
            $this->addError('email', 'Pautan Tetapkan Kata Laluan tidak sah atau telah tamat tempoh. Sila hubungi Urus Setia untuk pautan baharu.');

            return;
        }

        Session::flash('status', 'Kata laluan anda telah ditetapkan. Sila log masuk.');

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Tetapkan kata laluan untuk akaun SPPICT anda.') }}
    </div>

    <form wire:submit="setupPassword">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('E-mel Rasmi')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Kata Laluan')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Pengesahan Kata Laluan')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                          type="password"
                          name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Tetapkan Kata Laluan') }}
            </x-primary-button>
        </div>
    </form>
</div>
