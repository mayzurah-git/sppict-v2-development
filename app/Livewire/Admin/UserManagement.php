<?php

namespace App\Livewire\Admin;

use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesUserDeactivation;
use App\Livewire\Concerns\HandlesUserProfileFields;
use App\Livewire\Concerns\RateLimitsActions;
use App\Models\User;
use App\Support\RoleChangeAudit;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * UM5: Pengurusan Pengguna oleh Superadmin.
 *
 * - Daftar akaun Urus Setia / Superadmin dengan master data (trait dikongsi dengan UM2/UM4).
 * - Kemas kini profil & peranan pengguna sedia ada: mana-mana 4 peranan (UM10), kecuali menukar
 *   Superadmin aktif terakhir. Perubahan peranan diaudit (`role_updated`).
 * - Reset kata laluan: kata laluan sementara rawak + `must_change_password` (dikuatkuasakan UM6).
 *
 * Kata laluan sementara dipaparkan sekali melalui property `#[Locked]`, tidak disimpan dalam
 * session (SESSION_ENCRYPT=false), log atau audit.
 */
class UserManagement extends Component
{
    use HandlesUserDeactivation;
    use HandlesUserProfileFields;
    use RateLimitsActions;
    use WithPagination;

    /**
     * Peranan bagi akaun BAHARU yang didaftar oleh Superadmin (keputusan UM0/UM10).
     * Kemas kini pengguna sedia ada menggunakan semua `ROLE_LABELS`.
     */
    public const ASSIGNABLE_ROLES = [
        'pentadbir_urus_setia' => 'Urus Setia',
        'superadmin' => 'Superadmin',
    ];

    /**
     * Label paparan bagi semua peranan sistem (senarai & penapis sahaja).
     */
    public const ROLE_LABELS = [
        'superadmin' => 'Superadmin',
        'pentadbir_urus_setia' => 'Urus Setia',
        'pengurusan' => 'Pengurusan',
        'pengguna_biasa' => 'Pemohon Agensi',
    ];

    public string $search = '';
    public string $roleFilter = '';
    public bool $showModal = false;

    #[Locked]
    public ?string $userUuid = null;

    public string $role = '';

    #[Locked]
    public ?string $temporaryPassword = null;

    #[Locked]
    public ?string $temporaryPasswordFor = null;

    public function mount(): void
    {
        $this->ensureSuperadmin();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function openModal(?string $uuid = null): void
    {
        $this->ensureSuperadmin();
        $this->resetErrorBag();

        if ($uuid) {
            $user = User::where('uuid', $uuid)->firstOrFail();
            $this->userUuid = $user->uuid;
            $this->fillProfileFieldsFrom($user);
            $this->role = $user->getRoleNames()->first() ?? '';
        } else {
            $this->userUuid = null;
            $this->resetProfileFields();
            $this->role = '';
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->userUuid = null;
        $this->resetProfileFields();
        $this->reset('role');
        $this->resetErrorBag();
    }

    public function saveUser(): void
    {
        $this->ensureSuperadmin();
        $this->normalizeProfileFields();

        // Sasaran dibaca semula daripada database berdasarkan UUID terkunci.
        $editing = $this->userUuid ? User::where('uuid', $this->userUuid)->firstOrFail() : null;

        $validated = $this->validate($this->rules($editing), $this->messages(), $this->validationAttributes());

        $editing ? $this->updateUser($editing, $validated) : $this->createUser($validated);
    }

    public function resetPassword(string $uuid): void
    {
        $this->ensureSuperadmin();

        $user = User::where('uuid', $uuid)->firstOrFail();

        // UM7: had kadar bagi setiap Superadmin: 3 reset / 10 minit bagi akaun sasaran yang sama,
        // dan 20 reset / 10 minit secara keseluruhan. Operasi pentadbiran biasa tidak terjejas.
        $limits = [
            [$this->rateLimitKey('admin-password-reset:target', (string) auth()->id(), $user->uuid), 3],
            [$this->rateLimitKey('admin-password-reset:actor', (string) auth()->id()), 20],
        ];

        foreach ($limits as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                session()->flash('error', $this->rateLimitMessage($key) . ' Kata laluan tidak diset semula.');

                return;
            }
        }

        $temporaryPassword = TemporaryPassword::generate(12);

        DB::transaction(function () use ($user, $temporaryPassword): void {
            $user->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                // Batalkan sesi "Ingat saya" sedia ada.
                'remember_token' => Str::random(60),
            ])->save();
        });

        foreach ($limits as [$key]) {
            RateLimiter::hit($key, 600);
        }

