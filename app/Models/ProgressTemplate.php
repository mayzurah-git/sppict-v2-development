<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * SP1: templat pemberat kemajuan projek bagi satu Jenis Perolehan (audit SP0 F.1).
 *
 * Templat berversi. Templat yang telah digunakan oleh projek tidak boleh diubah (lihat
 * ProgressTemplateActivity); perubahan pemberat dibuat dengan mencipta versi baharu.
 */
class ProgressTemplate extends Model implements Auditable
{
    use HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    /** Jumlah pemberat wajib bagi setiap templat. */
    public const TOTAL_WEIGHT = 100;

    protected $fillable = [
        'code',
        'version',
        'name',
        'procurement_type_id',
        'allows_phases',
        'is_active',
        'effective_from',
    ];

    protected $casts = [
        'version' => 'integer',
        'allows_phases' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function procurementType()
    {
        return $this->belongsTo(ProcurementType::class)->withTrashed();
    }

    public function activities()
    {
        return $this->hasMany(ProgressTemplateActivity::class)->orderBy('sequence');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Templat aktif (versi tertinggi) bagi Jenis Perolehan. Null jika tiada templat: projek tidak boleh
     * dimulakan (audit SP0 E.3, H.13).
     */
    public static function activeFor(ProcurementType|int|null $procurementType): ?self
    {
        $id = $procurementType instanceof ProcurementType ? $procurementType->id : $procurementType;

        if ($id === null) {
            return null;
        }

        return static::active()
            ->where('procurement_type_id', $id)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Telah digunakan oleh mana-mana projek (termasuk yang di-soft delete)?
     */
    public function isInUse(): bool
    {
        return $this->exists && Project::withTrashed()->where('progress_template_id', $this->id)->exists();
    }

    public function totalWeight(): float
    {
        return round((float) $this->activities()->sum('weight'), 2);
    }
}
