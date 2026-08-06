<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use OwenIt\Auditing\Contracts\Auditable;

class Application extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

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
        'project_category',
        'objectives',     
        'project_scope',  
        'procurement_type',
        'procurement_method',
        'ceiling_cost',
        'estimated_cost',
        'expected_duration_months',
        'outcome_code',
        //'justification',
        //'technical_impact',
        'primary_officer_name',
        'primary_officer_position',
        'primary_officer_email',
        'primary_officer_phone',
        'secondary_officer_name',
        'secondary_officer_position',
        'secondary_officer_email',
        'secondary_officer_phone',
        'status',
        'review_remarks',
        'pra_jtict_meeting_id',
        'jtict_meeting_id',
        'jpict_meeting_id',
        'jtict_decision',
        'jtict_remarks',
        'jpict_decision',
        'jpict_remarks',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
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

    public function praJtictMeeting()
    {
        return $this->belongsTo(Meeting::class, 'pra_jtict_meeting_id');
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
}
