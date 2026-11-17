<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Enums\ApplicationDecision as ApplicationDecisionEnum;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Contracts\Auditable;

class Application extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    /**
     * Status yang membenarkan pemohon mengemas kini permohonan & dokumen
     * (Draf / Tidak Lengkap / Pindaan Pra JTICT). Sumber: ApplicationStatus::editableCases().
     */
    public const EDITABLE_STATUSES = [
        ApplicationStatus::Draft->value,
        ApplicationStatus::NeedsAmendment->value,
        ApplicationStatus::PreJtictAmendment->value,
    ];

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /**
     * Status proses sebagai enum; null bagi nilai lama / tidak dikenali (paparan tidak gagal).
     * `status` sengaja tidak di-cast supaya rekod lama dengan nilai luar senarai masih boleh dibaca.
     */
    public function statusEnum(): ?ApplicationStatus
    {
        return ApplicationStatus::tryFrom((string) $this->status);
    }

    public function statusLabel(): string
    {
        return ApplicationStatus::labelFor($this->status);
    }

    /**
     * Mesyuarat JTICT yang akan menerima permohonan ini jika dihantar sekarang (tanpa pilihan pengguna).
     *
     * PP2: permohonan Tidak Lengkap kekal pada mesyuarat asalnya HANYA jika mesyuarat itu masih
     * menerima permohonan. Jika tidak, null dikembalikan dan pemohon mesti memilih Mesyuarat JTICT
     * aktif (lihat requiresMeetingSelection()). PP4: peraturan yang sama bagi Pindaan Pra JTICT.
     * Permohonan lain: Mesyuarat JTICT yang sedang dibuka.
     */
    public function targetJtictMeeting(): ?Meeting
    {
        if ($this->statusEnum()?->isAmendment() && $this->jtict_meeting_id) {
            return $this->requiresMeetingSelection() ? null : $this->jtictMeeting;
        }

        return Meeting::currentJtict();
    }

    /**
     * Permohonan Tidak Lengkap / Pindaan Pra JTICT yang mesyuarat asalnya tidak lagi menerima permohonan
     * (tidak aktif / di luar tempoh / dipadam): pemohon mesti memilih mesyuarat JTICT aktif.
     */
    public function requiresMeetingSelection(): bool
    {
        return $this->statusEnum()?->isAmendment()
            && $this->jtict_meeting_id
            && ! $this->jtictMeeting?->isOpenForSubmission();
    }

    /**
     * Pengguna ini pegawai bertanggungjawab yang aktif (Utama atau Kedua)?
     */
    public function isActiveOfficer(User $user): bool
    {
        return $this->officers()->active()->where('user_id', $user->id)->exists();
    }

    /**
     * Permohonan yang telah dihantar tetapi tiada Pegawai Utama aktif yang masih
     * berada dalam agensi pemohon (contoh: pegawai telah bertukar keluar / akaun dipadam).
     * Termasuk Tidak Lengkap / Pindaan Pra JTICT: hanya pegawai aktif boleh meminda, jadi tanpa
     * pegawai aktif permohonan tersangkut sehingga Urus Setia melantik pegawai baharu.
     */
    public function scopeNeedsOfficerReassignment(Builder $query): Builder
    {
        return $query->where('status', '!=', ApplicationStatus::Draft->value)
            ->whereDoesntHave('officers', function (Builder $officer) {
                $officer->active()
                    ->role(OfficerRole::Primary)
                    ->whereHas('user', fn (Builder $user) => $user->whereColumn('users.agency_id', 'applications.agency_id'));
            });
    }

    /**
     * Permohonan milik pemohon: didaftarkan olehnya, atau projek yang dia pegawai bertanggungjawab
     * aktif (termasuk yang diambil alih). Digunakan oleh senarai dan papan pemuka pengguna.
     */
    public function scopeForApplicant(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('applicant_id', $user->id)
                ->orWhereHas('officers', fn (Builder $officer) => $officer->active()->where('user_id', $user->id));
        });
    }

    /**
     * PP4.2: permohonan Lengkap yang menunggu keputusan Pra JTICT — telah disahkan Lengkap melalui
     * semakan PP2, mempunyai Mesyuarat JTICT, dan belum menerima keputusan muktamad (Diterima / Ditolak).
     * Sumber keputusan: application_decisions (bukan jtict_decision).
     */
    public function scopeAwaitingPreJtictDecision(Builder $query): Builder
    {
        return $query->where('status', ApplicationStatus::Complete->value)
            ->whereNotNull('jtict_meeting_id')
            ->whereHas('reviews', fn (Builder $review) => $review->where('to_status', ApplicationStatus::Complete->value))
            ->whereDoesntHave('decisions', fn (Builder $decision) => $decision->finalPreJtict());
    }

    /**
     * PP4.2: permohonan Lengkap yang tidak memenuhi syarat di atas (cth. rekod lama tanpa Mesyuarat JTICT
     * atau tanpa sejarah semakan PP2). Dipaparkan berasingan untuk tindakan manual; tidak diputuskan.
     */
    public function scopeNeedsManualPreJtictReview(Builder $query): Builder
    {
        return $query->where('status', ApplicationStatus::Complete->value)
            ->where(fn (Builder $q) => $q->whereNull('jtict_meeting_id')
                ->orWhereDoesntHave('reviews', fn (Builder $review) => $review->where('to_status', ApplicationStatus::Complete->value))
                ->orWhereHas('decisions', fn (Builder $decision) => $decision->finalPreJtict()));
    }

    /**
     * PP4.3A (PP42B-01): sebab keputusan Pra JTICT "Diterima" tidak dibenarkan bagi Mesyuarat JTICT
     * permohonan ini; null jika mesyuarat sah. Ditolak dengan Pindaan / Ditolak tidak terikat syarat ini.
     */
    public function preJtictAcceptanceBlocker(): ?string
    {
        if (! $this->jtict_meeting_id) {
            return 'Permohonan ini tiada Mesyuarat JTICT.';
        }

        $meeting = $this->jtictMeeting ?? Meeting::withTrashed()->find($this->jtict_meeting_id);

        return $meeting ? $meeting->preJtictAcceptanceBlocker() : 'Mesyuarat JTICT permohonan ini tidak wujud.';
    }

    /**
     * PP4.3A: ketidakselarasan yang memerlukan semakan manual sebelum keputusan JTICT boleh direkodkan
     * (bukan sekadar mesyuarat belum berlangsung); null jika rekod selaras. `is_active` tidak disyaratkan.
     */
    public function jtictMeetingIssue(): ?string
    {
        $accepted = $this->latestPreJtictDecision;

        if ($accepted?->decision !== ApplicationDecisionEnum::Accepted) {
            return 'Tiada keputusan Pra JTICT Diterima.';
        }

        if (! $this->jtict_meeting_id) {
            return 'Tiada Mesyuarat JTICT.';
        }

        $meeting = $this->jtictMeeting ?? Meeting::withTrashed()->find($this->jtict_meeting_id);

        return match (true) {
            ! $meeting || $meeting->trashed() => 'Mesyuarat JTICT telah dipadam atau tidak wujud.',
            ! $meeting->isJtict() => 'Mesyuarat bukan jenis JTICT.',
            $meeting->meeting_date->lt($accepted->decision_date) => "Tarikh mesyuarat ({$meeting->meeting_date->format('d/m/Y')}) lebih awal daripada tarikh keputusan Pra JTICT Diterima ({$accepted->decision_date->format('d/m/Y')}).",
            default => null,
        };
    }

    /**
     * PP4.3A: sebab keputusan JTICT belum boleh direkodkan atas faktor mesyuarat; null jika sedia diputuskan.
     */
    public function jtictMeetingBlocker(): ?string
    {
        if ($issue = $this->jtictMeetingIssue()) {
            return $issue;
        }

        $meeting = $this->jtictMeeting;

        return $meeting->hasTakenPlace()
            ? null
            : "Mesyuarat {$meeting->label()} belum berlangsung (tarikh mesyuarat {$meeting->meeting_date->format('d/m/Y')}). Keputusan hanya boleh direkodkan selepas mesyuarat bersidang.";
    }

    /**
     * PP4.3A: permohonan Dalam Proses JTICT yang mesyuaratnya telah berlangsung dan sedia diputuskan.
     */
    public function scopeAwaitingJtictDecision(Builder $query): Builder
    {
        return $query->pendingJtict()
            ->jtictMeetingConsistent()
            ->whereHas('jtictMeeting', fn (Builder $meeting) => $meeting->whereDate('meeting_date', '<=', today()));
    }

    /**
     * PP4.3A: permohonan Dalam Proses JTICT yang mesyuaratnya sah tetapi belum berlangsung.
     */
    public function scopeScheduledForJtict(Builder $query): Builder
    {
        return $query->pendingJtict()
            ->jtictMeetingConsistent()
            ->whereHas('jtictMeeting', fn (Builder $meeting) => $meeting->whereDate('meeting_date', '>', today()));
    }

    /**
     * PP4.3A: permohonan Dalam Proses JTICT dengan ketidakselarasan mesyuarat / keputusan Pra JTICT
     * (cth. tiada mesyuarat, mesyuarat dipadam atau lebih awal daripada tarikh Diterima). Tidak diputuskan.
     */
    public function scopeNeedsManualJtictReview(Builder $query): Builder
    {
        return $query->pendingJtict()
            ->where(fn (Builder $consistent) => $consistent->jtictMeetingConsistent(), null, null, 'and not');
    }

    /**
     * Dalam Proses JTICT dan belum mempunyai keputusan JTICT.
     */
    public function scopePendingJtict(Builder $query): Builder
    {
        return $query->where('status', ApplicationStatus::InJtict->value)
            ->whereDoesntHave('decisions', fn (Builder $decision) => $decision->where('stage', DecisionStage::Jtict->value));
    }

    /**
     * Keputusan Pra JTICT terkini Diterima; Mesyuarat JTICT wujud, tidak dipadam, jenis JTICT dan
     * tarikhnya tidak lebih awal daripada tarikh keputusan Diterima tersebut.
     */
    public function scopeJtictMeetingConsistent(Builder $query): Builder
    {
        $acceptedDate = '(SELECT d.decision_date FROM application_decisions d WHERE d.application_id = applications.id'
            . ' AND d.stage = ? ORDER BY d.id DESC LIMIT 1)';

        return $query->whereHas('latestPreJtictDecision', fn (Builder $decision) => $decision->where('decision', ApplicationDecisionEnum::Accepted->value))
            ->whereHas('jtictMeeting', fn (Builder $meeting) => $meeting->jtict()
                ->whereRaw("DATE(meetings.meeting_date) >= DATE({$acceptedDate})", [DecisionStage::PreJtict->value]));
    }

    /**
     * PP4.2: label status untuk paparan butiran / senarai Pra JTICT. Status Selesai dibezakan mengikut
     * peringkat keputusan sebenar (application_decisions); tiada andaian jika rekod keputusan tiada.
     * Label asas (penapis, kad papan pemuka) kekal melalui statusLabel().
     *
     * PP4.2A: Lengkap hanya dilabel "Menunggu Keputusan Pra JTICT" jika boleh diputuskan (ada Mesyuarat
     * JTICT dan tiada keputusan muktamad); permohonan lama / Perlu Semakan Manual kekal "Lengkap".
     * Untuk senarai, eager-load latestPreJtictDecision & latestJtictDecision.
     */
    public function statusDisplayLabel(): string
    {
        $status = $this->statusEnum();

        if (! in_array($status, [ApplicationStatus::Complete, ApplicationStatus::PreJtictAmendment, ApplicationStatus::InJtict, ApplicationStatus::Closed], true)) {
            return $this->statusLabel();
        }

        $preJtict = $this->latestPreJtictDecision?->decision;

        return match ($status) {
            ApplicationStatus::Complete => $this->jtict_meeting_id !== null
                && ! in_array($preJtict, [ApplicationDecisionEnum::Accepted, ApplicationDecisionEnum::Rejected], true)
                    ? 'Lengkap – Menunggu Keputusan Pra JTICT'
                    : $this->statusLabel(),
            ApplicationStatus::PreJtictAmendment => 'Ditolak dengan Pindaan – Menunggu Tindakan Pemohon',
            ApplicationStatus::InJtict => $preJtict === ApplicationDecisionEnum::Accepted
                ? 'Diterima – Menunggu Mesyuarat JTICT'
                : $this->statusLabel(),
            ApplicationStatus::Closed => match (true) {
                $this->latestJtictDecision !== null => "Selesai – {$this->latestJtictDecision->decision->label()} (JTICT)",
                $preJtict === ApplicationDecisionEnum::Rejected => 'Selesai – Ditolak (Pra JTICT)',
                default => $this->statusLabel(),
            },
            default => $this->statusLabel(),
        };
    }

    /**
     * Tentukan kolum UUID untuk HasUuids
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'reference_number',
        'agency_id',
        'applicant_id',
        'title',
        'application_category',
        'objectives',     
        'project_scope',  
        'procurement_type',
        'procurement_type_id',
        'procurement_method',
        'procurement_method_id',
        'ceiling_cost',
        'estimated_cost',
        'subtotal_cost',
        'sst_rate',
        'sst_amount',
        'total_cost',
        'expected_duration_months',
        'outcome_code',
        //'justification',
        //'technical_impact',
        // Kolum primary_/secondary_officer_* tidak digunakan lagi; lihat officers() (application_officers).
        'status',
        'review_remarks',
        'jtict_meeting_id',
        'jpict_meeting_id',
        'jtict_decision',
        'jtict_remarks',
        'jpict_decision',
        'jpict_remarks',
    ];

    protected $casts = [
        'ceiling_cost' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'subtotal_cost' => 'decimal:2',
        'sst_rate' => 'decimal:2',
        'sst_amount' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // --- RELATIONSHIPS ---

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function jtictMeeting()
    {
        return $this->belongsTo(Meeting::class, 'jtict_meeting_id');
    }

    public function jpictMeeting()
    {
        return $this->belongsTo(Meeting::class, 'jpict_meeting_id');
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }

    public function details()
    {
        return $this->hasMany(ApplicationDetail::class);
    }

    /**
     * PP2: sejarah semakan kelengkapan & penghantaran semula (rekod tidak boleh diubah).
     */
    public function reviews()
    {
        return $this->hasMany(ApplicationReview::class);
    }

    /**
     * PP4: sejarah keputusan Pra JTICT & JTICT (rekod tidak boleh diubah). Sumber rasmi keputusan;
     * lajur warisan jtict_decision tidak digunakan.
     */
    public function decisions()
    {
        return $this->hasMany(ApplicationDecision::class);
    }

    /**
     * PP4: keputusan Pra JTICT terkini (kitaran Pindaan Pra JTICT boleh menghasilkan beberapa keputusan).
     */
    public function latestPreJtictDecision()
    {
        return $this->hasOne(ApplicationDecision::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('stage', DecisionStage::PreJtict->value),
        );
    }

    /**
     * PP4: keputusan JTICT terkini.
     */
    public function latestJtictDecision()
    {
        return $this->hasOne(ApplicationDecision::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('stage', DecisionStage::Jtict->value),
        );
    }

    /**
     * PP4: permohonan sumber bagi permohonan salinan (Kaji Semula). `copied_from_application_id`
     * sengaja tiada dalam $fillable; ditetapkan hanya oleh fungsi Salin Permohonan (fasa akan datang).
     */
    public function copiedFromApplication()
    {
        return $this->belongsTo(self::class, 'copied_from_application_id')->withTrashed();
    }

    public function copies()
    {
        return $this->hasMany(self::class, 'copied_from_application_id');
    }

    /**
     * PP4.2: semakan PP2 terkini yang mengesahkan permohonan Lengkap (had awal tarikh keputusan Pra JTICT).
     */
    public function latestCompleteReview()
    {
        return $this->hasOne(ApplicationReview::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('to_status', ApplicationStatus::Complete->value),
        );
    }

    /**
     * Keputusan Tidak Lengkap terkini (ulasan yang perlu ditangani oleh pemohon).
     */
    public function latestAmendmentReview()
    {
        return $this->hasOne(ApplicationReview::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('to_status', ApplicationStatus::NeedsAmendment->value),
        );
    }

    /**
     * Master data Kategori Permohonan, dipadankan melalui code (application_category).
     */
    public function applicationCategoryMaster()
    {
        return $this->belongsTo(ApplicationCategory::class, 'application_category', 'code')->withTrashed();
    }

    public function procurementType()
    {
        return $this->belongsTo(ProcurementType::class)->withTrashed();
    }

    public function procurementMethod()
    {
        return $this->belongsTo(ProcurementMethod::class)->withTrashed();
    }

    /**
     * Sejarah penuh pegawai bertanggungjawab (aktif & terdahulu).
     */
    public function officers()
    {
        return $this->hasMany(ApplicationOfficer::class);
    }

    public function primaryOfficer()
    {
        return $this->hasOne(ApplicationOfficer::class)->active()->role(OfficerRole::Primary);
    }

    public function secondaryOfficer()
    {
        return $this->hasOne(ApplicationOfficer::class)->active()->role(OfficerRole::Secondary);
    }
}
