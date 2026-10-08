<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Master data Kategori Permohonan. `code` ialah nilai yang disimpan dalam
 * applications.application_category.
 */
class ApplicationCategory extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'application_category', 'code');
    }

    /**
     * Telah digunakan oleh mana-mana permohonan (termasuk yang di-soft delete)?
     */
    public function isInUse(): bool
    {
        return $this->applications()->withTrashed()->exists();
    }
}
