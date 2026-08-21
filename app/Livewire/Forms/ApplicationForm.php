<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use Livewire\Attributes\Validate;

class ApplicationForm extends Form
{
    // --- FASA 1: MAKLUMAT AM PROJEK ---
    public string $title = '';
    public string $project_category = 'System Development';
    public string $description = '';
    public string $objectives = '';
    public string $project_scope = '';

    // --- FASA 2: MAKLUMAT KEWANGAN & PEROLEHAN ---
    public string $procurement_type = 'Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)';
    public string $procurement_method = 'Sebut Harga';
    public $ceiling_cost = '';
    public $estimated_cost = '';
    public $expected_duration_months = '';
    public string $outcome_code = '';

    // --- FASA 3: PERINCIAN PROJEK ---
    public array $details = [];

    // --- FASA 4: DOKUMEN SOKONGAN (PDF) ---
    public $proposal_paper = null;
    public $presentation_slide = null;

    // --- FASA 5: MAKLUMAT PEGAWAI BERTANGGUNGJAWAB ---
    public string $officer_name = '';
    public string $officer_position = '';
    public string $officer_email = '';
    public string $officer_phone = '';

    public bool $has_secondary_officer = false;
    public string $secondary_officer_name = '';
    public string $secondary_officer_position = '';
    public string $secondary_officer_email = '';
    public string $secondary_officer_phone = '';

    // --- PENGESAHAN (VALIDATION) MENGIKUT FASA ---

    public function validateStep1()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'project_category' => 'required|string',
            'objectives' => 'required|string|min:10',
            'project_scope' => 'required|string|min:10',
        ], [
            'title.required' => 'Tajuk permohonan projek wajib diisi.',
            'objectives.required' => 'Objektif projek wajib diisi.',
            'objectives.min' => 'Objektif projek hendaklah sekurang-kurangnya 10 aksara.',
            'project_scope.required' => 'Skop projek wajib diisi.',
            'project_scope.min' => 'Skop projek hendaklah sekurang-kurangnya 10 aksara.',
        ]);
    }

    public function validateStep2()
    {
        $this->validate([
            'procurement_type' => 'required|string',
            'procurement_method' => 'required|string',
            'ceiling_cost' => 'required|numeric|min:1000',
            'estimated_cost' => 'required|numeric|min:1000',
            'expected_duration_months' => 'required|integer|min:1',
            'outcome_code' => 'required|string',
        ], [
            'ceiling_cost.required' => 'Anggaran kos siling wajib diisi.',
            'estimated_cost.required' => 'Anggaran kos projek wajib diisi.',
            'expected_duration_months.required' => 'Jangkaan tempoh pelaksanaan wajib diisi.',
            'outcome_code.required' => 'Kod hasil wajib diisi.',
        ]);
    }

    public function validateStep3()
    {
        $this->validate([
            'details' => 'required|array|min:1',
            'details.*.item_category' => 'required|string',
            'details.*.items' => 'required|array|min:1',
            'details.*.items.*.technical_specifications' => 'required|string|min:3',
            'details.*.items.*.unit_quantity' => 'required|integer|min:1',
            'details.*.items.*.unit_cost' => 'required|numeric|min:0',
        ], [
            'details.*.items.*.technical_specifications.required' => 'Spesifikasi teknikal wajib diisi.',
            'details.*.items.*.unit_quantity.min' => 'Kuantiti sekurang-kurangnya 1.',
            'details.*.items.*.unit_cost.min' => 'Kos seunit tidak boleh kurang daripada 0.00.',
        ]);

        $estimatedCost = (float) $this->estimated_cost;
        $totalDetails = $this->calculateTotalDetailsCost();

        if (abs($totalDetails - $estimatedCost) > 0.01) {
            $message = 'Jumlah Perincian (RM ' . number_format($totalDetails, 2) . 
                       ') mesti SAMA TEPAT dengan Anggaran Kos Projek (RM ' . number_format($estimatedCost, 2) . ').';
            
            // Tambah ralat khusus untuk paparan UI
            throw \Illuminate\Validation\ValidationException::withMessages([
                'details_total' => $message
            ]);
        }
    }

    public function validateStep4()
    {
        $this->validate([
            'proposal_paper' => $this->proposal_paper && is_object($this->proposal_paper) 
                ? 'file|mimes:pdf|max:15360' 
                : ($this->proposal_paper ? 'nullable' : 'required|file|mimes:pdf|max:15360'),
            'presentation_slide' => 'nullable|file|mimes:pdf|max:15360',
        ], [
            'proposal_paper.required' => 'Sila muat naik Kertas Cadangan dalam format PDF.',
            'proposal_paper.mimes' => 'Kertas Cadangan hendaklah dalam format PDF sahaja.',
            'presentation_slide.mimes' => 'Slaid Pembentangan hendaklah dalam format PDF sahaja.',
        ]);
    }

    public function validateStep5()
    {
        $rules = [
            'officer_name' => 'required|string|max:255',
            'officer_position' => 'required|string|max:255',
            'officer_email' => 'required|email|max:255',
            'officer_phone' => 'required|string|max:50',
        ];

        if ($this->has_secondary_officer) {
            $rules['secondary_officer_name'] = 'required|string|max:255';
            $rules['secondary_officer_position'] = 'required|string|max:255';
            $rules['secondary_officer_email'] = 'required|email|max:255';
            $rules['secondary_officer_phone'] = 'required|string|max:50';
        }

        $this->validate($rules, [
            'officer_name.required' => 'Nama pegawai utama wajib diisi.',
            'officer_position.required' => 'Jawatan pegawai utama wajib diisi.',
            'officer_email.required' => 'E-mel rasmi wajib diisi.',
            'officer_phone.required' => 'No. telefon wajib diisi.',
            'secondary_officer_name.required' => 'Nama pegawai pengganti wajib diisi.',
            'secondary_officer_position.required' => 'Jawatan pegawai pengganti wajib diisi.',
            'secondary_officer_email.required' => 'E-mel pegawai pengganti wajib diisi.',
            'secondary_officer_phone.required' => 'No. telefon pegawai pengganti wajib diisi.',
        ]);
    }

    // --- PENGIRAAN KOS DINAMIK (GETTER) ---
    public function getTotalDetailsCostAttribute(): float
    {
        return $this->calculateTotalDetailsCost();
    }

    public function calculateTotalDetailsCost(): float
    {
        $total = 0;
        foreach ($this->details as $group) {
            foreach (($group['items'] ?? []) as $item) {
                $qty = (int) ($item['unit_quantity'] ?? 0);
                $cost = (float) ($item['unit_cost'] ?? 0);
                $total += ($qty * $cost);
            }
        }
        return (float) $total;
    }
}
