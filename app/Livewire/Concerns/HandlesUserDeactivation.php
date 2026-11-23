<?php

namespace App\Livewire\Concerns;

use App\Enums\InactiveReason;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

/**
 * UM11: pengurusan status akaun — nyahaktif (Aktif → Tidak Aktif, sebab wajib) dan
 * aktifkan semula (Tidak Aktif → Aktif).
 *
 * Dikongsi oleh Superadmin (`Admin\UserManagement`) dan Urus Setia (`Users\ManageUsers`); setiap
 * komponen menentukan kebenaran (`authorizeStatusChange`) dan skop sasaran (`statusManageableUsers`).
 *
 * - Hanya `active` boleh dinyahaktifkan; hanya `inactive` boleh diaktifkan semula. Status permohonan
 *   (`pending`/`rejected`) tidak disentuh.
 * - Peranan Spatie, kata laluan dan medan kelulusan tidak disentuh.
 * - Superadmin aktif terakhir tidak boleh dinyahaktifkan.
 * - Perubahan diaudit oleh audit `updated` OwenIt (status, sebab, masa, pegawai).
 * - Akses pengguna Tidak Aktif disekat serta-merta oleh `EnsureUserIsActive` (HTTP & Livewire) dan LoginForm.
 */
trait HandlesUserDeactivation
{
    public bool $showDeactivateModal = false;

    #[Locked]
    public ?string $deactivateUuid = null;

    public string $inactiveReason = '';

    abstract protected function authorizeStatusChange(): void;

    /**
     * Pengguna dalam skop pelaku semasa (tanpa mengira status); dibaca semula dari database.
     */
    abstract protected function statusManageableUsers(): Builder;

    public function openDeactivate(string $uuid): void
    {
        $this->authorizeStatusChange();

        $user = $this->statusManageableUsers()->active()->where('uuid', $uuid)->firstOrFail();

        $this->deactivateUuid = $user->uuid;
        $this->inactiveReason = '';
        $this->resetValidation('inactiveReason');
        $this->showDeactivateModal = true;
    }

    public function closeDeactivate(): void
    {
        $this->showDeactivateModal = false;
        $this->deactivateUuid = null;
        $this->inactiveReason = '';
        $this->resetValidation('inactiveReason');
    }

    public function deactivate(): void
    {
        $this->authorizeStatusChange();

        $this->validate(
            ['inactiveReason' => ['required', 'string', Rule::enum(InactiveReason::class)]],
            [
                'inactiveReason.required' => 'Sila pilih Sebab Tidak Aktif.',
                'inactiveReason.enum' => 'Sebab Tidak Aktif yang dipilih tidak sah.',
            ],
        );

        $actor = auth()->user();
        $reason = InactiveReason::from($this->inactiveReason);

        [$outcome, $user] = DB::transaction(function () use ($actor, $reason): array {
            // Kunci rekod & semak semula skop di server (UUID terkunci, bukan input bebas).
            $user = $this->statusManageableUsers()->where('uuid', (string) $this->deactivateUuid)->lockForUpdate()->firstOrFail();

            if (! $user->isActive()) {
                return ['processed', $user];
            }

            if ($user->isLastActiveSuperadmin()) {
                return ['last_superadmin', $user];
            }

            $user->forceFill([
                'status' => UserStatus::Inactive,
                'inactive_reason' => $reason,
                'status_changed_at' => now(),
                'status_changed_by' => $actor->id,
            ])->save();

            return ['ok', $user];
        });

        if ($outcome === 'processed') {
            $this->addError('inactiveReason', 'Akaun ini tidak lagi berstatus Aktif.');

            return;
        }

        if ($outcome === 'last_superadmin') {
            $this->addError('inactiveReason', 'Superadmin aktif yang terakhir tidak boleh dinyahaktifkan.');

            return;
        }

        session()->flash('message', "Akaun {$user->name} telah ditukar kepada Tidak Aktif ({$reason->label()}). Akses ke sistem disekat serta-merta.");

        // Pegawai Bertanggungjawab yang dinyahaktifkan: maklumkan supaya pegawai baharu dilantik.
        $references = $user->activeOfficerReferences();
        if ($references->isNotEmpty()) {
            session()->flash('warning', "{$user->name} masih Pegawai Bertanggungjawab bagi {$references->count()} permohonan/projek ({$references->implode(', ')}). "
                . 'Sila lantik pegawai baharu.');
        }

        $this->closeDeactivate();
    }

    /**
     * Aktifkan semula akaun Tidak Aktif (keputusan dasar 2026-10-21: Urus Setia & Superadmin).
     * Sebab dikosongkan; masa & pegawai direkod. Kata laluan, peranan & profil tidak berubah.
     */
    public function reactivate(string $uuid): void
    {
        $this->authorizeStatusChange();
        $actor = auth()->user();

        [$outcome, $user] = DB::transaction(function () use ($uuid, $actor): array {
            $user = $this->statusManageableUsers()->where('uuid', $uuid)->lockForUpdate()->firstOrFail();

            if (! $user->isInactive()) {
                return ['processed', $user];
            }

            $user->forceFill([
                'status' => UserStatus::Active,
                'inactive_reason' => null,
                'status_changed_at' => now(),
                'status_changed_by' => $actor->id,
            ])->save();

            return ['ok', $user];
        });

        $outcome === 'ok'
            ? session()->flash('message', "Akaun {$user->name} telah diaktifkan semula.")
            : session()->flash('error', 'Akaun ini tidak berstatus Tidak Aktif dan tidak boleh diaktifkan semula.');
    }

    protected function deactivationOptions(): array
    {
        return [
            'inactiveReasonOptions' => InactiveReason::options(),
            'deactivating' => $this->showDeactivateModal && $this->deactivateUuid
                ? $this->statusManageableUsers()->active()->where('uuid', $this->deactivateUuid)->first()
                : null,
        ];
    }
}
