<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
        'project_manager_id',
        'contractor_name',
        'actual_cost',
        'start_date',
        'end_date',
        'actual_completion_date',
        'status_indicator',
        'physical_progress_percent',
        'financial_progress_percent',
        'is_completed',
    ];

    protected $casts = [
        'actual_cost' => 'decimal:2',
        'physical_progress_percent' => 'decimal:2',
        'financial_progress_percent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_completion_date' => 'date',
        'is_completed' => 'boolean',
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

    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
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
