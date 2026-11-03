<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * PP2: satu tindakan dalam sejarah semakan kelengkapan permohonan.
 * - Semakan Urus Setia: SUBMITTED -> COMPLETE / NEEDS_AMENDMENT (ulasan wajib bagi Tidak Lengkap)
 * - Penghantaran semula pemohon: NEEDS_AMENDMENT -> SUBMITTED, atau (PP4) PRE_JTICT_AMENDMENT -> SUBMITTED
 *
 * Rekod sejarah: tidak boleh dikemas kini atau dipadam. Semakan baharu = rekod baharu.
 */
class ApplicationReview extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'application_id',
        'from_status',
        'to_status',
        'remarks',
        'user_id',
        'jtict_meeting_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Rekod sejarah semakan tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Rekod sejarah semakan tidak boleh dipadam.'));
    }

    public function toStatus(): ?ApplicationStatus
    {
        return ApplicationStatus::tryFrom((string) $this->to_status);
    }

    /**
     * Keputusan semakan Urus Setia (bukan penghantaran semula).
     */
    public function isReviewDecision(): bool
    {
        return in_array($this->toStatus(), [ApplicationStatus::Complete, ApplicationStatus::NeedsAmendment], true);
    }

    public function isResubmission(): bool
    {
        return (bool) ApplicationStatus::tryFrom((string) $this->from_status)?->isAmendment()
            && $this->to_status === ApplicationStatus::Submitted->value;
    }

    public function actionLabel(): string
    {
        return match (true) {
            $this->toStatus() === ApplicationStatus::Complete => 'Ditetapkan Lengkap',
            $this->toStatus() === ApplicationStatus::NeedsAmendment => 'Ditetapkan Tidak Lengkap',
            $this->isResubmission() => 'Dihantar Semula',
            default => ApplicationStatus::labelFor($this->to_status),
        };
    }

    /**
     * Pihak yang bertindak, tanpa nama individu (untuk paparan kepada pemohon / agensi).
     */
    public function actorRoleLabel(): string
    {
        return $this->isReviewDecision() ? 'Urus Setia' : 'Pemohon';
    }

    // --- RELATIONSHIPS ---

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function jtictMeeting()
    {
        return $this->belongsTo(Meeting::class, 'jtict_meeting_id')->withTrashed();
    }
}