        // Dipaparkan hanya selepas transaksi berjaya.
        $this->showTemporaryPassword($temporaryPassword, $user->name);
    }

    /**
     * Tutup paparan kata laluan sementara; ia tidak boleh dipaparkan semula.
     */
    public function dismissTemporaryPassword(): void
    {
        $this->temporaryPassword = null;
        $this->temporaryPasswordFor = null;
    }

    protected function createUser(array $validated): void
    {
        $temporaryPassword = TemporaryPassword::generate(12);

        $user = DB::transaction(function () use ($validated, $temporaryPassword): User {
            $user = new User();
            $user->fill($this->profileAttributesForSave($validated) + [
                'password' => Hash::make($temporaryPassword),
            ]);
            // Selaras dengan UM4: akaun didaftar pentadbir terus aktif, kata laluan sementara wajib ditukar.
            $user->forceFill([
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ])->save();
            $user->assignRole($validated['role']);

            return $user;
        });

        $this->closeModal();
        $this->showTemporaryPassword($temporaryPassword, $user->name);
        session()->flash('message', "Pengguna {$user->name} berjaya didaftarkan.");
    }

    protected function updateUser(User $user, array $validated): void
    {
        $previousAgencyId = $user->agency_id;
        $previousRoles = $user->getRoleNames()->all();
        $currentRole = $previousRoles[0] ?? null;

        // UM10: sistem mesti sentiasa mempunyai sekurang-kurangnya satu Superadmin aktif.
        if ($currentRole === 'superadmin' && $validated['role'] !== 'superadmin' && $user->isLastActiveSuperadmin()) {
            $this->addError('role', 'Tahap Capaian Superadmin aktif yang terakhir tidak boleh ditukar.');

            return;
        }

        DB::transaction(function () use ($user, $validated, $currentRole, $previousRoles): void {
            // Hanya medan profil; status, kelulusan, kata laluan dan kolum legacy tidak disentuh.
            $user->fill($this->profileAttributesForSave($validated))->save();

            if ($validated['role'] !== $currentRole) {
                $user->syncRoles([$validated['role']]);
                RoleChangeAudit::record($user, 'role_updated', ['roles' => $previousRoles], ['roles' => [$validated['role']]]);
            }
        });

        session()->flash('message', "Maklumat {$user->name} berjaya dikemaskini.");

        // Pengguna bertukar agensi tetapi masih Pegawai Bertanggungjawab aktif: maklumkan supaya pegawai baharu dilantik
        if ((int) $previousAgencyId !== (int) $user->agency_id) {
            $references = $user->activeOfficerReferences();

            if ($references->isNotEmpty()) {
                session()->flash('warning', "{$user->name} masih Pegawai Bertanggungjawab bagi {$references->count()} permohonan/projek ({$references->implode(', ')}). "
                    . 'Pegawai ini tidak lagi boleh mengisi kemajuan projek tersebut; sila maklumkan agensi berkenaan atau Urus Setia untuk melantik pegawai baharu.');
            }
        }

        $this->closeModal();
    }

    protected function showTemporaryPassword(string $password, string $name): void
    {
        $this->temporaryPassword = $password;
        $this->temporaryPasswordFor = $name;
    }

    /**
     * Peranan yang sah untuk borang (keputusan UM10):
     * - Daftar akaun baharu: Superadmin & Urus Setia sahaja (`ASSIGNABLE_ROLES`).
     * - Kemas kini pengguna sedia ada: mana-mana 4 peranan sistem (`ROLE_LABELS`), termasuk
     *   menurunkan Urus Setia kepada Pengguna Biasa / Pengurusan.
     */
    protected function allowedRoles(?User $editing): array
    {
        return $editing
            ? array_keys(self::ROLE_LABELS)
            : array_keys(self::ASSIGNABLE_ROLES);
    }

    /**
     * UM11: Superadmin boleh menyahaktifkan / mengaktifkan semula mana-mana akaun
     * (kecuali menyahaktifkan Superadmin aktif terakhir).
     */
    protected function statusManageableUsers(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query();
    }

    protected function authorizeStatusChange(): void
    {
        $this->ensureSuperadmin();
    }

    protected function rules(?User $editing = null): array
    {
        return $this->profileRules($editing?->id) + [
            'role' => ['required', 'string', Rule::in($this->allowedRoles($editing)), Rule::exists('roles', 'name')],
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

    protected function ensureSuperadmin(): void
    {
        $user = auth()->user();

        // Disemak semula pada setiap tindakan Livewire (middleware route `role:` tidak diulang untuk permintaan Livewire).
        abort_unless($user?->hasRole('superadmin') && $user->canUseSystem(), 403);
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ($role ?: 'Tiada Peranan');
    }

    public function render()
    {
        $users = User::query()
            ->with(['agency:id,name', 'positionRelation:id,title', 'grade:id,code', 'roles:id,name', 'statusChanger:id,name'])
            ->when($this->search !== '', function ($query): void {
                // Dikumpulkan dalam closure supaya `orWhere` tidak memintas penapis peranan.
                $query->where(function ($query): void {
                    $term = '%' . $this->search . '%';
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('no_kad_pengenalan', 'like', $term);
                });
            })
            ->when(array_key_exists($this->roleFilter, self::ROLE_LABELS), function ($query): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $this->roleFilter));
            })
            ->latest()
            ->paginate(10);

        $editing = $this->showModal && $this->userUuid ? User::where('uuid', $this->userUuid)->first() : null;

        $roleOptions = collect($this->allowedRoles($editing))
            ->mapWithKeys(fn (string $role) => [$role => self::roleLabel($role)])
            ->all();

        return view('livewire.admin.user-management', $this->profileOptions() + $this->deactivationOptions() + [
            'users' => $users,
            'roleOptions' => $roleOptions,
            'roleLabels' => self::ROLE_LABELS,
        ])->layout('layouts.app');
    }
}
