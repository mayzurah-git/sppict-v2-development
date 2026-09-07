<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Application;
use Illuminate\Support\Facades\Auth;

class ViewApplication extends Component
{
    public Application $application;

    public function mount(string $uuid): void
    {
        $user = Auth::user();

        // Pastikan pengguna hanya boleh melihat permohonan mereka sendiri (kecuali admin/urus setia)
        $this->application = Application::with(['details', 'documents', 'applicant', 'agency'])
            ->where('applicant_id', Auth::id())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.view-application')
            ->layout('layouts.app');
    }
}
