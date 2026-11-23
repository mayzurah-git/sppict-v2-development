<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * SP2: Urus Setia membuka semula struktur fasa/aktiviti yang telah dikunci (keputusan L.3).
 * Rekod sejarah: tidak boleh dikemas kini atau dipadam.
 */
class ProjectStructureUnlock extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id',
        'locked_at',
        'remarks',
        'unlocked_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Rekod sejarah buka kunci struktur tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Rekod sejarah buka kunci struktur tidak boleh dipadam.'));
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function unlocker()
    {
        return $this->belongsTo(User::class, 'unlocked_by')->withTrashed();
    }
}
