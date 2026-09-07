<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Grade;
use App\Models\Position;

#[Layout('layouts.app')]
class PositionGradeManagement extends Component
{
    use WithPagination;

    public string $activeTab = 'grades'; // 'grades' atau 'positions'
    public string $search = '';

    // Form Grade
    public string $gradeName = '';
    public string $gradeScheme = '';
    public ?int $gradeId = null;

    // Form Position
    public string $positionTitle = '';
    public ?int $positionId = null;

    public bool $showModal = false;

    public function openModal(?int $id = null)
    {
        $this->resetErrorBag();

        if ($this->activeTab === 'grades') {
            $this->gradeId = $id;
            if ($id) {
                $g = Grade::findOrFail($id);
                $this->gradeName = $g->name;
                $this->gradeScheme = $g->scheme ?? '';
            } else {
                $this->reset(['gradeName', 'gradeScheme', 'gradeId']);
            }
        } else {
            $this->positionId = $id;
            if ($id) {
                $p = Position::findOrFail($id);
                $this->positionTitle = $p->title;
            } else {
                $this->reset(['positionTitle', 'positionId']);
            }
        }

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['gradeName', 'gradeScheme', 'gradeId', 'positionTitle', 'positionId']);
    }

    public function save()
    {
        if ($this->activeTab === 'grades') {
            $this->validate([
                'gradeName' => 'required|string|max:50|unique:grades,name,' . $this->gradeId,
                'gradeScheme' => 'nullable|string|max:100',
            ]);

            Grade::updateOrCreate(['id' => $this->gradeId], [
                'name' => strtoupper($this->gradeName),
                'scheme' => $this->gradeScheme,
            ]);
        } else {
            $this->validate([
                'positionTitle' => 'required|string|max:255|unique:positions,title,' . $this->positionId,
            ]);

            Position::updateOrCreate(['id' => $this->positionId], [
                'title' => $this->positionTitle,
            ]);
        }

        session()->flash('message', 'Rekod berjaya disimpan!');
        $this->closeModal();
    }

    public function render()
    {
        $grades = Grade::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('scheme', 'like', '%' . $this->search . '%')
            ->paginate(10, ['*'], 'gradesPage');

        $positions = Position::where('title', 'like', '%' . $this->search . '%')
            ->paginate(10, ['*'], 'positionsPage');

        return view('livewire.admin.position-grade-management', [
            'grades' => $grades,
            'positions' => $positions,
        ]);
    }
}
