<?php

namespace App\Models;

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

    public function serviceScheme()
    {
        return $this->belongsTo(ServiceScheme::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
