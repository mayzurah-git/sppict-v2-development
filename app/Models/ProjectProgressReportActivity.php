<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SP3: snapshot satu aktiviti dalam laporan kemajuan bulanan pada tarikh penilaian (audit SP0 F.5).
 * Dicipta semasa laporan dihantar; tidak berubah selepas itu.
 */
class ProjectProgressReportActivity extends Model
{
    protected $fillable = [
        'project_progress_report_id',
        'project_progress_activity_id',
        'phase_number',
        'sequence',
        'name',
        'weight',
        'target_date',
        'is_planned_due',
        'is_completed',
        'actual_completion_date',
    ];

    protected $casts = [
        'phase_number' => 'integer',
        'sequence' => 'integer',
        'weight' => 'decimal:2',
        'target_date' => 'date',
        'is_planned_due' => 'boolean',
        'is_completed' => 'boolean',
        'actual_completion_date' => 'date',
    ];

    public function report()
    {
        return $this->belongsTo(ProjectProgressReport::class, 'project_progress_report_id');
    }

    public function activity()
    {
        return $this->belongsTo(ProjectProgressActivity::class, 'project_progress_activity_id')->withTrashed();
    }
}
