<?php

namespace App\Models;

// 1. Import Trait HasRoles dari Spatie & HasUuids / SoftDeletes
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;
    
    // 2. Masukkan HasRoles, HasUuids & SoftDeletes di sini
    use  HasUuids, SoftDeletes;

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
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'agency_id',
        'role_id',
        'position_id',
        'name',
        'email',
        'position',
        'phone_number',
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
        ];
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

        return static::query()
            ->where('agency_id', $agencyId)
            ->whereDoesntHave('roles', fn ($role) => $role->where('name', 'superadmin'))
            ->orderBy('name')
            ->get();
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

}
