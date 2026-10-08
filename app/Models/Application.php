<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Enums\OfficerRole;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Contracts\Auditable;

class Application extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    /**
     * Status yang membenarkan pemohon mengemas kini permohonan & dokumen
     * (Draf / Tidak Lengkap).
     */
    public const EDITABLE_STATUSES = ['DRAFT', 'NEEDS_AMENDMENT'];

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /**
     * Mesyuarat JTICT yang akan menerima permohonan ini jika dihantar sekarang.
     * Permohonan Perlu Pindaan kekal pada mesyuarat asalnya (tidak perlu mesyuarat aktif);
     * selain itu mesti ada Mesyuarat JTICT yang sedang dibuka.
     */
    public function targetJtictMeeting(): ?Meeting
    {
        if ($this->status === 'NEEDS_AMENDMENT' && $this->jtict_meeting_id) {
            return $this->jtictMeeting;
        }

        return Meeting::currentJtict();
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
     */
    public function scopeNeedsOfficerReassignment(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::EDITABLE_STATUSES)
            ->whereDoesntHave('officers', function (Builder $officer) {
                $officer->active()
                    ->role(OfficerRole::Primary)
                    ->whereHas('user', fn (Builder $user) => $user->whereColumn('users.agency_id', 'applications.agency_id'));
            });
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
