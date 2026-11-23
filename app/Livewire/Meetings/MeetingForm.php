<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use App\Models\MeetingName;
use App\Models\MeetingNumber;
use App\Models\MeetingYear;
use App\Rules\DocumentSignature;
use App\Notifications\MeetingAnnouncement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mews\Purifier\Facades\Purifier;

/**
 * Daftar Mesyuarat (tanpa UUID) dan Kemaskini Mesyuarat (dengan UUID).
 */
#[Layout('layouts.app')]
class MeetingForm extends Component
{
    use WithFileUploads;

    public const MAX_MINUTES_KB = 25600; // 25MB

    public ?string $meetingUuid = null;

    public $meeting_name_id = '';
    public $meeting_number_id = '';
    public $meeting_year_id = '';
    public string $meeting_date = '';
    public string $meeting_time = '';
    public string $venue = '';
    public string $is_active = '1';
    public string $submission_closes_at = '';
    public string $progress_update_deadline = '';
    public $minutes = null;
    public string $agenda = '';
    public bool $send_notification = false;

    /**
     * Amaran susunan Bil./tarikh yang perlu disahkan sebelum simpan (bukan sekatan, kerana Mesyuarat Khas dibenarkan).
     *
     * @var array<int, string>
     */
    public array $sequenceWarnings = [];

    public function mount(?string $uuid = null): void
    {
        $this->ensureUrusSetia();

        if (! $uuid) {
            return;
        }

        $meeting = Meeting::where('uuid', $uuid)->firstOrFail();

        $this->meetingUuid = $meeting->uuid;
        $this->meeting_name_id = (string) $meeting->meeting_name_id;
        $this->meeting_number_id = (string) $meeting->meeting_number_id;
        $this->meeting_year_id = (string) $meeting->meeting_year_id;
        $this->meeting_date = $meeting->meeting_date?->format('Y-m-d') ?? '';
        $this->meeting_time = $meeting->meeting_time ? substr($meeting->meeting_time, 0, 5) : '';
        $this->venue = $meeting->venue ?? '';
        $this->is_active = $meeting->is_active ? '1' : '0';
        $this->submission_closes_at = $meeting->submission_closes_at?->format('Y-m-d') ?? '';
        $this->progress_update_deadline = $meeting->progress_update_deadline?->format('Y-m-d') ?? '';
        $this->agenda = $meeting->agenda ?? '';
    }

    public function updatedMinutes(): void
    {
        $meeting = $this->meetingUuid ? Meeting::where('uuid', $this->meetingUuid)->firstOrFail() : new Meeting();

        $this->validateOnly('minutes', ['minutes' => $this->meetingRules($meeting)['minutes']], $this->messages(), $this->validationAttributes());
    }

    /**
     * Sebarang perubahan pada borang membatalkan amaran yang sedang dipaparkan (perlu disemak semula).
     */
    public function updated(string $property): void
    {
        if ($property !== 'sequenceWarnings') {
            $this->sequenceWarnings = [];
        }
    }

    public function updatedMeetingNameId(): void
    {
        $this->suggestNextNumber();
    }

    public function updatedMeetingYearId(): void
    {
        $this->suggestNextNumber();
    }

    /**
     * Daftar baharu: cadangkan Bil. seterusnya (Bil. terakhir didaftarkan + 1) bagi Nama & Tahun yang dipilih.
     */
    private function suggestNextNumber(): void
    {
        if ($this->meetingUuid || ! $this->meeting_name_id || ! $this->meeting_year_id) {
            return;
        }

        $next = (int) $this->siblingMeetings()->keys()->max() + 1;
        $suggested = MeetingNumber::active()->where('number', $next)->first();

        if ($suggested) {
            $this->meeting_number_id = (string) $suggested->id;
        }
    }

