<?php

namespace App\Models;

use App\Enums\ClosureEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * SP4: satu peristiwa penutupan projek (keputusan I.6). Rekod sejarah: tidak boleh diubah atau dipadam.
 */
class ProjectClosureEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id',
        'event_type',
        'remarks',
        'actor_id',
    ];

    protected $casts = [
        'event_type' => ClosureEventType::class,
        'created_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Rekod sejarah penutupan projek tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Rekod sejarah penutupan projek tidak boleh dipadam.'));
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
