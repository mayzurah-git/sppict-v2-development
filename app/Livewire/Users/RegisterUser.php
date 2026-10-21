<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesUserProfileFields;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * UM4: Pendaftaran pengguna oleh Urus Setia.
 *
 * Akaun dicipta aktif dengan kata laluan sementara yang dijana sistem dan wajib ditukar
 * (`must_change_password`). Kata laluan dipaparkan sekali sahaja kepada Urus Setia dan
 * tidak disimpan dalam session, log atau audit.
 */
#[Layout('layouts.app')]
class RegisterUser extends Component
{
    use HandlesUserProfileFields;

    /**
     * Tahap capaian yang boleh didaftarkan oleh Urus Setia. `superadmin` sengaja dikecualikan.
     */
    public const ASSIGNABLE_ROLES = [
        'pengguna_biasa' => 'Pengguna Biasa (Pemohon Agensi)',
        'pentadbir_urus_setia' => 'Urus Setia',
        'pengurusan' => 'Pengurusan',
    ];

    public string $role = '';

    #[Locked]
    public ?string $temporaryPassword = null;

    #[Locked]
    public ?string $createdUserName = null;

    public function mount(): void
    {
        $this->ensureUrusSetia();
    }

    public function save(): void
    {
        $this->ensureUrusSetia();
        $this->normalizeProfileFields();

        $validated = $this->validate($this->rules(), $this->messages(), $this->validationAttributes());

        $temporaryPassword = TemporaryPassword::generate(12);

        $user = DB::transaction(function () use ($validated, $temporaryPassword): User {
            $user = new User();
            $user->fill($this->profileAttributesForSave($validated) + [
                'password' => Hash::make($temporaryPassword),
            ]);
            // Didaftar oleh Urus Setia: terus aktif, tetapi kata laluan sementara wajib ditukar.
            $user->forceFill([
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ])->save();
            $user->assignRole($validated['role']);

            return $user;
        });

        $this->temporaryPassword = $temporaryPassword;
        $this->createdUserName = $user->name;

        $this->resetProfileFields();
        $this->reset('role');
        $this->resetValidation();
    }

    /**
     * Tutup paparan kata laluan sementara; ia tidak boleh dipaparkan semula.
     */
    public function dismissTemporaryPassword(): void
    {
        $this->temporaryPassword = null;
        $this->createdUserName = null;
    }

    protected function rules(): array
    {
        return $this->profileRules() + [
            // Whitelist di server: nilai lain (termasuk `superadmin`) ditolak walaupun dihantar secara manual.
            'role' => ['required', 'string', Rule::in(array_keys(self::ASSIGNABLE_ROLES)), Rule::exists('roles', 'name')],
        ];
    }

    protected function messages(): array
    {
        return $this->profileMessages() + [
            'role.in' => 'Tahap Capaian yang dipilih tidak dibenarkan.',
        ];
    }

    protected function validationAttributes(): array
    {
        return $this->profileAttributes() + [
            'role' => 'Tahap Capaian',
        ];
    }

    protected function ensureUrusSetia(): void
    {
        $user = auth()->user();

        // Disemak semula pada setiap tindakan Livewire (middleware route tidak diulang untuk permintaan Livewire).
        abort_unless($user?->hasRole('pentadbir_urus_setia') && $user->canUseSystem(), 403);
    }

    public function render()
    {
        return view('livewire.users.register-user', $this->profileOptions() + [
            'roleOptions' => self::ASSIGNABLE_ROLES,
        ]);
    }
}
