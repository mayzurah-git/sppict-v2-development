<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\RoleChangeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * UM4: Semakan permohonan Self Register oleh Urus Setia (lulus / tolak).
 *
 * Tindakan hanya sah bagi rekod berstatus `pending`; rekod tidak dipadam. Perubahan status
 * direkodkan oleh audit model User (OwenIt).
 */
#[Layout('layouts.app')]
class RegistrationRequests extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'pending';

    #[Locked]
    public ?string $viewingUuid = null;

    /**
     * UM9: Tahap Capaian yang boleh diberikan semasa meluluskan Self Register — sama dengan whitelist
     * pendaftaran oleh Urus Setia (`superadmin` dikecualikan). Lalai: Pengguna Biasa.
     */
    public const APPROVAL_ROLES = RegisterUser::ASSIGNABLE_ROLES;

    public const DEFAULT_APPROVAL_ROLE = 'pengguna_biasa';

    public string $approvalRole = self::DEFAULT_APPROVAL_ROLE;

    public function mount(): void
    {
        $this->ensureUrusSetia();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function showDetail(string $uuid): void
    {
        $this->ensureUrusSetia();

        $this->viewingUuid = $this->applications()->where('uuid', $uuid)->firstOrFail()->uuid;
        $this->approvalRole = self::DEFAULT_APPROVAL_ROLE;
        $this->resetValidation('approvalRole');
    }

    public function closeDetail(): void
    {
        $this->viewingUuid = null;
        $this->approvalRole = self::DEFAULT_APPROVAL_ROLE;
        $this->resetValidation('approvalRole');
    }

    public function approve(string $uuid): void
    {
        $this->ensureUrusSetia();

        // UM9: tahap capaian disahkan di server; `superadmin` / nilai lain ditolak walaupun dihantar manual.
        $this->validate(
            ['approvalRole' => ['required', 'string', Rule::in(array_keys(self::APPROVAL_ROLES)), Rule::exists('roles', 'name')]],
            ['approvalRole.required' => 'Sila pilih Tahap Capaian.', 'approvalRole.in' => 'Tahap Capaian yang dipilih tidak dibenarkan.', 'approvalRole.exists' => 'Tahap Capaian yang dipilih tidak sah.'],
        );

        $this->decide($uuid, UserStatus::Active, $this->approvalRole);
    }

    public function reject(string $uuid): void
    {
        $this->decide($uuid, UserStatus::Rejected);
    }

    /**
     * Kemas kini status dalam transaction dengan kunci baris supaya dua Urus Setia tidak
     * memproses permohonan yang sama serentak.
     */
    protected function decide(string $uuid, UserStatus $decision, ?string $approvedRole = null): void
    {
        $this->ensureUrusSetia();
        $reviewer = auth()->user();

        $result = DB::transaction(function () use ($uuid, $decision, $reviewer, $approvedRole): string {
            $applicant = User::where('uuid', $uuid)->lockForUpdate()->firstOrFail();

            if ($applicant->is($reviewer)) {
                return 'self';
            }

            if (! $applicant->isPending()) {
                return 'processed';
            }

            $previousRoles = $applicant->getRoleNames()->all();

            $applicant->forceFill(['status' => $decision]);

            // Medan kelulusan hanya diisi apabila diluluskan.
            if ($decision === UserStatus::Active) {
                $applicant->forceFill(['approved_by' => $reviewer->id, 'approved_at' => now()]);
            }

            $applicant->save();

            // UM9: peranan ditetapkan dalam transaksi yang sama dengan status; jika gagal, kedua-duanya
            // dibatalkan. Penolakan tidak menyentuh peranan (akaun `rejected` tiada akses).
            if ($decision === UserStatus::Active) {
                $applicant->syncRoles([$approvedRole]);
                $this->auditApproval($applicant, $previousRoles, $approvedRole, $reviewer);
            }

            return 'ok:' . $applicant->name;
        });

        $this->viewingUuid = null;
        $this->approvalRole = self::DEFAULT_APPROVAL_ROLE;

        match (true) {
            $result === 'self' => session()->flash('error', 'Anda tidak boleh memproses permohonan pendaftaran anda sendiri.'),
            $result === 'processed' => session()->flash('error', 'Permohonan ini telah diproses dan tidak lagi menunggu kelulusan.'),
            default => session()->flash('message', $decision === UserStatus::Active
                ? 'Permohonan ' . substr($result, 3) . ' telah diluluskan dengan Tahap Capaian ' . self::APPROVAL_ROLES[$approvedRole] . '. Akaun kini aktif.'
                : 'Permohonan ' . substr($result, 3) . ' telah ditolak.'),
        };
    }

    /**
     * UM9: rekod audit custom OwenIt bagi keputusan kelulusan & tahap capaian yang diberikan
     * (perubahan peranan Spatie tidak diaudit secara automatik). Tiada maklumat rahsia direkod.
     */
    protected function auditApproval(User $applicant, array $previousRoles, string $approvedRole, User $reviewer): void
    {
        RoleChangeAudit::record(
            $applicant,
            'approved',
            ['status' => UserStatus::Pending->value, 'roles' => $previousRoles],
            ['status' => UserStatus::Active->value, 'roles' => [$approvedRole], 'approved_by' => $reviewer->id],
        );
    }

    /**
     * Permohonan Self Register: hanya rekod `pending` (untuk tindakan) atau `rejected` (rujukan).
     */
    protected function applications()
    {
        return User::query()->whereIn('status', [UserStatus::Pending->value, UserStatus::Rejected->value]);
    }

    protected function ensureUrusSetia(): void
    {
        $user = auth()->user();

        // Disemak semula pada setiap tindakan Livewire (middleware route tidak diulang untuk permintaan Livewire).
        abort_unless($user?->hasRole('pentadbir_urus_setia') && $user->canUseSystem(), 403);
    }

    /**
     * No. KP disembunyikan sebahagiannya dalam senarai; nombor penuh hanya dalam paparan butiran.
     */
    public static function maskIc(?string $ic): string
    {
        return $ic ? substr($ic, 0, 6) . '****' . substr($ic, -2) : '—';
    }

    public function render()
    {
        $status = $this->statusFilter === UserStatus::Rejected->value ? UserStatus::Rejected : UserStatus::Pending;

        $requests = $this->applications()
            ->where('status', $status->value)
            ->with(['agency:id,name', 'positionRelation:id,title', 'grade:id,code'])
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('no_kad_pengenalan', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at')
            ->paginate(10);

        $viewing = $this->viewingUuid
            ? $this->applications()
                ->with(['agency:id,name', 'positionRelation.serviceScheme', 'grade:id,code'])
                ->where('uuid', $this->viewingUuid)
                ->first()
            : null;

        return view('livewire.users.registration-requests', [
            'requests' => $requests,
            'viewing' => $viewing,
            'pendingCount' => User::pending()->count(),
            'approvalRoleOptions' => self::APPROVAL_ROLES,
        ]);
    }
}
