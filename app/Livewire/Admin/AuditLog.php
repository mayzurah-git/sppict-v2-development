<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Models\Audit;

class AuditLog extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $audits = Audit::query()
            ->with('user')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('event', 'like', "%{$this->search}%")
                        ->orWhere('auditable_type', 'like', "%{$this->search}%")
                        ->orWhere('url', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return view('livewire.admin.audit-log', compact('audits'))
            ->layout('layouts.app');
    }
}
