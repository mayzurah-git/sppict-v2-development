<?php

namespace App\Livewire\Admin;

use App\Models\Grade;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class GradeManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?string $gradeUuid = null;
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
        $grade = $uuid ? Grade::where('uuid', $uuid)->firstOrFail() : null;

        if ($grade && $grade->code === null) {
            session()->flash('error', 'Gred legacy tidak boleh dikemaskini melalui modul master Gred.');

            return;
        }

        $this->gradeUuid = $uuid;

        if ($grade) {
            $this->code = $grade->code;
            $this->name = $grade->name;
            $this->description = $grade->description ?? '';
            $this->is_active = $grade->is_active;
        } else {
            $this->reset(['code', 'name', 'description', 'gradeUuid']);
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['code', 'name', 'description', 'is_active', 'gradeUuid']);
    }

    public function save(): void
    {
        $this->ensureSuperadmin();

        $this->code = trim($this->code);
        $this->name = trim($this->name);

        $this->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grades', 'code')->ignore($this->gradeUuid, 'uuid'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $grade = $this->gradeUuid
            ? Grade::where('uuid', $this->gradeUuid)->firstOrFail()
            : new Grade();

        abort_if($grade->exists && $grade->code === null, 403, 'Gred legacy tidak boleh diubah.');

        $grade->fill([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'is_active' => $this->is_active,
        ]);
        $grade->save();

        session()->flash('message', $this->gradeUuid
            ? 'Gred berjaya dikemaskini!'
            : 'Gred baharu berjaya didaftarkan!');

        $this->closeModal();
    }

    public function setStatus(string $uuid, bool $active): void
    {
        $this->ensureSuperadmin();

        $grade = Grade::where('uuid', $uuid)->firstOrFail();
        abort_if($grade->code === null, 403, 'Gred legacy tidak boleh diaktifkan atau dinyahaktifkan.');

        $grade->update(['is_active' => $active]);

        session()->flash('message', $active
            ? 'Gred berjaya diaktifkan.'
            : 'Gred berjaya dinyahaktifkan.');
    }

    public function delete(string $uuid): void
    {
        $this->ensureSuperadmin();

        $grade = Grade::where('uuid', $uuid)->firstOrFail();
        abort_if($grade->code === null, 403, 'Gred legacy tidak boleh dipadam.');

        if ($grade->positions()->withTrashed()->exists()) {
            session()->flash('error', 'Gred tidak boleh dipadam kerana masih mempunyai rekod Jawatan berkaitan.');

            return;
        }

        if ($grade->users()->withTrashed()->exists()) {
            session()->flash('error', 'Gred tidak boleh dipadam kerana masih digunakan oleh User.');

            return;
        }

        $grade->delete();
        session()->flash('message', 'Gred berjaya dipadamkan secara soft delete.');
    }

    protected function ensureSuperadmin(): void
    {
        abort_unless(auth()->user()?->hasRole('superadmin') && auth()->user()->canUseSystem(), 403);
    }

    public function render()
    {
        $grades = Grade::query()
            ->withCount('positions')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->orderByRaw('code IS NULL')
            ->orderByRaw('CAST(code AS UNSIGNED)')
            ->paginate(10);

        return view('livewire.admin.grade-management', [
            'grades' => $grades,
        ]);
    }
}
