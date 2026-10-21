<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\Meeting;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use Livewire\WithFileUploads;
use App\Livewire\Forms\ApplicationForm as ApplicationFormObject;
use App\Services\ApplicationSubmissionService;
use App\Services\SubmissionClosedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;

#[Layout('layouts.app')]
class ApplicationForm extends Component
{
    use WithFileUploads;

    // Suntikan Form Object
    public ApplicationFormObject $form;

    // Tracker Fasa
    public int $currentStep = 1;

    // ID draf sedia ada; dikunci supaya tidak boleh diubah dari pelayar
    #[Locked]
    public ?int $applicationId = null;

    public function mount(?string $uuid = null)
    {
        $user = Auth::user();

        // UM7A: permohonan baharu hanya oleh peranan yang dibenarkan (ApplicationPolicy::create).
        if (! $uuid) {
            $this->authorize('create', Application::class);
        }

        // Jika ada UUID (Edit Draf / Tidak Lengkap)
        if ($uuid) {
            $application = Application::with(['details', 'documents', 'primaryOfficer', 'secondaryOfficer'])
                ->where('uuid', $uuid)
                ->firstOrFail();

            $this->authorize('update', $application);

            $this->applicationId = $application->id;

            // Isikan semula data ke Form Object
            $this->form->title = $application->title ?? '';
            $this->form->application_category = $application->application_category ?? '';
            $this->form->objectives = $application->objectives ?? '';
            $this->form->project_scope = $application->project_scope ?? '';

            $this->form->procurement_type_id = $application->procurement_type_id ?? '';
            $this->form->procurement_method_id = $application->procurement_method_id ?? '';
            $this->form->ceiling_cost = $application->ceiling_cost;
            $this->form->estimated_cost = $application->estimated_cost;
            $this->form->expected_duration_months = $application->expected_duration_months;
            $this->form->outcome_code = $application->outcome_code ?? '';

            $this->form->primary_officer_id = $application->primaryOfficer?->user_id ?? $application->applicant_id;

            if ($application->secondaryOfficer) {
                $this->form->has_secondary_officer = true;
                $this->form->secondary_officer_id = $application->secondaryOfficer->user_id ?? '';
            }

            // Reconstruct Item Details (Fasa 3), dikumpul mengikut identiti master Kategori Projek
            if ($application->details->count() > 0) {
                $groupedDetails = [];
                foreach ($application->details->groupBy('project_category_id') as $categoryId => $items) {
                    $itemList = [];
                    foreach ($items as $item) {
                        $itemList[] = [
                            'technical_specifications' => $item->technical_specifications,
                            'unit_quantity' => $item->unit_quantity,
                            'unit_cost' => $item->unit_cost,
                        ];
                    }
                    $groupedDetails[] = [
                        'project_category_id' => $categoryId,
                        'items' => $itemList
                    ];
                }
                $this->form->details = $groupedDetails;
            }
        } else {
            // Permohonan Baharu
            // Pemohon menjadi Pegawai Utama secara lalai
            if ($user) {
                $this->form->primary_officer_id = $user->id;
            }

            // Nilai lalai: master aktif pertama mengikut sort_order
            $this->form->procurement_type_id = ProcurementType::active()->ordered()->value('id') ?? '';
            $this->form->procurement_method_id = ProcurementMethod::active()->ordered()->value('id') ?? '';

            if (empty($this->form->details)) {
                $this->addCategoryGroup();
            }
        }
    }

    // --- HELPER BERSAMPEL UNTUK FASA 3 (PERINCIAN PROJEK) ---
    public function addCategoryGroup()
    {
        $this->form->details[] = [
            // Kumpulan baharu sentiasa bermula dengan master aktif pertama (bukan nilai tidak aktif)
            'project_category_id' => ProjectCategory::active()->ordered()->value('id') ?? '',
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
        $this->validateStep($this->currentStep);
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->form->validateStep1($this->savedMasterValues['application_category']),
            2 => $this->form->validateStep2($this->savedMasterValues['procurement_type_id'], $this->savedMasterValues['procurement_method_id']),
            3 => $this->form->validateStep3($this->savedMasterValues['project_category_ids']),
            4 => $this->form->validateStep4($this->existingDocuments->has('PROPOSAL_PAPER')),
            5 => $this->form->validateStep5($this->officerCandidates->modelKeys()),
        };
    }

