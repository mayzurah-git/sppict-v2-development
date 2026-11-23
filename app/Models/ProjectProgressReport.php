<?php

namespace App\Models;

use App\Enums\ProgressReportStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use LogicException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Laporan kemajuan bulanan projek. SP3 (audit SP0 F.4): satu laporan setiap bulan, Draf / Dihantar,
 * snapshot aktiviti pada tarikh penilaian; laporan Dihantar tidak boleh diubah atau dipadam.
 */
class ProjectProgressReport extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    public const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun',
        7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'project_id',
        'report_year',
        'report_month',
        'status',
        'evaluation_date',
        'due_date',
        'due_meeting_id',
        'is_late',
        'submitted_by',
        'submitted_at',
        'application_officer_id',
        'physical_actual',
        'physical_planned',
        'financial_actual',
        'financial_planned',
        'actual_progress_percent',
        'planned_progress_percent',
        'variance_percent',
        'work_summary',
        'issues_and_challenges',
        'corrective_action_plan',
        'verified_status',
        'verified_by',
        'verified_at',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'DRAFT',
        'is_late' => false,
    ];

    protected $casts = [
        'report_year' => 'integer',
        'report_month' => 'integer',
        'status' => ProgressReportStatus::class,
        'evaluation_date' => 'date',
        'due_date' => 'date',
        'is_late' => 'boolean',
        'submitted_at' => 'datetime',
        'physical_actual' => 'decimal:2',
        'physical_planned' => 'decimal:2',
        'financial_actual' => 'decimal:2',
        'financial_planned' => 'decimal:2',
        'actual_progress_percent' => 'decimal:2',
        'planned_progress_percent' => 'decimal:2',
        'variance_percent' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Laporan yang telah dihantar dikunci: pembetulan bukan dengan menimpa rekod.
        static::updating(function (self $report): void {
            if ($report->getOriginal('status') === ProgressReportStatus::Submitted) {
                throw new LogicException('Laporan kemajuan yang telah dihantar tidak boleh diubah.');
            }
        });

        static::deleting(function (self $report): void {
            if ($report->getOriginal('status') === ProgressReportStatus::Submitted) {
                throw new LogicException('Laporan kemajuan yang telah dihantar tidak boleh dipadam.');
            }
        });
    }

    public function isSubmitted(): bool
    {
        return $this->status === ProgressReportStatus::Submitted;
    }

    public function periodLabel(): string
    {
        return (self::MONTHS[$this->report_month] ?? $this->report_month) . ' ' . $this->report_year;
    }

    /** Hari terakhir bulan laporan. */
    public function periodEnd(): Carbon
    {
        return Carbon::create($this->report_year, $this->report_month, 1)->endOfMonth()->startOfDay();
    }

    // --- RELATIONSHIPS ---

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function dueMeeting()
    {
        return $this->belongsTo(Meeting::class, 'due_meeting_id')->withTrashed();
    }

    /**
     * Tempoh tugasan pegawai semasa laporan dihantar (kekal walaupun pegawai bertukar).
     */
    public function officerAssignment()
    {
        return $this->belongsTo(ApplicationOfficer::class, 'application_officer_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** SP3: snapshot aktiviti pada tarikh penilaian. */
    public function activitySnapshots()
    {
        return $this->hasMany(ProjectProgressReportActivity::class)->orderBy('phase_number')->orderBy('sequence');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectProgressAttachment::class);
    }
}
