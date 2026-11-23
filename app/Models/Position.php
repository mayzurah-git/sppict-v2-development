<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Position extends Model implements Auditable
{
    use HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'title',
        'service_scheme_id',
        'grade_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Jawatan yang boleh dipilih oleh pengguna: aktif, mempunyai Skim Perkhidmatan aktif
     * dan sekurang-kurangnya satu Gred yang boleh diberikan. Jawatan legacy tanpa skim/gred
     * yang sah tersingkir secara automatik.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('positions.is_active', true)
            ->whereHas('serviceScheme', fn (Builder $scheme) => $scheme->where('is_active', true))
            ->whereHas('grades', fn (Builder $grade) => $grade->assignable());
    }

    public function serviceScheme()
    {
        return $this->belongsTo(ServiceScheme::class);
    }

    /**
     * Gred yang sah bagi jawatan ini (sumber utama, many-to-many).
     */
    public function grades()
    {
        return $this->belongsToMany(Grade::class)->withTimestamps();
    }

    /**
     * Legacy: hubungan 1:1 lama melalui `positions.grade_id`. Kolum dikekalkan sementara;
     * gunakan `grades()` untuk logik baharu.
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
