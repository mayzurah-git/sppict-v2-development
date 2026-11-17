<?php

use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

/**
 * UM12: Pengemaskinian profil pengguna sendiri (semua tahap capaian).
 *
 * Boleh dikemas kini: No. Telefon (Pejabat), No. Telefon (HP), Gred Jawatan, E-mel Rasmi.
 * Rujukan sahaja: No. KP, Nama, Jabatan/Agensi, Jawatan, Skim Perkhidmatan, Tahap Capaian, Status.
 *
 * Sasaran sentiasa pengguna yang sedang log masuk (tiada ID diterima daripada borang). Hanya
 * medan yang dibenarkan disimpan; peranan, status dan medan pentadbiran tidak boleh diubah.
 */
new class extends Component
{
    public string $phone_office = '';
    public string $phone_mobile = '';
    public string $grade_id = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = $this->currentUser();

        $this->phone_office = $user->phone_office ?? '';
        $this->phone_mobile = $user->phone_mobile ?? '';
        $this->grade_id = (string) ($user->grade_id ?? '');
        $this->email = $user->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        // Rekod semasa dari database; pengguna mesti masih boleh menggunakan sistem (UM6/UM11).
        $user = $this->currentUser();
        abort_unless($user->canUseSystem(), 403);

        $this->phone_office = trim($this->phone_office);
        $this->phone_mobile = trim($this->phone_mobile);
        $this->email = Str::lower(trim($this->email));

        $position = $this->assignablePosition($user);

        $rules = [
            'phone_office' => ['required', 'regex:/^0\d{8,10}$/'],
            'phone_mobile' => ['required', 'regex:/^0\d{8,10}$/'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Unik tanpa mengira huruf besar/kecil, termasuk akaun soft-deleted, kecuali akaun sendiri.
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    $taken = User::withTrashed()
                        ->whereRaw('LOWER(email) = ?', [Str::lower($value)])
                        ->whereKeyNot($user->id)
                        ->exists();

                    if ($taken) {
                        $fail('E-mel Rasmi ini telah didaftarkan.');
                    }
                },
            ],
        ];

        // Gred hanya boleh dikemas kini jika Jawatan pengguna sah; mesti Gred aktif yang dipautkan
        // kepada Jawatan tersebut melalui pivot grade_position (Jawatan diambil dari database).
        if ($position) {
            $rules['grade_id'] = [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('code')
                    ->whereNull('deleted_at')),
                Rule::exists('grade_position', 'grade_id')->where('position_id', $position->id),
            ];
        }

        $validated = $this->validate($rules, [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute yang dipilih tidak sah.',
            'grade_id.exists' => 'Gred Jawatan yang dipilih tidak sah bagi Jawatan anda.',
            'regex' => ':attribute mesti digit sahaja, bermula dengan 0 (9 hingga 11 digit).',
            'email' => 'Format :attribute tidak sah.',
            'max' => ':attribute tidak boleh melebihi :max aksara.',
        ], [
            'phone_office' => 'No. Telefon (Pejabat)',
            'phone_mobile' => 'No. Telefon (HP)',
            'grade_id' => 'Gred Jawatan',
            'email' => 'E-mel Rasmi',
        ]);

        // Senarai medan dibenarkan secara eksplisit (tiada mass assignment medan sensitif).
        $attributes = [
            'phone_office' => $validated['phone_office'],
            'phone_mobile' => $validated['phone_mobile'],
            'email' => $validated['email'],
        ];

        if ($position) {
            $attributes['grade_id'] = (int) $validated['grade_id'];
        }

        DB::transaction(function () use ($user, $attributes): void {
            $user->fill($attributes);

            // E-mel baharu belum disahkan (pengesahan e-mel wajib belum diaktifkan — UM7A).
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
        });

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    protected function currentUser(): User
    {
        return User::query()->findOrFail(Auth::id());
    }

    /**
     * Jawatan pengguna jika masih sah dipilih (aktif, skim aktif, ada gred aktif).
     */
    protected function assignablePosition(User $user): ?Position
    {
        return $user->position_id
            ? Position::assignable()->with('serviceScheme')->find($user->position_id)
            : null;
    }

    public function with(): array
    {
        // Maklumat rujukan (No. KP, Jabatan, Jawatan, dll.) dipaparkan oleh profile.blade.php.
        $user = $this->currentUser()->load(['grade:id,code', 'roles:id,name']);
        $position = $this->assignablePosition($user);

        return [
            'profile' => $user,
            'theme' => \App\Support\RoleTheme::for($user),
            'grades' => $position
                ? $position->grades()->assignable()->orderByRaw('CAST(grades.code AS UNSIGNED)')->get(['grades.id', 'grades.code'])
                : collect(),
            'canChangeGrade' => (bool) $position,
        ];
    }
}; ?>

