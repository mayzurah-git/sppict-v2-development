<?php

namespace App\Livewire\Admin;

use App\Models\ServiceScheme;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ServiceSchemeManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?string $serviceSchemeUuid = null;
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->ensureSuperadmin();
    }

    public function openModal(?string $uuid = null): void
    {
        $this->ensureSuperadmin();
        $this->resetErrorBag();
        $this->serviceSchemeUuid = $uuid;

        if ($uuid) {
            $scheme = ServiceScheme::where('uuid', $uuid)->firstOrFail();
            $this->code = $scheme->code;
            $this->name = $scheme->name;
            $this->description = $scheme->description ?? '';
            $this->is_active = $scheme->is_active;
        } else {
            $this->reset(['code', 'name', 'description', 'serviceSchemeUuid']);
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['code', 'name', 'description', 'is_active', 'serviceSchemeUuid']);
    }

    public function save(): void
    {
        $this->ensureSuperadmin();

        $this->code = strtoupper(trim($this->code));
        $this->name = trim($this->name);

        $this->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_schemes', 'code')->ignore($this->serviceSchemeUuid, 'uuid'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $scheme = $this->serviceSchemeUuid
            ? ServiceScheme::where('uuid', $this->serviceSchemeUuid)->firstOrFail()
            : new ServiceScheme();

        $scheme->fill([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'is_active' => $this->is_active,
        ]);
        $scheme->save();

        session()->flash('message', $this->serviceSchemeUuid
            ? 'Skim Perkhidmatan berjaya dikemaskini!'
            : 'Skim Perkhidmatan berjaya didaftarkan!');

        $this->closeModal();
    }

    public function setStatus(string $uuid, bool $active): void
    {
        $this->ensureSuperadmin();

        $scheme = ServiceScheme::where('uuid', $uuid)->firstOrFail();
        $scheme->update(['is_active' => $active]);

        session()->flash('message', $active
            ? 'Skim Perkhidmatan berjaya diaktifkan.'
            : 'Skim Perkhidmatan berjaya dinyahaktifkan.');
    }

    public function delete(string $uuid): void
    {
        $this->ensureSuperadmin();

        $scheme = ServiceScheme::where('uuid', $uuid)->firstOrFail();

        if ($scheme->positions()->withTrashed()->exists()) {
            session()->flash('error', 'Skim Perkhidmatan tidak boleh dipadam kerana masih mempunyai rekod Jawatan berkaitan.');

            return;
        }

        $scheme->delete();
        session()->flash('message', 'Skim Perkhidmatan berjaya dipadamkan secara soft delete.');
    }

    protected function ensureSuperadmin(): void
    {
        abort_unless(auth()->user()?->hasRole('superadmin') && auth()->user()->canUseSystem(), 403);
    }

    public function render()
    {
        $schemes = ServiceScheme::query()
            ->withCount('positions')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('code')
            ->paginate(10);

        return view('livewire.admin.service-scheme-management', [
            'schemes' => $schemes,
        ]);
    }
}
