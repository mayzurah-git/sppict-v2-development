<?php

namespace App\Models;

use App\Enums\ProjectActivityStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * SP2: aktiviti projek dalam satu fasa (audit SP0 F.3). Nama, pemberat dan keperluan dokumen ialah
 * snapshot templat. Satu tarikh sasaran (I.7). Tanda siap + tarikh sebenar siap (L.1) dikemas kini dalam SP3.
 */
class ProjectProgressActivity extends Model implements Auditable
{
    use HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'project_id',
        'project_phase_id',
        'progress_template_activity_id',
        'sequence',
        'name',
        'weight',
        'requires_document',
        'document_label',
        'target_date',
        'is_completed',
        'actual_completion_date',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'weight' => 'decimal:2',
        'requires_document' => 'boolean',
        'target_date' => 'date',
        'is_completed' => 'boolean',
        'actual_completion_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function phase()
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }

    public function templateActivity()
    {
        return $this->belongsTo(ProgressTemplateActivity::class, 'progress_template_activity_id');
    }

    /**
     * Status diterbitkan pada tarikh penilaian: Selesai / Lewat (belum siap dan tarikh sasaran telah berlalu) / Belum Siap.
     */
    public function statusOn(?CarbonInterface $date = null): ProjectActivityStatus
    {
        $date ??= today();

        if ($this->is_completed) {
            return ProjectActivityStatus::Completed;
        }

        return $this->target_date !== null && $this->target_date->lt($date->copy()->startOfDay())
            ? ProjectActivityStatus::Late
            : ProjectActivityStatus::Pending;
    }
}
