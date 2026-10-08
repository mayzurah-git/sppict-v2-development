<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Master data Kategori Projek (Fasa 3: Perincian Projek).
 */
class ProjectCategory extends Model implements Auditable
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

    /**
     * Prefix paparan dijana daripada sort_order: 1 → A, 2 → B, ...
     */
    public function prefix(): ?string
    {
        return $this->sort_order >= 1 && $this->sort_order <= 26
            ? chr(64 + $this->sort_order)
            : null;
    }

    /**
     * Label dropdown, contoh: "A. PROJEK BAHARU".
     */
    public function label(): string
    {
        $name = mb_strtoupper($this->name);

        return $this->prefix() ? "{$this->prefix()}. {$name}" : $name;
    }

    public function applicationDetails()
    {
        return $this->hasMany(ApplicationDetail::class);
    }

    /**
     * Telah digunakan oleh mana-mana perincian permohonan (termasuk yang di-soft delete)?
     * Semak juga snapshot teks item_category kerana rekod lama belum tentu mempunyai FK.
     */
    public function isInUse(): bool
    {
        return ApplicationDetail::withTrashed()
            ->where(fn ($query) => $query->where('project_category_id', $this->id)
                ->orWhere('item_category', $this->name))
            ->exists();
    }
}
