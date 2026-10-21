<?php

namespace App\Models;

// 1. Import Trait HasRoles dari Spatie & HasUuids / SoftDeletes
use App\Enums\InactiveReason;
use App\Enums\UserStatus;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;


class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    // 2. Masukkan HasRoles, HasUuids & SoftDeletes di sini
    use  HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    /**
     * Medan sensitif yang tidak direkodkan dalam log audit.
     */
    protected $auditExclude = [
        'password',
        'remember_token',
    ];

    /**
     * Selaras dengan default database supaya model baharu (belum di-refresh) mempunyai status.
     */
    protected $attributes = [
        'status' => 'active',
        'must_change_password' => false,
    ];

    /**
     * Tentukan kolum UUID untuk HasUuids
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * `status`, `approved_by`, `approved_at`, `must_change_password`, `inactive_reason`,
     * `status_changed_at` dan `status_changed_by` sengaja tidak disenaraikan: ia hanya
     * ditetapkan secara eksplisit oleh aliran pendaftaran/kelulusan/status, bukan input borang.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'no_kad_pengenalan',
        'agency_id',
        'role_id',
        'position_id',
        'grade_id',
        'name',
        'email',
        'position',
        'phone_number',
        'phone_office',
        'phone_mobile',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'approved_at' => 'datetime',
            'must_change_password' => 'boolean',
            'inactive_reason' => InactiveReason::class,
            'status_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isInactive(): bool
    {
        return $this->status === UserStatus::Inactive;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Boleh menggunakan modul dalaman: akaun aktif dan tiada kata laluan sementara yang belum ditukar.
     * Digunakan oleh semakan `ensure…()` dalam tindakan Livewire (lapisan kedua selepas middleware).
     */
    public function canUseSystem(): bool
    {
        return $this->isActive() && ! $this->must_change_password;
    }

    /**
     * Superadmin aktif yang terakhir dalam sistem (UM10/UM11): peranan dan status akaun ini
     * tidak boleh diubah supaya sistem sentiasa mempunyai sekurang-kurangnya satu Superadmin aktif.
     */
    public function isLastActiveSuperadmin(): bool
    {
        return $this->isActive()
            && $this->hasRole('superadmin')
            && ! static::active()
                ->whereKeyNot($this->id)
                ->whereHas('roles', fn ($query) => $query->where('name', 'superadmin'))
                ->exists();
    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::Pending;
    }

    // --- SCOPES ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Pending->value);
    }

    /**
     * Pengguna aktif dalam agensi yang boleh dilantik sebagai Pegawai Bertanggungjawab.
     * Jika agensi tidak diketahui, hanya pengguna semasa (pemohon) dibenarkan.
     */
    public static function eligibleOfficersFor(?int $agencyId, ?self $fallback = null): EloquentCollection
    {
        if (! $agencyId) {
            return new EloquentCollection(array_filter([$fallback]));
        }

        // Hanya akaun aktif: pemohon Self Register yang belum diluluskan/ditolak tidak boleh dilantik.
        return static::query()
            ->active()
            ->where('agency_id', $agencyId)
            ->whereDoesntHave('roles', fn ($role) => $role->where('name', 'superadmin'))
            ->orderBy('name')
            ->get();
    }

    /**
     * No. rujukan permohonan/projek (bukan draf) di mana pengguna ini masih Pegawai Bertanggungjawab
     * aktif. Digunakan untuk amaran apabila agensi pengguna ditukar oleh pentadbir.
     */
    public function activeOfficerReferences(): \Illuminate\Support\Collection
    {
        return $this->officerAssignments()->active()
            ->whereHas('application', fn ($query) => $query->whereNotIn('status', Application::EDITABLE_STATUSES))
            ->with('application:id,reference_number')
            ->get()
            ->pluck('application.reference_number');
    }

    // --- RELATIONSHIPS ---

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    /**
     * Tugasan sebagai Pegawai Bertanggungjawab (termasuk sejarah).
     */
    public function officerAssignments()
    {
        return $this->hasMany(ApplicationOfficer::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Normalized position relationship. The legacy `position` text column is
     * intentionally retained and remains available as `$user->position`.
     */
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Explicit alias for callers that need to avoid the legacy attribute name.
     */
    public function positionRelation()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Gred pengguna (sumber utama gred). Mesti salah satu Gred yang sah bagi Jawatan pengguna.
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * Pegawai (Urus Setia) yang meluluskan akaun Self Register.
     */
    public function approver()
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    /**
     * UM11: pentadbir yang terakhir menukar status akaun (cth. menyahaktifkan).
     */
    public function statusChanger()
    {
        return $this->belongsTo(self::class, 'status_changed_by');
    }

}
