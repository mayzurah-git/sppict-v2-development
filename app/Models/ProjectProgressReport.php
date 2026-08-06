<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use OwenIt\Auditing\Contracts\Auditable;

class ProjectProgressReport extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'project_id',
        'report_year',
        'report_month',
        'physical_actual',
        'physical_planned',
        'financial_actual',
        'financial_planned',
        'work_summary',
        'issues_and_challenges',
        'corrective_action_plan',
        'verified_status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'physical_actual' => 'decimal:2',
        'physical_planned' => 'decimal:2',
        'financial_actual' => 'decimal:2',
        'financial_planned' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
