<?php

namespace App\Models;

use App\Enums\ProgressUpdateType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * SP3: satu perubahan tanda siap aktiviti (audit SP0 F.6; keputusan L.1).
 * Rekod sejarah: tidak boleh dikemas kini atau dipadam.
 */
class ProjectProgressUpdate extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id',
        'project_progress_activity_id',
        'change_type',
        'completion_date',
        'remarks',
        'updated_by',
    ];

    protected $casts = [
        'change_type' => ProgressUpdateType::class,
        'completion_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Rekod sejarah kemajuan aktiviti tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Rekod sejarah kemajuan aktiviti tidak boleh dipadam.'));
    }

    public function activity()
    {
        return $this->belongsTo(ProjectProgressActivity::class, 'project_progress_activity_id')->withTrashed();
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
