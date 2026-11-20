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
    ];

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