    // --- MASTER DATA (Fasa 1–3) ---

    /**
     * Nilai master yang telah disimpan pada rekod ini (dibaca daripada DB, bukan daripada borang),
     * supaya master yang telah dinyahaktifkan kekal sah untuk rekod ini sahaja.
     */
    #[Computed]
    public function savedMasterValues(): array
    {
        $application = $this->applicationId ? Application::find($this->applicationId) : null;

        return [
            'application_category' => $application?->application_category,
            'procurement_type_id' => $application?->procurement_type_id,
            'procurement_method_id' => $application?->procurement_method_id,
            'project_category_ids' => $application
                ? $application->details()->whereNotNull('project_category_id')->distinct()->pluck('project_category_id')->all()
                : [],
        ];
    }

    #[Computed]
    public function applicationCategoryOptions(): Collection
    {
        return $this->masterOptions(ApplicationCategory::class, 'code', [$this->savedMasterValues['application_category']]);
    }

    #[Computed]
    public function procurementTypeOptions(): Collection
    {
        return $this->masterOptions(ProcurementType::class, 'id', [$this->savedMasterValues['procurement_type_id']]);
    }

    #[Computed]
    public function procurementMethodOptions(): Collection
    {
        return $this->masterOptions(ProcurementMethod::class, 'id', [$this->savedMasterValues['procurement_method_id']]);
    }

    #[Computed]
    public function projectCategoryOptions(): Collection
    {
        return $this->masterOptions(ProjectCategory::class, 'id', $this->savedMasterValues['project_category_ids']);
    }

    /**
     * Pilihan dropdown: master aktif mengikut sort_order, ditambah nilai yang telah disimpan
     * pada rekod ini walaupun kini tidak aktif / dipadam (dipaparkan dengan tanda "Tidak Aktif").
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function masterOptions(string $model, string $key, array $keep): Collection
    {
        $keep = array_values(array_filter($keep, fn ($value) => filled($value)));

        return $model::withTrashed()
            ->where(fn ($query) => $query
                ->where(fn ($active) => $active->active()->whereNull('deleted_at'))
                ->when($keep, fn ($current) => $current->orWhereIn($key, $keep)))
            ->ordered()
            ->get();
    }

    // --- FASA 5: PEGAWAI BERTANGGUNGJAWAB ---

    /**
     * Pengguna berdaftar dalam agensi permohonan yang boleh dilantik sebagai pegawai bertanggungjawab.
     */
    #[Computed]
    public function officerCandidates(): Collection
    {
        $agencyId = $this->applicationId
            ? Application::whereKey($this->applicationId)->value('agency_id')
            : Auth::user()?->agency_id;

        return User::eligibleOfficersFor($agencyId, Auth::user());
    }

    // --- FASA 4: DOKUMEN SOKONGAN ---

    /**
     * Semak format & saiz sebaik sahaja fail dipilih; fail tidak sah dibuang serta-merta.
     */
    public function updated($property)
    {
        if (! in_array($property, ['form.proposal_paper', 'form.presentation_slide'], true)) {
            return;
        }

        $field = str($property)->after('form.')->toString();

        try {
            $this->form->validateDocument($field);
        } catch (ValidationException $e) {
            $this->form->reset($field);
            throw $e;
        }
    }

    /**
     * Dokumen aktif yang telah disimpan, mengikut jenis dokumen.
     */
    #[Computed]
    public function existingDocuments(): Collection
    {
        if (! $this->applicationId) {
            return collect();
        }

        return Application::findOrFail($this->applicationId)
            ->documents()
            ->latest()
            ->get()
            ->keyBy('document_type');
    }

