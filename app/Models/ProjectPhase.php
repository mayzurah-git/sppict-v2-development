<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * SP2: satu fasa projek (audit SP0 F.2A). Projek Tidak Berfasa mempunyai satu fasa (phase_number 1).
 * Setiap fasa berjumlah 100% (Model 2, L.3); nilai cache diisi oleh pengiraan SP4.
 */
class ProjectPhase extends Model implements Auditable
{
    use HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'project_id',
        'phase_number',
        'name',
        'actual_progress_percent',
        'planned_progress_percent',
    ];

    protected $casts = [
        'phase_number' => 'integer',
        'actual_progress_percent' => 'decimal:2',
        'planned_progress_percent' => 'decimal:2',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function activities()
    {
        return $this->hasMany(ProjectProgressActivity::class)->orderBy('sequence');
    }

    public function displayName(): string
    {
        return $this->name ?: "Fasa {$this->phase_number}";
    }

    public function totalWeight(): float
    {
        return round((float) $this->activities->sum('weight'), 2);
    }
}
