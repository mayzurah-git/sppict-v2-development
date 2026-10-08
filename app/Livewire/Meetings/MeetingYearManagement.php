<?php

namespace App\Livewire\Meetings;

use App\Models\MeetingYear;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MeetingYearManagement extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?string $meetingYearUuid = null;
    public ?int $year = null;
    public bool $is_active = true;

    public function openModal(?string $uuid = null): void
    {
        $this->resetErrorBag();
        $this->meetingYearUuid = $uuid;

        if ($uuid) {
            $meetingYear = MeetingYear::where('uuid', $uuid)->firstOrFail();
            $this->year = $meetingYear->year;
            $this->is_active = $meetingYear->is_active;
        } else {
            $this->reset(['meetingYearUuid']);
            $latest = MeetingYear::withTrashed()->max('year');
            $this->year = $latest ? (int) $latest + 1 : now()->year;
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['year', 'is_active', 'meetingYearUuid']);
    }

    public function save(): void
    {
        $this->ensureUrusSetia();

        $this->validate([
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('meeting_years', 'year')->ignore($this->meetingYearUuid, 'uuid'),
            ],
            'is_active' => ['boolean'],
        ], [
            'year.unique' => 'Tahun Mesyuarat ini telah wujud.',
        ], [
            'year' => 'Tahun Mesyuarat',
        ]);

        $meetingYear = $this->meetingYearUuid
            ? MeetingYear::where('uuid', $this->meetingYearUuid)->firstOrFail()
            : new MeetingYear();

        $meetingYear->fill([
            'year' => $this->year,
            'is_active' => $this->is_active,
        ]);
        $meetingYear->save();

        session()->flash('message', $this->meetingYearUuid
            ? 'Tahun Mesyuarat berjaya dikemaskini!'
            : 'Tahun Mesyuarat baharu berjaya didaftarkan!');

        $this->closeModal();
    }

    public function setStatus(string $uuid, bool $active): void
    {
        $this->ensureUrusSetia();

        MeetingYear::where('uuid', $uuid)->firstOrFail()->update(['is_active' => $active]);

        session()->flash('message', $active
            ? 'Tahun Mesyuarat berjaya diaktifkan.'
            : 'Tahun Mesyuarat berjaya dinyahaktifkan.');
    }

    public function delete(string $uuid): void
    {
        $this->ensureUrusSetia();

        $meetingYear = MeetingYear::where('uuid', $uuid)->firstOrFail();

        if ($meetingYear->meetings()->withTrashed()->exists()) {
            session()->flash('error', 'Tahun Mesyuarat tidak boleh dipadam kerana telah digunakan oleh rekod mesyuarat. Sila nyahaktifkan sahaja.');

            return;
        }

        $meetingYear->delete();

        session()->flash('message', 'Tahun Mesyuarat berjaya dipadamkan secara soft delete.');
    }

    protected function ensureUrusSetia(): void
    {
        abort_unless(auth()->user()?->hasRole('pentadbir_urus_setia'), 403);
    }

    public function render()
    {
        return view('livewire.meetings.meeting-year-management', [
            'meetingYears' => MeetingYear::orderByDesc('year')->paginate(10),
        ]);
    }
}