    /**
     * Mesyuarat JTICT yang akan menerima permohonan ini; null = butang Hantar dinyahaktifkan.
     */
    #[Computed]
    public function targetMeeting(): ?Meeting
    {
        return $this->applicationId
            ? Application::findOrFail($this->applicationId)->targetJtictMeeting()
            : Meeting::currentJtict();
    }

    #[Computed]
    public function applicationUuid(): ?string
    {
        return $this->applicationId ? Application::whereKey($this->applicationId)->value('uuid') : null;
    }

    /**
     * Padam dokumen (soft delete; fail dikekalkan untuk audit).
     * Hanya dibenarkan ketika permohonan berstatus Draf / Tidak Lengkap.
     */
    public function deleteDocument(string $documentUuid)
    {
        $application = Application::findOrFail($this->applicationId);
        $this->authorize('update', $application);

        $application->documents()->where('uuid', $documentUuid)->firstOrFail()->delete();
        unset($this->existingDocuments);

        session()->flash('message', 'Dokumen berjaya dipadam.');
    }

    // --- SIMPAN PERMOHONAN METODE SERVICE ---
    public function submitApplication(ApplicationSubmissionService $service)
    {
        $this->authorizeNewApplication();

        try {
            // Semak semula semua fasa di server (termasuk had Kos Siling & dokumen); kembali ke fasa yang gagal
            foreach ([1, 2, 3, 4, 5] as $step) {
                try {
                    $this->validateStep($step);
                } catch (ValidationException $e) {
                    $this->currentStep = $step;
                    throw $e;
                }
            }

            $user = Auth::user();

            // Panggil Service Class untuk simpan rekod & fail (draf sedia ada dikemas kini)
            $application = $service->submit($this->form, $user, $this->applicationId);

            session()->flash('message', "Permohonan Projek ({$application->reference_number}) berjaya dihantar ke Urus Setia!");

            return redirect()->to('/dashboard');

        } catch (SubmissionClosedException $e) {
            // Tempoh JTICT ditutup ketika borang dibuka: permohonan telah disimpan sebagai draf
            $this->applicationId = $e->application->id;
            $this->form->reset(['proposal_paper', 'presentation_slide']);
            unset($this->existingDocuments, $this->targetMeeting);

            session()->flash('error', $e->getMessage() . " (No. Rujukan: {$e->application->reference_number})");
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Ralat Simpan Permohonan: ' . $e->getMessage());
            session()->flash('error', 'Gagal menyimpan permohonan. Punca: ' . $e->getMessage());
        }
    }

    public function saveDraft(ApplicationSubmissionService $service)
    {
        $this->authorizeNewApplication();

        try {
            $user = Auth::user();
            $application = $service->saveDraft($this->form, $user, $this->applicationId);
            $this->applicationId = $application->id;

            // Dokumen telah disimpan; kosongkan fail sementara dan muat semula senarai dokumen
            $this->form->reset(['proposal_paper', 'presentation_slide']);
            unset($this->existingDocuments);

            session()->flash('message', "Draf permohonan berjaya disimpan ({$application->reference_number})!");
        } catch (\Exception $e) {
            Log::error('Ralat Simpan Draf: ' . $e->getMessage());
            session()->flash('error', 'Gagal menyimpan draf: ' . $e->getMessage());
        }
    }

    /**
     * UM7A: semak semula kebenaran mencipta permohonan pada setiap tindakan simpan (peranan boleh
     * berubah semasa sesi). Draf sedia ada disemak melalui `update` dalam ApplicationSubmissionService.
     */
    protected function authorizeNewApplication(): void
    {
        if (! $this->applicationId) {
            $this->authorize('create', Application::class);
        }
    }

    public function render()
    {
        return view('livewire.application-form');
            
    }
}
