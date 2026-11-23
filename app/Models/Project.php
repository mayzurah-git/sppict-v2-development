<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Enums\OfficerRole;
use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use OwenIt\Auditing\Contracts\Auditable;

class Project extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'application_id',
        'agency_id',
        'contractor_name',
        'actual_cost',
        'start_date',
        'end_date',
        'actual_completion_date',
        'status_indicator',
        'physical_progress_percent',
        'financial_progress_percent',
        'is_completed',
        // SP1: pemantauan kemajuan (audit SP0 F.0)
        'progress_template_id',
        'is_phased',
        'source',
        'legacy_reference',
        'status',
        'structure_locked_at',
        'planned_start_date',
        'planned_end_date',
        'actual_progress_percent',
        'planned_progress_percent',
        'variance_percent',
        'progress_evaluated_at',
        'started_by',
        'started_at',
        'closed_by',
        'closed_at',
        'closure_remarks',
        // SP2: kelulusan JPICT (pilihan, I.1)
        'jpict_approved_at',
        'jpict_reference',
        'jpict_recorded_by',
        'jpict_recorded_at',
    ];

    /** I.1: projek dengan jumlah kos sekurang-kurangnya nilai ini memerlukan kelulusan muktamad JPICT (AGENTS.md §3). */
    public const JPICT_THRESHOLD = 500000;

    /** L.3: had atas bilangan fasa bagi projek Berfasa. */
    public const MAX_PHASES = 10;

    protected $attributes = [
        'source' => 'V2',
        'status' => 'NOT_STARTED',
        'is_phased' => false,
    ];

    protected $casts = [
        'actual_cost' => 'decimal:2',
        'physical_progress_percent' => 'decimal:2',
        'financial_progress_percent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_completion_date' => 'date',
        'is_completed' => 'boolean',
        'is_phased' => 'boolean',
        'source' => ProjectSource::class,
        'status' => ProjectStatus::class,
        'structure_locked_at' => 'datetime',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_progress_percent' => 'decimal:2',
        'planned_progress_percent' => 'decimal:2',
        'variance_percent' => 'decimal:2',
        'progress_evaluated_at' => 'date',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'jpict_approved_at' => 'date',
        'jpict_recorded_at' => 'datetime',
    ];

    // --- LOGIK PERNIAGAAN INDIKATOR LAMPU ---

    /**
     * Hitung status indicator secara automatik berdasarkan perbezaan jadual
     */
    public function updateStatusIndicator(float $plannedPhysicalProgress): string
    {
        $variance = $plannedPhysicalProgress - $this->physical_progress_percent;

        if ($variance <= 0) {
            $this->status_indicator = 'GREEN'; // On-track / Mendahului
        } elseif ($variance > 0 && $variance < 20) {
            $this->status_indicator = 'AMBER'; // Lewat 1% - 19%
        } else {
            $this->status_indicator = 'RED';   // Sakit / Lewat >= 20%
        }

        $this->save();
        return $this->status_indicator;
    }

    // --- RELATIONSHIPS ---

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * SP1: versi templat pemberat yang dikunci pada projek.
     */
    public function progressTemplate()
    {
        return $this->belongsTo(ProgressTemplate::class)->withTrashed();
    }

    /**
     * SP1: projek dipindahkan daripada SPPICT v1 (Bank Projek).
     */
    public function isLegacy(): bool
    {
        return $this->source === ProjectSource::V1;
    }

    /**
     * SP1: rekod sejarah v1, baca sahaja.
     */
    public function isHistorical(): bool
    {
        return $this->status === ProjectStatus::Historical;
    }

    /**
     * SP1: struktur fasa dan aktiviti dikunci selepas aktiviti pertama ditanda siap (keputusan L.3).
     */
    public function isStructureLocked(): bool
    {
        return $this->structure_locked_at !== null;
    }

    /**
     * SP2: projek baca sahaja untuk semua pengguna (rekod sejarah v1, Selesai, Ditutup).
     */
    public function isReadOnly(): bool
    {
        return in_array($this->status, [ProjectStatus::Historical, ProjectStatus::Completed, ProjectStatus::Closed], true);
    }

    /**
     * SP2 (I.1): jumlah kos projek bagi ambang JPICT. Jumlah Keseluruhan permohonan (termasuk SST jika
     * ditanda); permohonan lama tanpa jumlah keseluruhan menggunakan anggaran kos.
     */
    public function totalCost(): ?float
    {
        $cost = $this->application?->total_cost ?? $this->application?->estimated_cost;

        return $cost === null ? null : (float) $cost;
    }

    /**
     * SP2 (I.1): projek >= RM500,000 memerlukan kelulusan muktamad JPICT.
     */
    public function requiresJpict(): bool
    {
        $cost = $this->totalCost();

        return $cost !== null && $cost >= self::JPICT_THRESHOLD;
    }

    /**
     * SP2 (I.1): penanda "Menunggu JPICT". Tidak menyekat pemantauan.
     */
    public function isAwaitingJpict(): bool
    {
        return ! $this->isLegacy() && $this->requiresJpict() && $this->jpict_approved_at === null;
    }

    public function phases()
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('phase_number');
    }

    public function activities()
    {
        return $this->hasMany(ProjectProgressActivity::class);
    }

    /**
     * Sejarah pindaan jadual (I.3), terkini dahulu.
     */
    public function scheduleChanges()
    {
        return $this->hasMany(ProjectScheduleChange::class)->latest('id');
    }

    /**
     * Sejarah buka kunci struktur oleh Urus Setia (L.3), terkini dahulu.
     */
    public function structureUnlocks()
    {
        return $this->hasMany(ProjectStructureUnlock::class)->latest('id');
    }

    /** SP3: dokumen sokongan kemajuan projek. */
    public function attachments()
    {
        return $this->hasMany(ProjectProgressAttachment::class);
    }

    /** SP3: sejarah tanda siap aktiviti, terkini dahulu. */
    public function progressUpdates()
    {
        return $this->hasMany(ProjectProgressUpdate::class)->latest('id');
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by')->withTrashed();
    }

    public function jpictRecorder()
    {
        return $this->belongsTo(User::class, 'jpict_recorded_by')->withTrashed();
    }

    /**
     * Pegawai Utama aktif bagi projek ini (sumber: application_officers melalui permohonan).
     */
    public function responsibleOfficer()
    {
        return $this->hasOne(ApplicationOfficer::class, 'application_id', 'application_id')
            ->active()
            ->role(OfficerRole::Primary);
    }

    public function progressReports()
    {
        return $this->hasMany(ProjectProgressReport::class);
    }

    public function midTermReview()
    {
        return $this->hasOne(MidTermReview::class);
    }
}
