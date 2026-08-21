<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDetail;
use App\Models\ApplicationDocument;
use App\Livewire\Forms\ApplicationForm;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationSubmissionService
{
    /**
     * Memproses dan menyimpan permohonan projek ke pangkalan data.
     *
     * @param ApplicationForm $form
     * @param mixed $user
     * @return Application
     * @throws \Exception
     */
    public function submit(ApplicationForm $form, $user): Application
    {
        return DB::transaction(function () use ($form, $user) {
            // 1. Jana Nombor Rujukan Permohonan Unik
            $year = date('Y');
            $count = Application::whereYear('created_at', $year)->count() + 1;
            $refNo = sprintf('SPPICT/%s/%04d', $year, $count);

            // 2. Cipta Rekod Permohonan Utama
            $application = Application::create([
                'uuid' => (string) Str::uuid(),
                'reference_number' => $refNo,
                'agency_id' => $user->agency_id ?? 1,
                'applicant_id' => $user->id,
                'title' => $form->title,
                'project_category' => $form->project_category,
                'description' => $form->description,
                'objectives' => $form->objectives,
                'project_scope' => $form->project_scope,
                'procurement_type' => $form->procurement_type,
                'procurement_method' => $form->procurement_method,
                'ceiling_cost' => $form->ceiling_cost ?: 0,
                'estimated_cost' => $form->estimated_cost ?: 0,
                'expected_duration_months' => $form->expected_duration_months ?: 1,
                'outcome_code' => $form->outcome_code ?? '',
                'status' => 'SUBMITTED',
                'primary_officer_name' => $form->officer_name,
                'primary_officer_position' => $form->officer_position,
                'primary_officer_email' => $form->officer_email,
                'primary_officer_phone' => $form->officer_phone,
                'secondary_officer_name' => $form->has_secondary_officer ? $form->secondary_officer_name : null,
                'secondary_officer_position' => $form->has_secondary_officer ? $form->secondary_officer_position : null,
                'secondary_officer_email' => $form->has_secondary_officer ? $form->secondary_officer_email : null,
                'secondary_officer_phone' => $form->has_secondary_officer ? $form->secondary_officer_phone : null,
            ]);

            // 3. Simpan Perincian Projek (Fasa 3)
            foreach ($form->details as $group) {
                $categoryName = $group['item_category'];
                foreach ($group['items'] as $item) {
                    $qty = (int) $item['unit_quantity'];
                    $unitCost = (float) $item['unit_cost'];

                    ApplicationDetail::create([
                        'uuid' => (string) Str::uuid(),
                        'application_id' => $application->id,
                        'item_category' => $categoryName,
                        'technical_specifications' => strip_tags($item['technical_specifications']),
                        'unit_quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'total_cost' => $qty * $unitCost,
                    ]);
                }
            }

            // 4. Simpan Kertas Cadangan PDF (Fasa 4)
            if ($form->proposal_paper && is_object($form->proposal_paper)) {
                $path = $form->proposal_paper->store('documents/proposals', 'public');
                ApplicationDocument::create([
                    'uuid' => (string) Str::uuid(),
                    'application_id' => $application->id,
                    'document_type' => 'PROPOSAL_PAPER',
                    'file_name' => $form->proposal_paper->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => 'application/pdf',
                    'file_size_kb' => round($form->proposal_paper->getSize() / 1024),
                ]);
            }

            // 5. Simpan Slaid Pembentangan PDF (Fasa 4)
            if ($form->presentation_slide && is_object($form->presentation_slide)) {
                $pathSlide = $form->presentation_slide->store('documents/slides', 'public');
                ApplicationDocument::create([
                    'uuid' => (string) Str::uuid(),
                    'application_id' => $application->id,
                    'document_type' => 'PRESENTATION_SLIDE_JTICT',
                    'file_name' => $form->presentation_slide->getClientOriginalName(),
                    'file_path' => $pathSlide,
                    'mime_type' => 'application/pdf',
                    'file_size_kb' => round($form->presentation_slide->getSize() / 1024),
                ]);
            }

            Log::info("Permohonan baru berjaya dihantar: {$refNo} oleh ID Pemohon {$user->id}");

            return $application;
        });
    }
}