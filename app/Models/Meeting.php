<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

class Meeting extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    public const MINUTES_DISK = 'local';

    /**
     * Peranan yang menerima Makluman Mesyuarat dan boleh membuka minit (superadmin dikecualikan).
     */
    public const MEMBER_ROLES = ['pengguna_biasa', 'pengurusan', 'pentadbir_urus_setia'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'meeting_name_id',
        'meeting_number_id',
        'meeting_year_id',
        'meeting_date',
        'meeting_time',
        'venue',
        'is_active',
        'submission_opens_at',
        'submission_closes_at',
        'progress_update_deadline',
        'agenda',
        'notified_at',
        'minutes_file_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'submission_opens_at' => 'date',
        'submission_closes_at' => 'date',
        'progress_update_deadline' => 'date',
        'is_active' => 'boolean',
        'notified_at' => 'datetime',
    ];

    // --- TEMPOH PENERIMAAN PERMOHONAN ---

    /**
     * Mesyuarat yang sedang menerima permohonan: berstatus Aktif dan tarikh hari ini dalam
     * tempoh buka–tutup (inklusif). Tempoh tutup secara automatik selepas tarikh tutup;
     * mesyuarat Tidak Aktif tidak lagi dikira.
     */
    public function scopeOpenForSubmission(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->where('is_active', true)
            ->whereNotNull('submission_opens_at')
            ->whereNotNull('submission_closes_at')
            ->whereDate('submission_opens_at', '<=', $today)
            ->whereDate('submission_closes_at', '>=', $today);
    }

    /**
     * Mesyuarat yang menerima permohonan projek (Nama Mesyuarat bertanda "Terima Permohonan", iaitu JTICT).
     */
    public function scopeJtict(Builder $query): Builder
    {
        return $query->whereHas('meetingName', fn (Builder $query) => $query->where('accepts_applications', true));
    }

    /**
     * Mesyuarat JTICT yang sedang dibuka untuk permohonan (hanya satu dibenarkan pada satu masa).
     */
    public static function currentJtict(): ?self
    {
        return static::jtict()->openForSubmission()->orderBy('submission_closes_at')->first();
    }

    public function isOpenForSubmission(): bool
    {
        return static::whereKey($this->getKey())->openForSubmission()->exists();
    }

    /**
     * Label paparan, contoh: "JTICTNS Bil. 3/2026".
     */
    public function label(): string
    {
        return "{$this->meetingName->code} {$this->seriesLabel()}";
    }

    /**
     * Contoh: "Bil. 3/2026".
     */
    public function seriesLabel(): string
    {
        return "Bil. {$this->meetingNumber->number}/{$this->meetingYear->year}";
    }

    /**
     * Semakan tanpa query (untuk senarai): Aktif, Nama Mesyuarat menerima permohonan,
     * dan hari ini dalam tempoh buka–tutup.
     */
    public function isAcceptingApplicationsToday(): bool
    {
        return $this->is_active
            && $this->meetingName?->accepts_applications
            && $this->submission_opens_at && $this->submission_closes_at
            && today()->between($this->submission_opens_at, $this->submission_closes_at);
    }

    /**
     * Format 12 jam, contoh: "9.30 pagi", "2.30 petang" (sama seperti pemilih masa dalam borang).
     */
    public function meetingTimeLabel(): string
    {
        if (! $this->meeting_time) {
            return '-';
        }

        $time = Carbon::createFromFormat('H:i', substr($this->meeting_time, 0, 5));

        return $time->format('g.i') . ($time->hour < 12 ? ' pagi' : ' petang');
    }

    public function hasMinutes(): bool
    {
        return filled($this->minutes_file_path);
    }

    /**
     * Ada permohonan (termasuk yang dipadam) yang dirujuk kepada mesyuarat ini sebagai JTICT atau JPICT?
     */
    public function hasLinkedApplications(): bool
    {
        return Application::withTrashed()
            ->where(fn (Builder $query) => $query->where('jtict_meeting_id', $this->id)->orWhere('jpict_meeting_id', $this->id))
            ->exists();
    }

    /**
     * Penerima Makluman Mesyuarat: pengguna aktif (belum dipadam) yang memegang peranan ahli
     * dan bukan superadmin.
     *
     * TODO: tapis juga users.is_active apabila status pengguna ditambah dalam Pengurusan Pengguna.
     */
    public static function notificationRecipients(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', self::MEMBER_ROLES))
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'superadmin'))
            ->get();
    }

    // --- RELATIONSHIPS ---

    public function meetingName()
    {
        return $this->belongsTo(MeetingName::class)->withTrashed();
    }

    public function meetingNumber()
    {
        return $this->belongsTo(MeetingNumber::class)->withTrashed();
    }

    public function meetingYear()
    {
        return $this->belongsTo(MeetingYear::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'jtict_meeting_id');
    }
}
