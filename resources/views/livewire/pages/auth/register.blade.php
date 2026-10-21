<?php

use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesUserProfileFields;
use App\Livewire\Concerns\RateLimitsActions;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    // Medan profil (No. KP, agensi, Jawatan → Skim → Gred, telefon, e-mel) dikongsi dengan pendaftaran oleh pentadbir.
    // Hanya medan borang didedahkan. Role dan status ditentukan oleh server semasa simpan.
    use HandlesUserProfileFields;
    use RateLimitsActions;

    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        // UM7: had kadar penghantaran borang bagi setiap IP (10 / 10 minit) termasuk cubaan yang gagal validation.
        $this->enforceRateLimit($this->rateLimitKey('self-register', (string) request()->ip()), 10, 600, 'throttle');

        $this->normalizeProfileFields();

        $validated = $this->validate($this->rules(), $this->messages(), $this->validationAttributes());

        $user = DB::transaction(function () use ($validated): User {
            $user = new User();
            $user->fill($this->profileAttributesForSave($validated) + [
                'password' => Hash::make($validated['password']),
            ]);
            // Self Register sentiasa bermula menunggu kelulusan Urus Setia.
            $user->forceFill(['status' => UserStatus::Pending])->save();
            $user->assignRole('pengguna_biasa');

            return $user;
        });

        event(new Registered($user));

        // Akaun belum aktif: tidak log masuk secara automatik.
        $this->redirect(route('register.pending', absolute: false), navigate: true);
    }

    protected function rules(): array
    {
        return $this->profileRules() + [
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ];
    }

    protected function messages(): array
    {
        return $this->profileMessages() + [
            'password.confirmed' => 'Pengesahan Kata Laluan tidak sepadan.',
            'password.min' => 'Kata Laluan mesti sekurang-kurangnya :min aksara.',
            'password.mixed' => 'Kata Laluan mesti mengandungi huruf besar dan huruf kecil.',
            'password.numbers' => 'Kata Laluan mesti mengandungi sekurang-kurangnya satu nombor.',
            'password.symbols' => 'Kata Laluan mesti mengandungi sekurang-kurangnya satu simbol.',
        ];
    }

    protected function validationAttributes(): array
    {
        return $this->profileAttributes() + [
            'password' => 'Kata Laluan',
        ];
    }

    public function with(): array
    {
        // Jawatan sentiasa dibaca semula dari database; skim dan gred tidak diterima daripada client.
        return $this->profileOptions();
    }
}; ?>

<div class="w-full">
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-700 tracking-tight dark:text-slate-100">Daftar Akaun Pengguna</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">
            Untuk pegawai Jabatan/Agensi. Akaun akan diaktifkan selepas diluluskan oleh Urus Setia.
        </p>
    </div>

    <form wire:submit="register" class="space-y-4" novalidate>
        <x-input-error :messages="$errors->get('throttle')" class="p-3 bg-red-50 rounded-md" />

        <div>
            <x-input-label for="no_kad_pengenalan" value="No. Kad Pengenalan *" />
            <x-text-input wire:model="no_kad_pengenalan" id="no_kad_pengenalan" class="block mt-1 w-full" type="text"
                inputmode="numeric" maxlength="12" autocomplete="off" placeholder="cth. 900101051234" autofocus />
            <p class="mt-1 text-xs text-slate-500">12 digit, tanpa sengkang.</p>
            <x-input-error :messages="$errors->get('no_kad_pengenalan')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="name" value="Nama Penuh *" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="agency_id" value="Jabatan/Agensi *" />
            <select wire:model="agency_id" id="agency_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">-- Pilih Jabatan/Agensi --</option>
                @foreach ($agencies as $agency)
                    <option value="{{ $agency->id }}">{{ $agency->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('agency_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="position_id" value="Jawatan *" />
            <select wire:model.live="position_id" id="position_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">-- Pilih Jawatan --</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}">{{ $position->title }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('position_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="service_scheme" value="Skim Perkhidmatan" />
            <x-text-input id="service_scheme" class="block mt-1 w-full bg-slate-100 text-slate-600 cursor-not-allowed" type="text" readonly tabindex="-1"
                value="{{ $serviceScheme ? $serviceScheme->code . ' — ' . $serviceScheme->name : '' }}"
                placeholder="Dipaparkan selepas Jawatan dipilih" />
        </div>

        <div>
            <x-input-label for="grade_id" value="Gred *" />
            <select wire:model="grade_id" id="grade_id" wire:key="grades-for-{{ $position_id ?: 'none' }}" @disabled($grades->isEmpty())
                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-slate-100">
                <option value="">{{ $position_id === '' ? '-- Pilih Jawatan dahulu --' : '-- Pilih Gred --' }}</option>
                @foreach ($grades as $grade)
                    <option value="{{ $grade->id }}">{{ $grade->code }}</option>
                @endforeach
            </select>
            @if ($position_id !== '' && $grades->isEmpty())
                <p class="mt-1 text-xs text-red-600">Tiada Gred yang sah bagi Jawatan ini. Sila hubungi Urus Setia.</p>
            @endif
            <x-input-error :messages="$errors->get('grade_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="phone_office" value="No. Telefon Pejabat *" />
                <x-text-input wire:model="phone_office" id="phone_office" class="block mt-1 w-full" type="tel"
                    inputmode="numeric" maxlength="11" placeholder="cth. 067659999" />
                <x-input-error :messages="$errors->get('phone_office')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="phone_mobile" value="No. Telefon HP *" />
                <x-text-input wire:model="phone_mobile" id="phone_mobile" class="block mt-1 w-full" type="tel"
                    inputmode="numeric" maxlength="11" placeholder="cth. 0123456789" />
                <x-input-error :messages="$errors->get('phone_mobile')" class="mt-2" />
            </div>
        </div>
        <p class="-mt-2 text-xs text-slate-500">Nombor telefon: digit sahaja, tanpa sengkang atau ruang.</p>

        <div>
            <x-input-label for="email" value="E-mel Rasmi *" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" autocomplete="username" placeholder="nama@ns.gov.my" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Kata Laluan *" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Pengesahan Kata Laluan *" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end pt-2">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                Sudah mempunyai akaun?
            </a>

            <x-primary-button class="ms-4 disabled:opacity-60" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove wire:target="register">Daftar</span>
                <span wire:loading wire:target="register">Mendaftar...</span>
            </x-primary-button>
        </div>
    </form>
</div>
