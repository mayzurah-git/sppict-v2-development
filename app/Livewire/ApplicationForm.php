<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Forms\ApplicationForm as ApplicationFormObject;
use App\Services\ApplicationSubmissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ApplicationForm extends Component
{
    use WithFileUploads;

    // Suntikan Form Object
    public ApplicationFormObject $form;

    // Tracker Fasa
    public int $currentStep = 1;

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            $this->form->officer_name = $user->name ?? '';
            $this->form->officer_position = $user->position ?? '';
            $this->form->officer_email = $user->email ?? '';
            $this->form->officer_phone = $user->phone_number ?? '';
        }

        // Sediakan Kategori & Item Asas Fasa 3 jika belum ada
        if (empty($this->form->details)) {
            $this->addCategoryGroup();
        }
    }

    // --- HELPER BERSAMPEL UNTUK FASA 3 (PERINCIAN PROJEK) ---
    public function addCategoryGroup()
    {
        $this->form->details[] = [
            'item_category' => 'Projek Baharu',
            'items' => [
                [
                    'technical_specifications' => '',
                    'unit_quantity' => 1,
                    'unit_cost' => 0.00,
                ]
            ]
        ];
    }

    public function removeCategoryGroup(int $categoryIndex)
    {
        if (count($this->form->details) > 1) {
            unset($this->form->details[$categoryIndex]);
            $this->form->details = array_values($this->form->details);
        }
    }

    public function addSubItem(int $categoryIndex)
    {
        $this->form->details[$categoryIndex]['items'][] = [
            'technical_specifications' => '',
            'unit_quantity' => 1,
            'unit_cost' => 0.00,
        ];
    }

    public function removeSubItem(int $categoryIndex, int $itemIndex)
    {
        if (count($this->form->details[$categoryIndex]['items']) > 1) {
            unset($this->form->details[$categoryIndex]['items'][$itemIndex]);
            $this->form->details[$categoryIndex]['items'] = array_values($this->form->details[$categoryIndex]['items']);
        }
    }

    // --- LOGIK NAVIGASI BANYAK FASA ---
    public function nextStep()
    {
        $this->validateCurrentStep();
        if ($this->currentStep < 5) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function validateCurrentStep()
    {
        if ($this->currentStep === 1) {
            $this->form->validateStep1();
        } elseif ($this->currentStep === 2) {
            $this->form->validateStep2();
        } elseif ($this->currentStep === 3) {
            $this->form->validateStep3();
        } elseif ($this->currentStep === 4) {
            $this->form->validateStep4();
        }
    }

    // --- SIMPAN PERMOHONAN METODE SERVICE ---
    public function submitApplication(ApplicationSubmissionService $service)
    {
        try {
            // Validate Fasa 5 (Pegawai Utama & Pengganti)
            $this->form->validateStep5();

            $user = Auth::user();

            // Panggil Service Class untuk simpan rekod & fail
            $application = $service->submit($this->form, $user);

            session()->flash('message', "Permohonan Projek ({$application->reference_number}) berjaya dihantar ke Urus Setia!");

            return redirect()->to('/dashboard');

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Ralat Simpan Permohonan: ' . $e->getMessage());
            session()->flash('error', 'Gagal menyimpan permohonan. Punca: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.application-form');
    }
}