<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Grade extends Model implements Auditable
{
    use HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'scheme',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Gred master yang boleh diberikan kepada pengguna: aktif dan bukan legacy (`code` wujud).
     * Rekod soft-deleted dikecualikan oleh SoftDeletes.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('grades.is_active', true)->whereNotNull('grades.code');
    }

    /**
     * Jawatan yang membenarkan gred ini (pivot `grade_position`).
     */
    public function positions()
    {
        return $this->belongsToMany(Position::class)->withTimestamps();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
