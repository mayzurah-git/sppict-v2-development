<?php

namespace App\Livewire\Meetings;

use App\Models\MeetingNumber;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MeetingNumberManagement extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?string $meetingNumberUuid = null;
    public ?int $number = null;
    public bool $is_active = true;

    public function mount(): void
    {
        $this->ensureUrusSetia();
    }

    public function openModal(?string $uuid = null): void
    {
        $this->ensureUrusSetia();
        $this->resetErrorBag();
        $this->meetingNumberUuid = $uuid;

        if ($uuid) {
            $meetingNumber = MeetingNumber::where('uuid', $uuid)->firstOrFail();
            $this->number = $meetingNumber->number;
            $this->is_active = $meetingNumber->is_active;
        } else {
            $this->reset(['meetingNumberUuid']);
            $this->number = (int) MeetingNumber::withTrashed()->max('number') + 1;
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['number', 'is_active', 'meetingNumberUuid']);
    }

    public function save(): void
    {
        $this->ensureUrusSetia();

        $this->validate([
            'number' => [
                'required',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('meeting_numbers', 'number')->ignore($this->meetingNumberUuid, 'uuid'),
            ],
            'is_active' => ['boolean'],
        ], [
            'number.unique' => 'Bilangan Mesyuarat ini telah wujud.',
        ], [
            'number' => 'Bilangan Mesyuarat',
        ]);

        $meetingNumber = $this->meetingNumberUuid
            ? MeetingNumber::where('uuid', $this->meetingNumberUuid)->firstOrFail()
            : new MeetingNumber();

        $meetingNumber->fill([
            'number' => $this->number,
            'is_active' => $this->is_active,
        ]);
        $meetingNumber->save();

        session()->flash('message', $this->meetingNumberUuid
            ? 'Bilangan Mesyuarat berjaya dikemaskini!'
            : 'Bilangan Mesyuarat baharu berjaya didaftarkan!');

        $this->closeModal();
    }

    public function setStatus(string $uuid, bool $active): void
    {
        $this->ensureUrusSetia();

        MeetingNumber::where('uuid', $uuid)->firstOrFail()->update(['is_active' => $active]);

        session()->flash('message', $active
            ? 'Bilangan Mesyuarat berjaya diaktifkan.'
            : 'Bilangan Mesyuarat berjaya dinyahaktifkan.');
    }

    public function delete(string $uuid): void
    {
        $this->ensureUrusSetia();

        $meetingNumber = MeetingNumber::where('uuid', $uuid)->firstOrFail();

        if ($meetingNumber->meetings()->withTrashed()->exists()) {
            session()->flash('error', 'Bilangan Mesyuarat tidak boleh dipadam kerana telah digunakan oleh rekod mesyuarat. Sila nyahaktifkan sahaja.');

            return;
        }

        $meetingNumber->delete();

        session()->flash('message', 'Bilangan Mesyuarat berjaya dipadamkan secara soft delete.');
    }

    protected function ensureUrusSetia(): void
    {
        abort_unless(auth()->user()?->hasRole('pentadbir_urus_setia') && auth()->user()->canUseSystem(), 403);
    }

    public function render()
    {
        return view('livewire.meetings.meeting-number-management', [
            'meetingNumbers' => MeetingNumber::orderBy('number')->paginate(10),
        ]);
    }
}
