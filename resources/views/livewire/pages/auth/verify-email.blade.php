<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Concerns\RateLimitsActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use RateLimitsActions;

    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        // UM7: had kadar hantar semula (6 / minit bagi setiap pengguna, sama seperti `verification.send` Laravel).
        $this->enforceRateLimit($this->rateLimitKey('verification-resend', (string) Auth::id()), 6, 60, 'throttle');

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Sila sahkan alamat e-mel anda dengan mengklik pautan yang telah dihantar ke e-mel anda. Jika anda tidak menerima e-mel tersebut, anda boleh meminta pautan baharu.') }}
    </div>

    <x-input-error :messages="$errors->get('throttle')" class="mb-4" />

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ __('Pautan pengesahan baharu telah dihantar ke alamat e-mel yang didaftarkan.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <x-primary-button wire:click="sendVerification">
            {{ __('Hantar Semula E-mel Pengesahan') }}
        </x-primary-button>

        <button wire:click="logout" type="submit" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
            {{ __('Log Keluar') }}
        </button>
    </div>
</div>
