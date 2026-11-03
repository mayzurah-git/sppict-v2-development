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
        $user = $this->currentUser()->load(['agency:id,name', 'positionRelation.serviceScheme', 'grade:id,code', 'roles:id,name']);
        $position = $this->assignablePosition($user);
        $role = $user->roles->first()?->name;

        return [
            'profile' => $user,
            'roleLabel' => \App\Livewire\Admin\UserManagement::ROLE_LABELS[$role] ?? ($role ?: '-'),
            'grades' => $position
                ? $position->grades()->assignable()->orderByRaw('CAST(grades.code AS UNSIGNED)')->get(['grades.id', 'grades.code'])
                : collect(),
            'canChangeGrade' => (bool) $position,
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Maklumat Profil') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Kemas kini nombor telefon, gred jawatan dan e-mel rasmi akaun anda.') }}
        </p>
    </header>

    {{-- Maklumat rujukan (tidak boleh diubah melalui Profil) --}}
    <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('No. Kad Pengenalan') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $profile->no_kad_pengenalan ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Nama') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $profile->name }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Jabatan/Agensi') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $profile->agency?->name ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Jawatan') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $profile->positionRelation?->title ?? ($profile->getAttribute('position') ?: '-') }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Skim Perkhidmatan') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">
                @if ($scheme = $profile->positionRelation?->serviceScheme){{ $scheme->code }} — {{ $scheme->name }}@else-@endif
            </dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">{{ __('Tahap Capaian') }}</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $roleLabel }}</dd>
        </div>
    </dl>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        {{ __('Untuk meminda maklumat di atas, sila hubungi Urus Setia.') }}
    </p>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6" novalidate>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="phone_office" :value="__('No. Telefon (Pejabat)')" />
                <x-text-input wire:model="phone_office" id="phone_office" name="phone_office" type="tel" inputmode="numeric" maxlength="11" class="mt-1 block w-full" placeholder="cth. 067659999" />
                <x-input-error class="mt-2" :messages="$errors->get('phone_office')" />
            </div>
            <div>
                <x-input-label for="phone_mobile" :value="__('No. Telefon (HP)')" />
                <x-text-input wire:model="phone_mobile" id="phone_mobile" name="phone_mobile" type="tel" inputmode="numeric" maxlength="11" class="mt-1 block w-full" placeholder="cth. 0123456789" />
                <x-input-error class="mt-2" :messages="$errors->get('phone_mobile')" />
            </div>
        </div>
        <p class="-mt-4 text-xs text-gray-500 dark:text-gray-400">{{ __('Digit sahaja, tanpa sengkang atau ruang.') }}</p>

        <div>
            <x-input-label for="grade_id" :value="__('Gred Jawatan')" />
            <select wire:model="grade_id" id="grade_id" name="grade_id" @disabled(! $canChangeGrade)
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm disabled:bg-gray-100">
                <option value="">{{ $canChangeGrade ? __('-- Pilih Gred --') : ($profile->grade?->code ?? '-') }}</option>
                @foreach ($grades as $grade)
                    <option value="{{ $grade->id }}">{{ $grade->code }}</option>
                @endforeach
            </select>
            @unless ($canChangeGrade)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Gred tidak boleh dipilih kerana Jawatan anda belum dikemas kini. Sila hubungi Urus Setia.') }}</p>
            @endunless
            <x-input-error class="mt-2" :messages="$errors->get('grade_id')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('E-mel Rasmi')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Alamat e-mel anda belum disahkan.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            {{ __('Klik di sini untuk menghantar semula e-mel pengesahan.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('Pautan pengesahan baharu telah dihantar ke alamat e-mel anda.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button wire:loading.attr="disabled" wire:target="updateProfileInformation">{{ __('Simpan') }}</x-primary-button>

            <x-action-message class="me-3 text-emerald-700" on="profile-updated">
                {{ __('Profil berjaya dikemas kini.') }}
            </x-action-message>
        </div>
    </form>
</section>