@php
    $field = 'block w-full h-11 rounded-xl border-slate-300 text-[15px] text-slate-900 shadow-none ' . $theme['input'];
    $label = 'block text-[13px] font-bold text-slate-700';
@endphp

<section aria-labelledby="tajuk-hubungan" class="bg-white border border-slate-200 rounded-[18px] overflow-hidden">
    <form wire:submit="updateProfileInformation" novalidate>
        <header class="px-6 pt-5">
            <h2 id="tajuk-hubungan" class="text-[17px] font-extrabold text-slate-900">{{ __('Maklumat Hubungan') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Kemas kini nombor telefon, gred jawatan dan e-mel rasmi akaun anda.') }}</p>
        </header>

        <div class="px-6 pt-5 pb-6 grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-[18px]">
            <div class="space-y-1.5">
                <label for="phone_office" class="{{ $label }}">{{ __('No. Telefon (Pejabat)') }}</label>
                <div class="relative">
                    <svg aria-hidden="true" class="pointer-events-none absolute left-3.5 top-[13px] w-[18px] h-[18px] text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z" /></svg>
                    <input wire:model="phone_office" id="phone_office" name="phone_office" type="tel" inputmode="numeric" maxlength="11" placeholder="cth. 067659999" class="{{ $field }} pl-10 tabular-nums" />
                </div>
                <x-input-error :messages="$errors->get('phone_office')" />
            </div>
            <div class="space-y-1.5">
                <label for="phone_mobile" class="{{ $label }}">{{ __('No. Telefon (HP)') }}</label>
                <div class="relative">
                    <svg aria-hidden="true" class="pointer-events-none absolute left-3.5 top-[13px] w-[18px] h-[18px] text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2.5" /><path d="M11 18h2" /></svg>
                    <input wire:model="phone_mobile" id="phone_mobile" name="phone_mobile" type="tel" inputmode="numeric" maxlength="11" placeholder="cth. 0123456789" class="{{ $field }} pl-10 tabular-nums" />
                </div>
                <x-input-error :messages="$errors->get('phone_mobile')" />
            </div>
            <p class="sm:col-span-2 -mt-2 text-xs text-slate-500">{{ __('Digit sahaja, bermula dengan 0, tanpa sengkang atau ruang.') }}</p>

            <div class="space-y-1.5">
                <label for="grade_id" class="{{ $label }}">{{ __('Gred Jawatan') }}</label>
                <select wire:model="grade_id" id="grade_id" name="grade_id" @disabled(! $canChangeGrade)
                    class="{{ $field }} disabled:bg-slate-100 disabled:text-slate-500">
                    <option value="">{{ $canChangeGrade ? __('-- Pilih Gred --') : ($profile->grade?->code ?? '-') }}</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}">{{ $grade->code }}</option>
                    @endforeach
                </select>
                @if ($canChangeGrade)
                    <p class="text-xs text-slate-500">{{ __('Hanya gred yang dipautkan kepada Jawatan anda.') }}</p>
                @else
                    <p class="text-xs text-slate-500">{{ __('Gred tidak boleh dipilih kerana Jawatan anda belum dikemas kini. Sila hubungi Urus Setia.') }}</p>
                @endif
                <x-input-error :messages="$errors->get('grade_id')" />
            </div>

            <div class="space-y-1.5">
                <label for="email" class="{{ $label }}">{{ __('E-mel Rasmi') }}</label>
                <div class="relative">
                    <svg aria-hidden="true" class="pointer-events-none absolute left-3.5 top-[13px] w-[18px] h-[18px] text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
                    <input wire:model="email" id="email" name="email" type="email" autocomplete="username" class="{{ $field }} pl-10" />
                </div>
                <x-input-error :messages="$errors->get('email')" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <div>
                        <p class="text-sm mt-2 text-slate-800">
                            {{ __('Alamat e-mel anda belum disahkan.') }}

                            <button wire:click.prevent="sendVerification" class="underline text-sm text-slate-600 hover:text-slate-900 rounded-md focus:outline-none focus-visible:ring-2 {{ $theme['focus'] }}">
                                {{ __('Klik di sini untuk menghantar semula e-mel pengesahan.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 font-medium text-sm text-green-700">
                                {{ __('Pautan pengesahan baharu telah dihantar ke alamat e-mel anda.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="px-6 py-3.5 bg-slate-50/70 border-t border-slate-100 flex flex-wrap items-center justify-end gap-3">
            <x-action-message class="me-auto inline-flex items-center gap-1.5 text-sm font-bold text-green-700" on="profile-updated">
                <svg aria-hidden="true" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" /></svg>
                {{ __('Profil berjaya dikemas kini.') }}
            </x-action-message>
            <button type="submit" wire:loading.attr="disabled" wire:target="updateProfileInformation"
                class="min-h-11 px-6 rounded-xl text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 {{ $theme['button'] }}">
                {{ __('Kemaskini') }}
            </button>
        </div>
    </form>
</section>
