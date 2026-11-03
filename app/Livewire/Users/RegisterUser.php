<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesUserProfileFields;
use App\Livewire\Concerns\ResendsAccountSetupLinks;
use App\Models\User;
use App\Support\AccountSetupLinkSender;
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
 * Akaun dicipta aktif dengan kata laluan rawak yang tidak didedahkan kepada sesiapa
 * (`must_change_password`). UM13: pengguna menerima pautan "Tetapkan Kata Laluan" melalui e-mel.
 * Jika e-mel gagal, status dipaparkan bersama butang Jana Semula.
 */
#[Layout('layouts.app')]
class RegisterUser extends Component
{
    use HandlesUserProfileFields;
    use ResendsAccountSetupLinks;

    /**
     * Tahap capaian yang boleh didaftarkan oleh Urus Setia. `superadmin` sengaja dikecualikan.
     */
    public const ASSIGNABLE_ROLES = [
        'pengguna_biasa' => 'Pengguna Biasa (Pemohon Agensi)',
        'pentadbir_urus_setia' => 'Urus Setia',
        'pengurusan' => 'Pengurusan',
    ];

    public string $role = '';

    /**
     * Akaun terakhir yang didaftarkan dan status penghantaran e-mel pautan (UM13).
     */
    #[Locked]
    public ?string $createdUserUuid = null;

    #[Locked]
    public ?string $createdUserName = null;

    #[Locked]
    public ?string $createdUserEmail = null;

    #[Locked]
    public ?bool $setupLinkSent = null;

    public function mount(): void
    {
        $this->ensureUrusSetia();
    }

    public function save(): void
    {
        $this->ensureUrusSetia();
        $this->normalizeProfileFields();

        $validated = $this->validate($this->rules(), $this->messages(), $this->validationAttributes());

        $user = DB::transaction(function () use ($validated): User {
            $user = new User();
            $user->fill($this->profileAttributesForSave($validated) + [
                // Kata laluan rawak yang tidak didedahkan; pengguna menetapkan sendiri melalui pautan e-mel.
                'password' => Hash::make(TemporaryPassword::generate(32)),
            ]);
            // Didaftar oleh Urus Setia: terus aktif, kata laluan perlu ditetapkan oleh pengguna.
            $user->forceFill([
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ])->save();
            $user->assignRole($validated['role']);

            return $user;
        });

        // Dihantar selepas transaksi: kegagalan e-mel tidak membatalkan pendaftaran.
        $this->afterSetupLinkAttempt($user, AccountSetupLinkSender::send($user));

        $this->resetProfileFields();
        $this->reset('role');
        $this->resetValidation();
    }

    public function dismissSetupStatus(): void
    {
        $this->reset('createdUserUuid', 'createdUserName', 'createdUserEmail', 'setupLinkSent');
    }

    protected function afterSetupLinkAttempt(User $user, bool $sent): void
    {
        $this->createdUserUuid = $user->uuid;
        $this->createdUserName = $user->name;
        $this->createdUserEmail = $user->email;
        $this->setupLinkSent = $sent;
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
