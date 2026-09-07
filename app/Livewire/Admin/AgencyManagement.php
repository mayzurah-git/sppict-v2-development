<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Agency;

class AgencyManagement extends Component
{
    use WithPagination;

    public const CATEGORY_OPTIONS = [
        'SUK' => 'Bahagian / Unit PSUKNS',
        'PBT' => 'Pihak Berkuasa Tempatan (PBT)',
        'DISTRICT_OFFICE' => 'Pejabat Daerah dan Tanah (PDT)',
        'STATE_DEPT' => 'Jabatan Negeri',
    ];

    public string $search = '';
    public bool $showModal = false;
    public ?int $agencyId = null;

    // Form Properties
    public string $name = '';
    public string $code = '';
    public string $category = 'SUK';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:agencies,code,' . $this->agencyId,
            'category' => 'required|string|in:' . implode(',', array_keys(self::CATEGORY_OPTIONS)),
        ];
    }

    public function openModal(?int $id = null)
    {
        $this->resetErrorBag();
        $this->agencyId = $id;

        if ($id) {
            $agency = Agency::findOrFail($id);
            $this->name = $agency->name;
            $this->code = $agency->code;
            // Rekod lama menggunakan nilai "Agensi Negeri". Ia bukan lagi kod
            // kategori yang sah, jadi pengguna perlu memilih kategori baharu.
            $this->category = array_key_exists($agency->category, self::CATEGORY_OPTIONS)
                ? $agency->category
                : '';
        } else {
            $this->reset(['name', 'code']);
            $this->category = 'SUK';
        }

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['name', 'code', 'category', 'agencyId']);
    }

    public function saveAgency()
    {
        $this->validate();

        Agency::updateOrCreate(
            ['id' => $this->agencyId],
            [
                'name' => $this->name,
                'code' => strtoupper($this->code),
                'category' => $this->category,
            ]
        );

        session()->flash('message', $this->agencyId ? 'Agensi berjaya dikemaskini!' : 'Agensi baharu berjaya didaftarkan!');
        $this->closeModal();
    }

    public function deleteAgency(string $uuid): void
    {
        $agency = Agency::where('uuid', $uuid)->firstOrFail();
        $agency->delete();

        session()->flash('message', 'Agensi berjaya dihapuskan.');
    }

    public function render()
    {
        $agencies = Agency::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('code', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        return view('livewire.admin.agency-management', [
            'agencies' => $agencies
        ])->layout('layouts.app');
    }
}
