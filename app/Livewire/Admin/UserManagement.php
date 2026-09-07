<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Agency;
use Illuminate\Support\Facades\Hash;

class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public bool $showModal = false;
    public ?int $userId = null;

    // Form Properties
    public string $name = '';
    public string $email = '';
    public string $position = '';
    public string $phone_number = '';
    public string $role = 'pengguna_biasa';
    public $agency_id = '';
    public string $password = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $this->userId,
            'position' => 'required|string|max:255',
            'phone_number' => 'required|string|max:50',
            'role' => 'required|string|exists:roles,name',
            'agency_id' => 'required|exists:agencies,id',
            'password' => $this->userId ? 'nullable|min:8' : 'required|min:8',
        ];
    }

    public function openModal(?int $id = null)
    {
        $this->resetErrorBag();
        $this->userId = $id;

        if ($id) {
            $user = User::findOrFail($id);
            $this->name = $user->name;
            $this->email = $user->email;
            $this->position = $user->position ?? '';
            $this->phone_number = $user->phone_number ?? '';
            $this->role = $user->getRoleNames()->first() ?? 'pengguna_biasa';
            $this->agency_id = $user->agency_id;
            $this->password = '';
        } else {
            $this->reset(['name', 'email', 'position', 'phone_number', 'role', 'agency_id', 'password', 'userId']);
        }

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['name', 'email', 'position', 'phone_number', 'role', 'agency_id', 'password', 'userId']);
    }

    public function saveUser()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'position' => $this->position,
            'phone_number' => $this->phone_number,
            'role' => $this->role,
            'agency_id' => $this->agency_id,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $user = User::updateOrCreate(['id' => $this->userId], $data);
        $user->syncRoles($this->role);

        session()->flash('message', $this->userId ? 'Pengguna berjaya dikemaskini!' : 'Pengguna baharu berjaya didaftarkan!');
        $this->closeModal();
    }

    public function resetPassword(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make('Password123!')]);

        session()->flash('message', "Kata laluan bagi {$user->name} telah diset semula kepada 'Password123!'.");
    }

    public function render()
    {
        $users = User::with('agency')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->when($this->roleFilter, function ($query) {
                $query->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', $this->roleFilter);
                });
            })
            ->latest()
            ->paginate(10);

        return view('livewire.admin.user-management', [
            'users' => $users,
            'agencies' => Agency::orderBy('name')->get()
        ])->layout('layouts.app');
    }
}
