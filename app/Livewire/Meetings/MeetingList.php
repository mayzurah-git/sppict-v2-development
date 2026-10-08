<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use App\Models\MeetingName;
use App\Models\MeetingYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MeetingList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'nama', except: '')]
    public string $meetingNameId = '';

    #[Url(as: 'tahun', except: '')]
    public string $meetingYearId = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'meetingNameId', 'meetingYearId', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'meetingNameId', 'meetingYearId', 'status']);
        $this->resetPage();
    }

    public function delete(string $uuid): void
    {
        $this->ensureUrusSetia();

        $meeting = Meeting::where('uuid', $uuid)->firstOrFail();

        if ($meeting->hasLinkedApplications()) {
            session()->flash('error', "Mesyuarat {$meeting->label()} tidak boleh dipadam kerana telah mempunyai permohonan projek. Sila tetapkan sebagai Tidak Aktif.");

            return;
        }

        $meeting->delete();
        session()->flash('message', "Mesyuarat {$meeting->label()} berjaya dipadamkan secara soft delete.");
    }

    protected function ensureUrusSetia(): void
    {
        abort_unless(auth()->user()?->hasRole('pentadbir_urus_setia'), 403);
    }

    public function render()
    {
        $meetings = Meeting::query()
            ->with(['meetingName', 'meetingNumber', 'meetingYear'])
            ->when($this->search, fn ($query) => $query->where('venue', 'like', '%' . $this->search . '%'))
            ->when($this->meetingNameId, fn ($query) => $query->where('meeting_name_id', $this->meetingNameId))
            ->when($this->meetingYearId, fn ($query) => $query->where('meeting_year_id', $this->meetingYearId))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === '1'))
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.meetings.meeting-list', [
            'meetings' => $meetings,
            'meetingNames' => MeetingName::ordered()->get(),
            'meetingYears' => MeetingYear::orderByDesc('year')->get(),
        ]);
    }
}
