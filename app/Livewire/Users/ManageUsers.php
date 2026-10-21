<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesUserDeactivation;
use App\Livewire\Concerns\HandlesUserProfileFields;
use App\Models\User;
use App\Support\RoleChangeAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * UM10: Senarai & kemas kini pengguna aktif oleh Urus Setia.
 *
 * - Urus Setia boleh mengemas kini PROFIL pengguna aktif berperanan Pengguna Biasa, Pengurusan
 *   atau Urus Setia (bukan Superadmin; bukan akaun sendiri — gunakan Profil).
 * - Urus Setia hanya boleh menukar PERANAN antara Pengurusan ↔ Pengguna Biasa. Peranan Urus Setia
 *   hanya boleh diubah oleh Superadmin.
 * - UM11: Urus Setia boleh menyahaktifkan (sebab wajib) dan mengaktifkan semula pengguna dalam skop.
 * - Kata laluan dan medan kelulusan tidak diurus di sini.
 */
#[Layout('layouts.app')]
class ManageUsers extends Component
{
    use HandlesUserDeactivation;
    use HandlesUserProfileFields;
    use WithPagination;

    /**
     * Peranan pengguna yang profilnya boleh dikemas kini oleh Urus Setia.
     */
    public const EDITABLE_ROLES = [
        'pengguna_biasa' => 'Pengguna Biasa',
        'pengurusan' => 'Pengurusan',
        'pentadbir_urus_setia' => 'Urus Setia',
    ];

    /**
     * Peranan yang boleh ditukar sesama sendiri oleh Urus Setia.
     */
    public const SWITCHABLE_ROLES = ['pengguna_biasa', 'pengurusan'];

    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = '';
    public bool $showModal = false;

    #[Locked]
    public ?string $userUuid = null;

    public string $role = '';

    public function mount(): void
    {
        $this->ensureUrusSetia();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openModal(string $uuid): void
    {
        $this->ensureUrusSetia();
        $this->resetErrorBag();

        $user = $this->editableUsers()->where('uuid', $uuid)->firstOrFail();

        $this->userUuid = $user->uuid;
        $this->fillProfileFieldsFrom($user);
        $this->role = $user->getRoleNames()->first() ?? '';
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

    public function save(): void
    {
        $this->ensureUrusSetia();
        $this->normalizeProfileFields();

        // Sasaran dibaca semula daripada database dan mesti masih dalam skop Urus Setia.
        $user = $this->editableUsers()->where('uuid', (string) $this->userUuid)->firstOrFail();

        $validated = $this->validate(
            $this->profileRules($user->id) + [
                'role' => ['required', 'string', Rule::in($this->allowedRoles($user)), Rule::exists('roles', 'name')],
            ],
            $this->profileMessages() + ['role.in' => 'Tahap Capaian yang dipilih tidak dibenarkan.'],
            $this->profileAttributes() + ['role' => 'Tahap Capaian'],
        );

        $previousAgencyId = $user->agency_id;

        DB::transaction(function () use ($user, $validated): void {
            // Kunci rekod & semak semula skop (peranan mungkin diubah serentak oleh Superadmin).
            $locked = $this->editableUsers()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $previousRoles = $locked->getRoleNames()->all();
            $currentRole = $previousRoles[0] ?? null;

            abort_unless(in_array($validated['role'], $this->allowedRoles($locked), true), 403);

            // Hanya medan profil; status, kelulusan, kata laluan dan kolum legacy tidak disentuh.
            $locked->fill($this->profileAttributesForSave($validated))->save();

            if ($validated['role'] !== $currentRole) {
                $locked->syncRoles([$validated['role']]);
                RoleChangeAudit::record($locked, 'role_updated', ['roles' => $previousRoles], ['roles' => [$validated['role']]]);
            }
        });

        $user->refresh();
        session()->flash('message', "Maklumat {$user->name} berjaya dikemaskini.");

        if ((int) $previousAgencyId !== (int) $user->agency_id) {
            $references = $user->activeOfficerReferences();

            if ($references->isNotEmpty()) {
                session()->flash('warning', "{$user->name} masih Pegawai Bertanggungjawab bagi {$references->count()} permohonan/projek ({$references->implode(', ')}). "
                    . 'Pegawai ini tidak lagi boleh mengisi kemajuan projek tersebut; sila lantik pegawai baharu.');
            }
        }

        $this->closeModal();
    }

    /**
     * Peranan yang sah bagi pengguna sasaran (dibaca dari database, bukan browser):
     * Pengguna Biasa/Pengurusan boleh ditukar sesama sendiri; Urus Setia dikekalkan.
     */
    protected function allowedRoles(User $user): array
    {
        $current = $user->getRoleNames()->first();

        return in_array($current, self::SWITCHABLE_ROLES, true) ? self::SWITCHABLE_ROLES : [$current];
    }

    /**
     * Skop Urus Setia (tanpa mengira status): memegang peranan EDITABLE_ROLES, bukan Superadmin,
     * bukan akaun sendiri.
     */
    protected function scopedUsers(): Builder
    {
        return User::query()
            ->whereKeyNot(auth()->id())
            ->whereHas('roles', fn ($query) => $query->whereIn('name', array_keys(self::EDITABLE_ROLES)))
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'superadmin'));
    }

    /**
     * Pengguna yang profil/peranannya boleh dikemas kini: skop Urus Setia & berstatus Aktif.
     */
    protected function editableUsers(): Builder
    {
        return $this->scopedUsers()->active();
    }

    /**
     * UM11: nyahaktif / aktifkan semula dalam skop Urus Setia.
     */
    protected function statusManageableUsers(): Builder
    {
        return $this->scopedUsers();
    }

    protected function authorizeStatusChange(): void
    {
        $this->ensureUrusSetia();
    }

    protected function ensureUrusSetia(): void
    {
        $user = auth()->user();

        // Disemak semula pada setiap tindakan Livewire (lapisan kedua selepas middleware persistent).
        abort_unless($user?->hasRole('pentadbir_urus_setia') && $user->canUseSystem(), 403);
    }

    public function render()
    {
        // Senarai: pengguna Aktif & Tidak Aktif dalam skop (pending/rejected diurus di Permohonan Pendaftaran).
        $listable = [UserStatus::Active->value, UserStatus::Inactive->value];

        $users = $this->scopedUsers()
            ->whereIn('status', in_array($this->statusFilter, $listable, true) ? [$this->statusFilter] : $listable)
            ->with(['agency:id,name', 'positionRelation:id,title', 'grade:id,code', 'roles:id,name', 'statusChanger:id,name'])
            ->when($this->search !== '', function ($query): void {
                // Dikumpulkan dalam closure supaya `orWhere` tidak memintas skop & penapis peranan.
                $query->where(function ($query): void {
                    $term = '%' . $this->search . '%';
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('no_kad_pengenalan', 'like', $term);
                });
            })
            ->when(array_key_exists($this->roleFilter, self::EDITABLE_ROLES), function ($query): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $this->roleFilter));
            })
            ->orderBy('name')
            ->paginate(10);

        $editing = $this->showModal && $this->userUuid
            ? $this->editableUsers()->where('uuid', $this->userUuid)->first()
            : null;

        $roleOptions = collect($editing ? $this->allowedRoles($editing) : [])
            ->mapWithKeys(fn (string $role) => [$role => self::EDITABLE_ROLES[$role] ?? $role])
            ->all();

        return view('livewire.users.manage-users', $this->profileOptions() + $this->deactivationOptions() + [
            'users' => $users,
            'roleOptions' => $roleOptions,
            'roleLabels' => self::EDITABLE_ROLES,
        ]);
    }
}