    public function save(bool $confirmed = false)
    {
        $this->ensureUrusSetia();

        $meeting = $this->meetingUuid
            ? Meeting::where('uuid', $this->meetingUuid)->firstOrFail()
            : new Meeting();

        $this->venue = trim($this->venue);
        $this->validate($this->meetingRules($meeting), $this->messages(), $this->validationAttributes());

        $agenda = $this->sanitizedAgenda();
        $this->ensureUniqueMeeting($meeting);
        $this->ensureNameUnchangedWhenLinked($meeting);

        if (! $confirmed) {
            $this->sequenceWarnings = $this->checkSequence($meeting);

            if ($this->sequenceWarnings !== []) {
                return null;
            }
        }

        $this->sequenceWarnings = [];

        $isActive = $this->is_active === '1';
        $meetingName = MeetingName::withTrashed()->findOrFail($this->meeting_name_id);

        // Tempoh permohonan dibuka apabila mesyuarat didaftar/ditukar kepada Aktif.
        $opensAt = $isActive
            ? ($meeting->exists && $meeting->is_active && $meeting->submission_opens_at
                ? $meeting->submission_opens_at->toDateString()
                : today()->toDateString())
            : $meeting->submission_opens_at?->toDateString();

        // UUID dijana awal supaya fail minit disimpan di bawah folder mesyuarat ini.
        $meeting->uuid ??= (string) Str::uuid();

        $storedPath = $this->minutes
            ? $this->minutes->store("meetings/{$meeting->uuid}/minutes", Meeting::MINUTES_DISK)
            : null;

        $isUpdate = $meeting->exists;
        $notifiedCount = 0;

        try {
            DB::transaction(function () use ($meeting, $meetingName, $isActive, $opensAt, $agenda, $storedPath, $isUpdate, &$notifiedCount): void {
                if ($isActive && $meetingName->accepts_applications) {
                    $this->ensureNoOverlappingOpenMeeting($meeting, $opensAt);
                }

                $meeting->fill([
                    'meeting_name_id' => (int) $this->meeting_name_id,
                    'meeting_number_id' => (int) $this->meeting_number_id,
                    'meeting_year_id' => (int) $this->meeting_year_id,
                    'meeting_date' => $this->meeting_date,
                    'meeting_time' => $this->meeting_time,
                    'venue' => $this->venue,
                    'is_active' => $isActive,
                    'submission_opens_at' => $opensAt,
                    'submission_closes_at' => $this->submission_closes_at,
                    'progress_update_deadline' => $this->progress_update_deadline,
                    'agenda' => $agenda,
                ]);

                if ($storedPath) {
                    $meeting->minutes_file_path = $storedPath;
                }

                if ($meeting->exists) {
                    $meeting->updated_by = auth()->id();
                } else {
                    $meeting->created_by = auth()->id();
                }

                if ($this->send_notification) {
                    $meeting->notified_at = now();
                }

                $meeting->save();

                // Makluman emel melalui queue; dihantar hanya selepas transaksi berjaya (afterCommit).
                if ($this->send_notification) {
                    $recipients = Meeting::notificationRecipients();
                    $notifiedCount = $recipients->count();
                    Notification::send($recipients, new MeetingAnnouncement($meeting, $isUpdate));
                }
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk(Meeting::MINUTES_DISK)->delete($storedPath);
            }

            throw $e;
        }

        $message = $isUpdate
            ? "Mesyuarat {$meeting->label()} berjaya dikemaskini."
            : "Mesyuarat {$meeting->label()} berjaya didaftarkan.";

        if ($this->send_notification) {
            $message .= " Notifikasi e-mel sedang dihantar kepada {$notifiedCount} ahli mesyuarat.";
        }

        session()->flash('message', $message);

        return $this->redirectRoute('meetings.index');
    }

    protected function meetingRules(Meeting $meeting): array
    {
        $needsMinutes = ! $meeting->hasMinutes();

        return [
            'meeting_name_id' => ['required', $this->masterDataRule('meeting_names', $meeting->meeting_name_id)],
            'meeting_number_id' => ['required', $this->masterDataRule('meeting_numbers', $meeting->meeting_number_id)],
            'meeting_year_id' => ['required', $this->masterDataRule('meeting_years', $meeting->meeting_year_id)],
            'meeting_date' => ['required', 'date_format:Y-m-d'],
            'meeting_time' => ['required', 'date_format:H:i'],
            'venue' => ['required', 'string', 'max:255'],
            'is_active' => ['required', Rule::in(['1', '0'])],
            'submission_closes_at' => array_merge(
                ['required', 'date_format:Y-m-d', 'before:meeting_date'],
                $meeting->exists ? [] : ['after_or_equal:today'],
            ),
            'progress_update_deadline' => ['required', 'date_format:Y-m-d'],
            'minutes' => [
                $needsMinutes ? 'required' : 'nullable',
                'file',
                'extensions:pdf',
                'mimes:pdf',
                'bail',
                new DocumentSignature(['pdf'], 'Minit Mesyuarat hendaklah dalam format PDF sahaja.'),
                'max:' . self::MAX_MINUTES_KB,
            ],
            'agenda' => ['required', 'string'],
            'send_notification' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'minutes.required' => 'Sila muat naik Minit Mesyuarat Terdahulu (PDF).',
            'minutes.extensions' => 'Minit Mesyuarat hendaklah dalam format PDF sahaja.',
            'minutes.mimes' => 'Minit Mesyuarat hendaklah dalam format PDF sahaja.',
            'minutes.max' => 'Saiz Minit Mesyuarat tidak boleh melebihi 25MB.',
            'minutes.file' => 'Fail gagal dimuat naik. Sila cuba semula.',
            'date_format' => 'Format :attribute tidak sah.',
            'submission_closes_at.before' => 'Tarikh Tutup Permohonan Projek mesti sebelum Tarikh Mesyuarat.',
            'submission_closes_at.after_or_equal' => 'Tarikh Tutup Permohonan Projek tidak boleh lebih awal daripada hari ini.',
            'exists' => ':attribute yang dipilih tidak sah atau tidak aktif.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'meeting_name_id' => 'Nama Mesyuarat',
            'meeting_number_id' => 'Bil. Mesyuarat',
            'meeting_year_id' => 'Tahun',
            'meeting_date' => 'Tarikh',
            'meeting_time' => 'Masa',
            'venue' => 'Tempat',
            'is_active' => 'Status Mesyuarat',
            'submission_closes_at' => 'Tarikh Tutup Permohonan Projek',
            'progress_update_deadline' => 'Tarikh Status Kemaskini Kemajuan Projek',
            'minutes' => 'Minit Mesyuarat Terdahulu',
            'agenda' => 'Agenda Mesyuarat',
        ];
    }

    /**
     * Pilihan mesti aktif dan belum dipadam; pilihan sedia ada rekod ini kekal sah walaupun telah dinyahaktifkan.
     */
    private function masterDataRule(string $table, ?int $currentId)
    {
        return Rule::exists($table, 'id')->where(function ($query) use ($currentId): void {
            $query->where(function ($query) use ($currentId): void {
                $query->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))
                    ->when($currentId, fn ($query) => $query->orWhere('id', $currentId));
            });
        });
    }

    private function sanitizedAgenda(): string
    {
        $agenda = trim(Purifier::clean($this->agenda, 'agenda'));
        $text = trim(str_replace("\u{A0}", ' ', html_entity_decode(strip_tags($agenda))));

        if ($text === '') {
            throw ValidationException::withMessages(['agenda' => 'Agenda Mesyuarat wajib diisi.']);
        }

        return $agenda;
    }

    private function ensureUniqueMeeting(Meeting $meeting): void
    {
        $exists = Meeting::where('meeting_name_id', $this->meeting_name_id)
            ->where('meeting_number_id', $this->meeting_number_id)
            ->where('meeting_year_id', $this->meeting_year_id)
            ->when($meeting->exists, fn ($query) => $query->whereKeyNot($meeting->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'meeting_number_id' => 'Mesyuarat dengan Nama, Bil. dan Tahun ini telah didaftarkan.',
            ]);
        }
    }

    private function ensureNameUnchangedWhenLinked(Meeting $meeting): void
    {
        if ($meeting->exists
            && (int) $this->meeting_name_id !== (int) $meeting->meeting_name_id
            && $meeting->hasLinkedApplications()) {
            throw ValidationException::withMessages([
                'meeting_name_id' => 'Nama Mesyuarat tidak boleh ditukar kerana mesyuarat ini telah mempunyai permohonan projek.',
            ]);
        }
    }

    /**
     * Hanya satu mesyuarat JTICT boleh dibuka untuk permohonan pada satu masa.
     */
    private function ensureNoOverlappingOpenMeeting(Meeting $meeting, string $opensAt): void
    {
        $overlap = Meeting::jtict()
            ->where('is_active', true)
            ->when($meeting->exists, fn ($query) => $query->whereKeyNot($meeting->getKey()))
            ->whereDate('submission_opens_at', '<=', $this->submission_closes_at)
            ->whereDate('submission_closes_at', '>=', $opensAt)
            ->lockForUpdate()
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'is_active' => "Tempoh permohonan bertindih dengan {$overlap->label()} "
                    . "({$overlap->submission_opens_at->format('d/m/Y')} – {$overlap->submission_closes_at->format('d/m/Y')}). "
                    . 'Tetapkan mesyuarat tersebut sebagai Tidak Aktif atau pilih Tarikh Tutup yang lebih awal.',
            ]);
        }
    }

    /**
     * Mesyuarat lain (belum dipadam) bagi Nama & Tahun yang dipilih, dikunci mengikut nombor Bil.
     *
     * @return \Illuminate\Support\Collection<int, Meeting>
     */
    private function siblingMeetings(): \Illuminate\Support\Collection
    {
        if (! $this->meeting_name_id || ! $this->meeting_year_id) {
            return collect();
        }

        return Meeting::with(['meetingName', 'meetingNumber', 'meetingYear'])
            ->where('meeting_name_id', $this->meeting_name_id)
            ->where('meeting_year_id', $this->meeting_year_id)
            ->when($this->meetingUuid, fn ($query) => $query->where('uuid', '!=', $this->meetingUuid))
            ->get()
            ->keyBy(fn (Meeting $meeting) => $meeting->meetingNumber->number)
            ->sortKeys();
    }

    /**
     * Amaran (bukan sekatan): Bil. sebelumnya belum didaftarkan, atau tarikh tidak mengikut susunan Bil.
     * Kemaskini hanya disemak jika Nama/Bil./Tahun/Tarikh diubah.
     *
     * @return array<int, string>
     */
    private function checkSequence(Meeting $meeting): array
    {
        if ($meeting->exists
            && (int) $this->meeting_name_id === $meeting->meeting_name_id
            && (int) $this->meeting_number_id === $meeting->meeting_number_id
            && (int) $this->meeting_year_id === $meeting->meeting_year_id
            && $this->meeting_date === $meeting->meeting_date?->format('Y-m-d')) {
            return [];
        }

        $code = MeetingName::withTrashed()->find($this->meeting_name_id)?->code;
        $year = MeetingYear::withTrashed()->find($this->meeting_year_id)?->year;
        $number = MeetingNumber::withTrashed()->find($this->meeting_number_id)?->number;
        $siblings = $this->siblingMeetings();
        $warnings = [];

        $missing = collect($number > 1 ? range(1, $number - 1) : [])->diff($siblings->keys())->values();

        if ($missing->isNotEmpty()) {
            $list = $missing->count() > 1
                ? $missing->slice(0, -1)->join(', ') . ' dan ' . $missing->last()
                : (string) $missing->first();

            $warnings[] = "{$code} Bil. {$list}/{$year} belum didaftarkan sebelum Bil. {$number}/{$year}.";
        }

        $date = Carbon::parse($this->meeting_date);
        $previous = $siblings->filter(fn ($sibling, $siblingNumber) => $siblingNumber < $number)->last();
        $next = $siblings->filter(fn ($sibling, $siblingNumber) => $siblingNumber > $number)->first();

        if ($previous && $previous->meeting_date->gt($date)) {
            $warnings[] = "Tarikh mesyuarat ini ({$date->format('d/m/Y')}) lebih awal daripada {$previous->label()} ({$previous->meeting_date->format('d/m/Y')}).";
        }

        if ($next && $next->meeting_date->lt($date)) {
            $warnings[] = "Tarikh mesyuarat ini ({$date->format('d/m/Y')}) lebih lewat daripada {$next->label()} ({$next->meeting_date->format('d/m/Y')}).";
        }

        return $warnings;
    }

    protected function ensureUrusSetia(): void
    {
        abort_unless(auth()->user()?->hasRole('pentadbir_urus_setia') && auth()->user()->canUseSystem(), 403);
    }

    public function render()
    {
        $meeting = $this->meetingUuid ? Meeting::where('uuid', $this->meetingUuid)->first() : null;
        $lastRegistered = $this->siblingMeetings()->last();

        // Pilihan aktif sahaja, ditambah pilihan sedia ada rekod ini (walaupun telah dinyahaktifkan).
        $options = function (string $model, ?int $currentId, callable $order) {
            $query = $model::query()->where(fn ($query) => $query->where('is_active', true)
                ->when($currentId, fn ($query) => $query->orWhere('id', $currentId)));

            return $order($query)->get();
        };

        return view('livewire.meetings.meeting-form', [
            'meeting' => $meeting,
            'lastRegistered' => $lastRegistered,
            'meetingNames' => $options(MeetingName::class, $meeting?->meeting_name_id, fn ($query) => $query->ordered()),
            'meetingNumbers' => $options(MeetingNumber::class, $meeting?->meeting_number_id, fn ($query) => $query->orderBy('number')),
            'meetingYears' => $options(MeetingYear::class, $meeting?->meeting_year_id, fn ($query) => $query->orderBy('year')),
        ]);
    }
}
