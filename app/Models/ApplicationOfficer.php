<?php

namespace App\Models;

use App\Enums\OfficerEndReason;
use App\Enums\OfficerRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Satu tempoh tugasan Pegawai Bertanggungjawab bagi sesuatu permohonan/projek.
 * Rekod tidak ditulis ganti apabila pegawai bertukar; tempoh lama ditutup (ended_at)
 * dan rekod baharu dibuka supaya sejarah kekal.
 */
class ApplicationOfficer extends Model implements Auditable
{
    use HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'application_id',
        'user_id',
        'role',
        'agency_id',
        'name',
        'position',
        'grade',
        'email',
        'phone',
        'started_at',
        'ended_at',
        'end_reason',
        'handover_remarks',
        'assigned_by',
        'ended_by',
    ];

    protected $casts = [
        'role' => OfficerRole::class,
        'end_reason' => OfficerEndReason::class,
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    /**
     * Snapshot maklumat pegawai daripada profil pengguna semasa.
     */
    public static function snapshotFrom(User $user): array
    {
        $position = $user->positionRelation;

        return [
            'user_id' => $user->id,
            'agency_id' => $user->agency_id,
            'name' => $user->name,
            'position' => $user->getAttribute('position') ?: $position?->title,
            'grade' => $user->grade?->code ?? $position?->grade?->code,
            'email' => $user->email,
            'phone' => $user->phone_mobile ?: ($user->phone_office ?: $user->phone_number),
        ];
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    // --- SCOPES ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeRole(Builder $query, OfficerRole $role): Builder
    {
        return $query->where('role', $role->value);
    }

    // --- RELATIONSHIPS ---

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function ender()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
