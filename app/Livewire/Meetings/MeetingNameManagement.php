<?php

namespace App\Livewire\Meetings;

use App\Models\MeetingName;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MeetingNameManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?string $meetingNameUuid = null;
    public string $code = '';
    public string $name = '';
    public bool $accepts_applications = false;
    public int $sort_order = 0;
    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(?string $uuid = null): void
    {
        $this->resetErrorBag();
        $this->meetingNameUuid = $uuid;

        if ($uuid) {
            $meetingName = MeetingName::where('uuid', $uuid)->firstOrFail();
            $this->code = $meetingName->code;
            $this->name = $meetingName->name;
            $this->accepts_applications = $meetingName->accepts_applications;
            $this->sort_order = $meetingName->sort_order;
            $this->is_active = $meetingName->is_active;
        } else {
            $this->reset(['code', 'name', 'accepts_applications', 'meetingNameUuid']);
            $this->sort_order = (int) MeetingName::max('sort_order') + 1;
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['code', 'name', 'accepts_applications', 'sort_order', 'is_active', 'meetingNameUuid']);
    }

    public function save(): void
    {
        $this->ensureUrusSetia();

        $this->code = strtoupper(trim($this->code));
        $this->name = trim($this->name);

        $this->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('meeting_names', 'code')->ignore($this->meetingNameUuid, 'uuid'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'accepts_applications' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'code.unique' => 'Kod Mesyuarat ini telah wujud.',
        ], [
            'code' => 'Kod Mesyuarat',
            'name' => 'Nama Mesyuarat',
            'sort_order' => 'Susunan',
        ]);

        $meetingName = $this->meetingNameUuid
            ? MeetingName::where('uuid', $this->meetingNameUuid)->firstOrFail()
            : new MeetingName();

        $meetingName->fill([
            'code' => $this->code,
            'name' => $this->name,
            'accepts_applications' => $this->accepts_applications,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ]);
        $meetingName->save();

        session()->flash('message', $this->meetingNameUuid
            ? 'Nama Mesyuarat berjaya dikemaskini!'
            : 'Nama Mesyuarat baharu berjaya didaftarkan!');

        $this->closeModal();
    }

    public function setStatus(string $uuid, bool $active): void
    {
        $this->ensureUrusSetia();

        MeetingName::where('uuid', $uuid)->firstOrFail()->update(['is_active' => $active]);

        session()->flash('message', $active
            ? 'Nama Mesyuarat berjaya diaktifkan.'
            : 'Nama Mesyuarat berjaya dinyahaktifkan.');
    }

    public function delete(string $uuid): void
    {
        $this->ensureUrusSetia();

        $meetingName = MeetingName::where('uuid', $uuid)->firstOrFail();

        if ($meetingName->meetings()->withTrashed()->exists()) {
            session()->flash('error', 'Nama Mesyuarat tidak boleh dipadam kerana telah digunakan oleh rekod mesyuarat. Sila nyahaktifkan sahaja.');

            return;
        }

        $meetingName->delete();

        session()->flash('message', 'Nama Mesyuarat berjaya dipadamkan secara soft delete.');
    }

    protected function ensureUrusSetia(): void
    {
        abort_unless(auth()->user()?->hasRole('pentadbir_urus_setia'), 403);
    }

    public function render()
    {
        $meetingNames = MeetingName::query()
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->ordered()
            ->paginate(10);

        return view('livewire.meetings.meeting-name-management', [
            'meetingNames' => $meetingNames,
        ]);
    }
}
