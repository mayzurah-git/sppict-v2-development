<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Concerns\RateLimitsActions;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use RateLimitsActions;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        // Halaman ini hanya untuk akaun dengan kata laluan sementara; pengguna lain guna Profil.
        if (! Auth::user()->must_change_password) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    /**
     * Tukar kata laluan sementara. Flag `must_change_password` dikosongkan hanya selepas
     * kata laluan baharu berjaya disimpan; status, peranan dan profil tidak disentuh.
     */
    public function updatePassword(): void
    {
        // Rekod semasa dari database, bukan nilai daripada browser.
        $user = User::query()->findOrFail(Auth::id());

        if (! $user->must_change_password) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        // UM7: had cubaan (meneka kata laluan semasa) bagi setiap akaun: 5 cubaan / 5 minit.
        $this->enforceRateLimit($this->rateLimitKey('change-password', (string) $user->id), 5, 300, 'current_password');

        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
            ], [
                'required' => ':attribute wajib diisi.',
                'current_password.current_password' => 'Kata Laluan Semasa tidak tepat.',
                'password.confirmed' => 'Pengesahan Kata Laluan Baharu tidak sepadan.',
                'password.different' => 'Kata Laluan Baharu mesti berbeza daripada kata laluan semasa.',
                'password.min' => 'Kata Laluan Baharu mesti sekurang-kurangnya :min aksara.',
                'password.letters' => 'Kata Laluan Baharu mesti mengandungi sekurang-kurangnya satu huruf.',
                'password.mixed' => 'Kata Laluan Baharu mesti mengandungi huruf besar dan huruf kecil.',
                'password.numbers' => 'Kata Laluan Baharu mesti mengandungi sekurang-kurangnya satu nombor.',
                'password.symbols' => 'Kata Laluan Baharu mesti mengandungi sekurang-kurangnya satu simbol.',
            ], [
                'current_password' => 'Kata Laluan Semasa',
                'password' => 'Kata Laluan Baharu',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
            ])->save();
        });

        // UM7: selaraskan pengguna dalam guard supaya AuthenticateSession menyimpan hash baharu bagi
        // sesi ini (sesi ini kekal), manakala sesi lain yang menggunakan kata laluan sementara terbatal.
        Auth::setUser($user);
        RateLimiter::clear($this->rateLimitKey('change-password', (string) $user->id));

        $this->reset('current_password', 'password', 'password_confirmation');

        Session::flash('status', 'Kata laluan berjaya ditukar.');

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="w-full">
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-700 tracking-tight dark:text-slate-100">Tukar Kata Laluan</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">
            Akaun anda menggunakan kata laluan sementara. Sila tetapkan kata laluan baharu sebelum meneruskan.
        </p>
    </div>

    <form wire:submit="updatePassword" class="space-y-4" novalidate>
        <div>
            <x-input-label for="current_password" value="Kata Laluan Semasa (sementara) *" />
            <x-text-input wire:model="current_password" id="current_password" class="block mt-1 w-full" type="password" autocomplete="current-password" autofocus />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Kata Laluan Baharu *" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Pengesahan Kata Laluan Baharu *" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="button" wire:click="logout" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Log Keluar
            </button>

            <x-primary-button wire:loading.attr="disabled" wire:target="updatePassword">
                Simpan Kata Laluan
            </x-primary-button>
        </div>
    </form>
</div>
