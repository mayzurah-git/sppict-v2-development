<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use OwenIt\Auditing\Contracts\Auditable;

class Meeting extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'type',
        'series_number',
        'year',
        'meeting_date',
        'meeting_time',
        'venue',
        'status',
        'minutes_file_path',
        'created_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
    ];

    // --- RELATIONSHIPS ---

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'jtict_meeting_id');
    }
}
