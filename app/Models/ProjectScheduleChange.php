<?php

namespace App\Models;

use App\Enums\ScheduleField;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * SP2: satu perubahan tarikh dalam pindaan jadual projek (keputusan I.3, audit SP0 L.6).
 *
 * Rekod sejarah: tidak boleh dikemas kini atau dipadam. Pindaan baharu = rekod baharu, dicipta melalui
 * App\Services\ProjectMonitoringService::amendSchedule().
 */
class ProjectScheduleChange extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'batch_uuid',
        'project_id',
        'project_progress_activity_id',
        'field',
        'old_value',
        'new_value',
        'justification',
        'changed_by',
    ];

    protected $casts = [
        'field' => ScheduleField::class,
        'old_value' => 'date',
        'new_value' => 'date',
        'created_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Rekod sejarah pindaan jadual tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Rekod sejarah pindaan jadual tidak boleh dipadam.'));
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function activity()
    {
        return $this->belongsTo(ProjectProgressActivity::class, 'project_progress_activity_id')->withTrashed();
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}
