<?php

namespace App\Livewire\Admin;

use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PositionManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?string $positionUuid = null;
    public string $title = '';
    public ?int $service_scheme_id = null;
    /** @var array<int, int|string> Gred yang sah bagi jawatan (pivot grade_position). */
    public array $grade_ids = [];
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
        $position = $uuid ? Position::where('uuid', $uuid)->firstOrFail() : null;

        if ($position && $this->isLegacyPosition($position)) {
            session()->flash('error', 'Jawatan legacy tidak boleh dikemaskini melalui modul master Jawatan.');

            return;
        }

        $this->positionUuid = $uuid;

        if ($position) {
            $this->title = $position->title;
            $this->service_scheme_id = $position->service_scheme_id;
            $this->grade_ids = $position->grades()->pluck('grades.id')->map(fn ($id) => (string) $id)->all();
            $this->description = $position->description ?? '';
            $this->is_active = $position->is_active;
        } else {
            $this->reset(['title', 'service_scheme_id', 'grade_ids', 'description', 'positionUuid']);
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['title', 'service_scheme_id', 'grade_ids', 'description', 'is_active', 'positionUuid']);
    }

    public function save(): void
    {
        $this->ensureSuperadmin();

        $this->title = trim($this->title);

        $this->validate([
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'title')->ignore($this->positionUuid, 'uuid'),
            ],
            'service_scheme_id' => [
                'required',
                'integer',
                Rule::exists('service_schemes', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'grade_ids' => ['required', 'array', 'min:1'],
            'grade_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('grades', 'id')->where(fn ($query) => $query
                    ->whereNotNull('code')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $position = $this->positionUuid
            ? Position::where('uuid', $this->positionUuid)->firstOrFail()
            : new Position();

        abort_if($position->exists && $this->isLegacyPosition($position), 403, 'Jawatan legacy tidak boleh diubah.');

        $position->fill([
            'title' => $this->title,
            'service_scheme_id' => $this->service_scheme_id,
            'description' => $this->description !== '' ? $this->description : null,
            'is_active' => $this->is_active,
        ]);

        // `positions.grade_id` (legacy) tidak lagi ditulis; Gred yang sah disimpan dalam pivot.
        DB::transaction(function () use ($position): void {
            $position->save();
            $position->grades()->sync(array_map('intval', $this->grade_ids));
        });

        session()->flash('message', $this->positionUuid
            ? 'Jawatan berjaya dikemaskini!'
            : 'Jawatan baharu berjaya didaftarkan!');

        $this->closeModal();
    }

    public function setStatus(string $uuid, bool $active): void
    {
        $this->ensureSuperadmin();

        $position = Position::where('uuid', $uuid)->firstOrFail();
        if ($this->isLegacyPosition($position)) {
            session()->flash('error', 'Jawatan legacy tidak boleh diaktifkan atau dinyahaktifkan.');

            return;
        }

        if ($active && ! $this->hasActiveParents($position)) {
            session()->flash('error', 'Jawatan tidak boleh diaktifkan kerana Skim Perkhidmatan atau Gred tidak aktif atau telah dipadam.');

            return;
        }

        $position->update(['is_active' => $active]);

        session()->flash('message', $active
            ? 'Jawatan berjaya diaktifkan.'
            : 'Jawatan berjaya dinyahaktifkan.');
    }

    public function delete(string $uuid): void
    {
        $this->ensureSuperadmin();

        $position = Position::where('uuid', $uuid)->firstOrFail();
        if ($this->isLegacyPosition($position)) {
            session()->flash('error', 'Jawatan legacy tidak boleh dipadam.');

            return;
        }

        if ($position->users()->withTrashed()->exists()) {
            session()->flash('error', 'Jawatan tidak boleh dipadam kerana masih digunakan oleh User.');

            return;
        }

        $position->delete();
        session()->flash('message', 'Jawatan berjaya dipadamkan secara soft delete.');
    }

    protected function hasActiveParents(Position $position): bool
    {
        return ServiceScheme::query()
            ->whereKey($position->service_scheme_id)
            ->where('is_active', true)
            ->exists()
            && $position->grades()
                ->whereNotNull('grades.code')
                ->where('grades.is_active', true)
                ->exists();
    }

    protected function isLegacyPosition(Position $position): bool
    {
        return $position->getKey() === 1;
    }

    protected function ensureSuperadmin(): void
    {
        abort_unless(auth()->user()?->hasRole('superadmin') && auth()->user()->canUseSystem(), 403);
    }

    public function render()
    {
        $positions = Position::query()
            ->with(['serviceScheme', 'grades' => fn ($query) => $query->orderByRaw('CAST(grades.code AS UNSIGNED)')])
            ->withCount('users')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('title', 'like', '%' . $this->search . '%')
                        ->orWhereHas('serviceScheme', function ($schemeQuery): void {
                            $schemeQuery->where('code', 'like', '%' . $this->search . '%')
                                ->orWhere('name', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('grades', function ($gradeQuery): void {
                            $gradeQuery->where('code', 'like', '%' . $this->search . '%')
                                ->orWhere('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->orderBy('id')
            ->paginate(10);

        $serviceSchemes = ServiceScheme::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $grades = Grade::query()
            ->whereNotNull('code')
            ->where('is_active', true)
            ->orderByRaw('CAST(code AS UNSIGNED)')
            ->get();

        if ($this->positionUuid) {
            $current = Position::where('uuid', $this->positionUuid)->first();

            if ($current?->service_scheme_id && ! $serviceSchemes->contains('id', $current->service_scheme_id)) {
                $currentScheme = ServiceScheme::withTrashed()->find($current->service_scheme_id);
                if ($currentScheme) {
                    $serviceSchemes->push($currentScheme);
                }
            }

            // Gred tidak aktif yang masih dipautkan tetap dipaparkan supaya Superadmin boleh menyahpilihnya.
            $current?->grades()->withTrashed()->get()
                ->reject(fn (Grade $grade) => $grades->contains('id', $grade->id))
                ->each(fn (Grade $grade) => $grades->push($grade));
        }

        return view('livewire.admin.position-management', [
            'positions' => $positions,
            'serviceSchemes' => $serviceSchemes,
            'grades' => $grades,
        ]);
    }
}
