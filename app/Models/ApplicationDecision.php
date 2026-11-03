<?php

namespace App\Models;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\DecisionStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * PP4: satu keputusan Pra JTICT atau JTICT dalam sejarah keputusan permohonan.
 *
 * Rekod sejarah: tidak boleh dikemas kini atau dipadam. Keputusan baharu = rekod baharu,
 * dicipta melalui App\Services\ApplicationDecisionService (validasi status, tarikh & kebenaran).
 */
class ApplicationDecision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'stage',
        'decision',
        'from_status',
        'to_status',
        'remarks',
        'decision_date',
        'meeting_id',
        'decided_by',
    ];

    protected $casts = [
        'stage' => DecisionStage::class,
        'decision' => Decision::class,
        'decision_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        // Pasangan peringkat & keputusan mesti sah walaupun rekod dicipta di luar service.
        static::creating(function (self $decision): void {
            if (! $decision->stage instanceof DecisionStage
                || ! $decision->decision instanceof Decision
                || $decision->decision->stage() !== $decision->stage) {
                throw new InvalidArgumentException('Keputusan tidak sepadan dengan peringkat keputusan.');
            }
        });

        static::updating(fn () => throw new LogicException('Rekod sejarah keputusan tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Rekod sejarah keputusan tidak boleh dipadam.'));
    }

    // --- RELATIONSHIPS ---

    public function application()
    {
        return $this->belongsTo(Application::class)->withTrashed();
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class)->withTrashed();
    }
}
