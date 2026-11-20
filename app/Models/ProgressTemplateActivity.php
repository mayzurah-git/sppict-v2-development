<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * SP1: aktiviti berpemberat dalam templat kemajuan (audit SP0 F.2).
 *
 * Setiap aktiviti mempunyai satu tarikh sasaran dalam projek (keputusan I.7); tiada jenis jadual.
 * Aktiviti templat yang telah digunakan oleh projek tidak boleh ditambah, diubah atau dipadam (audit SP0 H.6).
 */
class ProgressTemplateActivity extends Model implements Auditable
{
    use HasUuids, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'progress_template_id',
        'sequence',
        'name',
        'weight',
        'requires_document',
        'document_label',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'weight' => 'decimal:2',
        'requires_document' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        $guard = function (self $activity): void {
            if ($activity->template?->isInUse()) {
                throw new LogicException('Templat kemajuan telah digunakan oleh projek dan tidak boleh diubah. Cipta versi baharu.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function template()
    {
        return $this->belongsTo(ProgressTemplate::class, 'progress_template_id')->withTrashed();
    }
}
