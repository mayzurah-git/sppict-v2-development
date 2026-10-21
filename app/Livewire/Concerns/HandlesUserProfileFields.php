<?php

namespace App\Livewire\Concerns;

use App\Models\Agency;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Medan profil pengguna yang dikongsi oleh Self Register (UM2) dan pendaftaran oleh
 * pentadbir (UM4/UM5): No. KP, nama, agensi, Jawatan → Skim (readonly) → Gred, telefon, e-mel.
 *
 * Skim dan senarai Gred sentiasa dibaca semula daripada database berdasarkan Jawatan;
 * nilai daripada client tidak dijadikan sumber kebenaran.
 */
trait HandlesUserProfileFields
{
    public string $no_kad_pengenalan = '';
    public string $name = '';
    public string $agency_id = '';
    public string $position_id = '';
    public string $grade_id = '';
    public string $phone_office = '';
    public string $phone_mobile = '';
    public string $email = '';

    /**
     * Tukar Jawatan: gred lama mungkin tidak sah untuk jawatan baharu, jadi dikosongkan.
     */
    public function updatedPositionId(): void
    {
        $this->grade_id = '';
        $this->resetValidation(['position_id', 'grade_id']);
    }

    protected function normalizeProfileFields(): void
    {
        $this->no_kad_pengenalan = trim($this->no_kad_pengenalan);
        $this->name = Str::squish($this->name);
        $this->phone_office = trim($this->phone_office);
        $this->phone_mobile = trim($this->phone_mobile);
        $this->email = Str::lower(trim($this->email));
    }

    /**
     * @param  int|null  $ignoreUserId  Mod kemas kini: rekod pengguna ini dikecualikan daripada semakan unik
     *                                  No. KP dan e-mel. Rekod lain (termasuk soft-deleted) tetap disemak.
     */
    protected function profileRules(?int $ignoreUserId = null): array
    {
        return [
            'no_kad_pengenalan' => ['required', 'digits:12', Rule::unique('users', 'no_kad_pengenalan')->ignore($ignoreUserId)],
            'name' => ['required', 'string', 'max:255'],
            'agency_id' => [
                'required',
                'integer',
                Rule::exists('agencies', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'position_id' => [
                'required',
                'integer',
                Rule::exists('positions', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
                // Skim aktif dan sekurang-kurangnya satu gred sah (sama seperti dropdown).
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Position::assignable()->whereKey((int) $value)->exists()) {
                        $fail('Jawatan yang dipilih tidak mempunyai Skim Perkhidmatan atau Gred yang sah.');
                    }
                },
            ],
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('code')
                    ->whereNull('deleted_at')),
                // Gred mesti dipautkan dengan Jawatan yang dipilih melalui pivot grade_position.
                Rule::exists('grade_position', 'grade_id')->where('position_id', (int) $this->position_id),
            ],
            'phone_office' => ['required', 'regex:/^0\d{8,10}$/'],
            'phone_mobile' => ['required', 'regex:/^0\d{8,10}$/'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Unik tanpa mengira huruf besar/kecil, termasuk akaun soft-deleted.
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
                    $taken = User::withTrashed()
                        ->whereRaw('LOWER(email) = ?', [Str::lower($value)])
                        ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
                        ->exists();

                    if ($taken) {
                        $fail('E-mel Rasmi ini telah didaftarkan.');
                    }
                },
            ],
        ];
    }

    protected function profileMessages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'exists' => ':attribute yang dipilih tidak sah atau tidak aktif.',
            'integer' => ':attribute yang dipilih tidak sah.',
            'no_kad_pengenalan.digits' => 'No. Kad Pengenalan mesti 12 digit tanpa sengkang.',
            'no_kad_pengenalan.unique' => 'No. Kad Pengenalan ini telah didaftarkan.',
            'grade_id.exists' => 'Gred yang dipilih tidak sah bagi Jawatan ini.',
            'regex' => ':attribute mesti digit sahaja, bermula dengan 0 (9 hingga 11 digit).',
            'email' => 'Format :attribute tidak sah.',
            'max' => ':attribute tidak boleh melebihi :max aksara.',
        ];
    }

    protected function profileAttributes(): array
    {
        return [
            'no_kad_pengenalan' => 'No. Kad Pengenalan',
            'name' => 'Nama Penuh',
            'agency_id' => 'Jabatan/Agensi',
            'position_id' => 'Jawatan',
            'grade_id' => 'Gred',
            'phone_office' => 'No. Telefon Pejabat',
            'phone_mobile' => 'No. Telefon HP',
            'email' => 'E-mel Rasmi',
        ];
    }

    /**
     * Atribut profil yang selamat untuk `User::fill()` daripada data tervalidasi.
     * Medan sensitif (role, status, kelulusan, password) tidak termasuk.
     */
    protected function profileAttributesForSave(array $validated): array
    {
        return [
            'no_kad_pengenalan' => $validated['no_kad_pengenalan'],
            'name' => $validated['name'],
            'agency_id' => (int) $validated['agency_id'],
            'position_id' => (int) $validated['position_id'],
            'grade_id' => (int) $validated['grade_id'],
            'phone_office' => $validated['phone_office'],
            'phone_mobile' => $validated['phone_mobile'],
            'email' => $validated['email'],
        ];
    }

    /**
     * Mod kemas kini: isi medan borang daripada rekod pengguna sedia ada.
     */
    protected function fillProfileFieldsFrom(User $user): void
    {
        $this->no_kad_pengenalan = $user->no_kad_pengenalan ?? '';
        $this->name = $user->name;
        $this->agency_id = (string) ($user->agency_id ?? '');
        $this->position_id = (string) ($user->position_id ?? '');
        $this->grade_id = (string) ($user->grade_id ?? '');
        $this->phone_office = $user->phone_office ?? '';
        $this->phone_mobile = $user->phone_mobile ?? '';
        $this->email = $user->email;
    }

    protected function resetProfileFields(): void
    {
        $this->reset(['no_kad_pengenalan', 'name', 'agency_id', 'position_id', 'grade_id', 'phone_office', 'phone_mobile', 'email']);
    }

    /**
     * Pilihan dropdown: agensi aktif, jawatan yang boleh dipilih, skim dan gred bagi jawatan semasa.
     */
    protected function profileOptions(): array
    {
        $position = $this->position_id !== ''
            ? Position::assignable()->with('serviceScheme')->find((int) $this->position_id)
            : null;

        return [
            'agencies' => Agency::active()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::assignable()->orderBy('title')->get(['id', 'title']),
            'serviceScheme' => $position?->serviceScheme,
            'grades' => $position
                ? $position->grades()->assignable()->orderByRaw('CAST(grades.code AS UNSIGNED)')->get(['grades.id', 'grades.code', 'grades.name'])
                : collect(),
        ];
    }
}
