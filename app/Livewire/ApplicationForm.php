<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Application;
use Livewire\WithFileUploads;
use App\Livewire\Forms\ApplicationForm as ApplicationFormObject;
use App\Services\ApplicationSubmissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class ApplicationForm extends Component
{
    use WithFileUploads;

    // Suntikan Form Object
    public ApplicationFormObject $form;

    // Tracker Fasa
    public int $currentStep = 1;

    public function mount(?string $uuid = null)
    {
        $user = Auth::user();

        // Jika ada UUID (Edit Draf)
        if ($uuid) {
            $application = Application::with(['details', 'documents'])
                ->where('applicant_id', $user->id)
                ->where('uuid', $uuid)
                ->firstOrFail();
            
            $this->applicationId = $application->id;

            // Isikan semula data ke Form Object
            $this->form->title = $application->title ?? '';
            $this->form->project_category = $application->project_category ?? 'System Development';
            $this->form->objectives = $application->objectives ?? '';
            $this->form->project_scope = $application->project_scope ?? '';
            
            $this->form->procurement_type = $application->procurement_type ?? '';
            $this->form->procurement_method = $application->procurement_method ?? '';
            $this->form->ceiling_cost = $application->ceiling_cost;
            $this->form->estimated_cost = $application->estimated_cost;
            $this->form->expected_duration_months = $application->expected_duration_months;
            $this->form->outcome_code = $application->outcome_code ?? '';

            $this->form->officer_name = $application->primary_officer_name ?? '';
            $this->form->officer_position = $application->primary_officer_position ?? '';
            $this->form->officer_email = $application->primary_officer_email ?? '';
            $this->form->officer_phone = $application->primary_officer_phone ?? '';

            if ($application->secondary_officer_name) {
                $this->form->has_secondary_officer = true;
                $this->form->secondary_officer_name = $application->secondary_officer_name;
                $this->form->secondary_officer_position = $application->secondary_officer_position;
                $this->form->secondary_officer_email = $application->secondary_officer_email;
                $this->form->secondary_officer_phone = $application->secondary_officer_phone;
            }

            // Reconstruct Item Details (Fasa 3)
            if ($application->details->count() > 0) {
                $groupedDetails = [];
                foreach ($application->details->groupBy('item_category') as $category => $items) {
                    $itemList = [];
                    foreach ($items as $item) {
                        $itemList[] = [
                            'technical_specifications' => $item->technical_specifications,
                            'unit_quantity' => $item->unit_quantity,
                            'unit_cost' => $item->unit_cost,
                        ];
                    }
                    $groupedDetails[] = [
                        'item_category' => $category,
                        'items' => $itemList
                    ];
                }
                $this->form->details = $groupedDetails;
            }
        } else {
            // Permohonan Baharu
            if ($user) {
                $this->form->officer_name = $user->name ?? '';
                $this->form->officer_position = $user->position ?? '';
                $this->form->officer_email = $user->email ?? '';
                $this->form->officer_phone = $user->phone_number ?? '';
            }

            if (empty($this->form->details)) {
                $this->addCategoryGroup();
            }
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

    public ?int $applicationId = null; // Menyimpan ID jika draf sedia ada diisikan semula

    public function saveDraft(ApplicationSubmissionService $service)
    {
        try {
            $user = Auth::user();
            $application = $service->saveDraft($this->form, $user, $this->applicationId);
            $this->applicationId = $application->id;

            session()->flash('message', "Draf permohonan berjaya disimpan ({$application->reference_number})!");
        } catch (\Exception $e) {
            Log::error('Ralat Simpan Draf: ' . $e->getMessage());
            session()->flash('error', 'Gagal menyimpan draf: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.application-form');
            
    }
}
