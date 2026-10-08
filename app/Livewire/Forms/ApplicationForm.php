<?php

namespace App\Livewire\Forms;

use App\Rules\DocumentSignature;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Form;
use Livewire\Attributes\Validate;

class ApplicationForm extends Form
{
    // --- FASA 1: MAKLUMAT AM PROJEK ---
    public string $title = '';
    public string $application_category = ''; // code master application_categories
    //public string $description = '';
    public string $objectives = '';
    public string $project_scope = '';

    // --- FASA 2: MAKLUMAT KEWANGAN & PEROLEHAN ---
    // ID master; nilai lalai rekod baharu ditetapkan dalam ApplicationForm::mount() (master aktif pertama)
    public $procurement_type_id = '';
    public $procurement_method_id = '';
    public $ceiling_cost = '';
    public $estimated_cost = '';
    public $expected_duration_months = '';
    public string $outcome_code = '';

    // --- FASA 3: PERINCIAN PROJEK ---
    // [['project_category_id' => int, 'items' => [...]], ...]
    public array $details = [];

    // --- FASA 4: DOKUMEN SOKONGAN (PDF) ---
    public $proposal_paper = null;
    public $presentation_slide = null;

    // --- FASA 5: MAKLUMAT PEGAWAI BERTANGGUNGJAWAB ---
    // Pegawai mesti mempunyai akaun SPPICT dalam agensi yang sama (untuk mengisi kemajuan projek).
    public $primary_officer_id = '';

    public bool $has_secondary_officer = false;
    public $secondary_officer_id = '';

    // --- PENGESAHAN (VALIDATION) MENGIKUT FASA ---

    /**
     * Master data mesti aktif, kecuali nilai yang telah disimpan pada rekod ini
     * (supaya rekod lama dengan master yang telah dinyahaktifkan masih boleh dihantar semula).
     */
    private function masterRule(string $table, string $column, array $keep = []): Exists
    {
        $keep = array_values(array_filter($keep, fn ($value) => filled($value)));

        return Rule::exists($table, $column)->where(fn ($query) => $query
            ->where(fn ($active) => $active->where('is_active', true)->whereNull('deleted_at'))
            ->when($keep, fn ($current) => $current->orWhereIn($column, $keep)));
    }

    public function validateStep1(?string $currentApplicationCategory = null)
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'application_category' => ['required', 'string', $this->masterRule('application_categories', 'code', [$currentApplicationCategory])],
            'objectives' => 'required|string|min:10',
            'project_scope' => 'required|string|min:10',
        ], [
            'title.required' => 'Nama projek wajib diisi.',
            'application_category.required' => 'Kategori permohonan wajib dipilih.',
            'application_category.exists' => 'Kategori permohonan tidak sah atau tidak aktif.',
            'objectives.required' => 'Objektif projek wajib diisi.',
            'objectives.min' => 'Objektif projek hendaklah sekurang-kurangnya 10 aksara.',
            'project_scope.required' => 'Skop projek wajib diisi.',
            'project_scope.min' => 'Skop projek hendaklah sekurang-kurangnya 10 aksara.',
        ]);
    }

    public function validateStep2(?int $currentProcurementTypeId = null, ?int $currentProcurementMethodId = null)
    {
        $this->validate([
            'procurement_type_id' => ['required', 'integer', $this->masterRule('procurement_types', 'id', [$currentProcurementTypeId])],
            'procurement_method_id' => ['required', 'integer', $this->masterRule('procurement_methods', 'id', [$currentProcurementMethodId])],
            'ceiling_cost' => 'required|numeric|min:1000',
            'estimated_cost' => 'required|numeric|min:1000|lte:ceiling_cost',
            'expected_duration_months' => 'required|integer|min:1',
            'outcome_code' => 'required|string',
        ], [
            'procurement_type_id.required' => 'Jenis perolehan wajib dipilih.',
            'procurement_type_id.integer' => 'Jenis perolehan tidak sah.',
            'procurement_type_id.exists' => 'Jenis perolehan tidak sah atau tidak aktif.',
            'procurement_method_id.required' => 'Kaedah perolehan wajib dipilih.',
            'procurement_method_id.integer' => 'Kaedah perolehan tidak sah.',
            'procurement_method_id.exists' => 'Kaedah perolehan tidak sah atau tidak aktif.',
            'ceiling_cost.required' => 'Anggaran kos siling wajib diisi.',
            'estimated_cost.required' => 'Anggaran kos projek wajib diisi.',
            'estimated_cost.lte' => 'Anggaran kos projek tidak boleh melebihi Anggaran Kos Siling.',
            'expected_duration_months.required' => 'Jangkaan tempoh pelaksanaan wajib diisi.',
            'outcome_code.required' => 'Kod hasil wajib diisi.',
        ]);
    }

    public function validateStep3(array $currentProjectCategoryIds = [])
    {
        $this->validate([
            'details' => 'required|array|min:1',
            'details.*.project_category_id' => ['required', 'integer', $this->masterRule('project_categories', 'id', $currentProjectCategoryIds)],
            'details.*.items' => 'required|array|min:1',
            'details.*.items.*.technical_specifications' => 'required|string|min:3',
            'details.*.items.*.unit_quantity' => 'required|integer|min:1',
            'details.*.items.*.unit_cost' => 'required|numeric|min:0',
        ], [
            'details.*.project_category_id.required' => 'Kategori projek wajib dipilih.',
            'details.*.project_category_id.integer' => 'Kategori projek tidak sah.',
            'details.*.project_category_id.exists' => 'Kategori projek tidak sah atau tidak aktif.',
            'details.*.items.*.technical_specifications.required' => 'Spesifikasi teknikal wajib diisi.',
            'details.*.items.*.unit_quantity.min' => 'Kuantiti sekurang-kurangnya 1.',
            'details.*.items.*.unit_cost.min' => 'Kos seunit tidak boleh kurang daripada 0.00.',
        ]);

        // Jumlah Keseluruhan (termasuk SST) tidak boleh melebihi Anggaran Kos Siling
        $summary = $this->costSummary();

        if ($summary['ceiling_balance'] < 0) {
            $message = 'Jumlah Keseluruhan termasuk SST (RM ' . number_format($summary['total'], 2) .
                       ') melebihi Anggaran Kos Siling (RM ' . number_format($summary['ceiling'], 2) .
                       ') sebanyak RM ' . number_format(abs($summary['ceiling_balance']), 2) . '.';

            // Tambah ralat khusus untuk paparan UI
            throw \Illuminate\Validation\ValidationException::withMessages([
                'details_total' => $message
            ]);
        }
    }

    // --- FASA 4: PERATURAN DOKUMEN ---
    // 'extensions' menyemak sambungan nama fail; 'mimes' menyemak kandungan sebenar fail.
    public const MAX_DOCUMENT_KB = 25600; // 25MB

    public const DOCUMENT_FORMATS = [
        'proposal_paper' => ['pdf', 'docx'],
        'presentation_slide' => ['pdf'],
    ];

    public function validateStep4(bool $hasExistingProposal = false)
    {
        $this->validate($this->documentRules($hasExistingProposal), $this->documentMessages());
    }

    /**
     * Semak satu dokumen sebaik sahaja dipilih (sebelum ke fasa seterusnya).
     */
    public function validateDocument(string $field)
    {
        $this->validate([$field => $this->documentRules(true)[$field]], $this->documentMessages());
    }

    private function documentRules(bool $hasExistingProposal): array
    {
        $messages = $this->documentMessages();
        $rules = [];

        foreach (self::DOCUMENT_FORMATS as $field => $formats) {
            $formatList = implode(',', $formats);
            $isRequired = $field === 'proposal_paper' && ! $hasExistingProposal;

            $rules[$field] = [
                $isRequired ? 'required' : 'nullable',
                'file',
                'extensions:' . $formatList,
                'mimes:' . $formatList,
                'bail',
                new DocumentSignature($formats, $messages["{$field}.mimes"]),
                'max:' . self::MAX_DOCUMENT_KB,
            ];
        }

        return $rules;
    }

    private function documentMessages(): array
    {
        return [
            'proposal_paper.required' => 'Sila muat naik Kertas Cadangan dalam format PDF atau Word (.docx).',
            'proposal_paper.extensions' => 'Kertas Cadangan hendaklah dalam format PDF atau Word (.docx) sahaja.',
            'proposal_paper.mimes' => 'Kertas Cadangan hendaklah dalam format PDF atau Word (.docx) sahaja.',
            'proposal_paper.max' => 'Saiz Kertas Cadangan tidak boleh melebihi 25MB.',
            'presentation_slide.extensions' => 'Slaid Pembentangan hendaklah dalam format PDF sahaja.',
            'presentation_slide.mimes' => 'Slaid Pembentangan hendaklah dalam format PDF sahaja.',
            'presentation_slide.max' => 'Saiz Slaid Pembentangan tidak boleh melebihi 25MB.',
            '*.file' => 'Fail gagal dimuat naik. Sila cuba semula.',
        ];
    }

    /**
     * @param  array<int>  $eligibleOfficerIds  ID pengguna dalam agensi permohonan yang boleh dilantik
     */
    public function validateStep5(array $eligibleOfficerIds)
    {
        $rules = [
            'primary_officer_id' => ['required', 'integer', Rule::in($eligibleOfficerIds)],
        ];

        if ($this->has_secondary_officer) {
            $rules['secondary_officer_id'] = ['required', 'integer', Rule::in($eligibleOfficerIds), 'different:primary_officer_id'];
        }

        $this->validate($rules, [
            'primary_officer_id.required' => 'Sila pilih Pegawai Utama.',
            'primary_officer_id.in' => 'Pegawai Utama mestilah pengguna berdaftar dalam agensi yang sama.',
            'secondary_officer_id.required' => 'Sila pilih Pegawai Kedua / Alternatif.',
            'secondary_officer_id.in' => 'Pegawai Kedua mestilah pengguna berdaftar dalam agensi yang sama.',
            'secondary_officer_id.different' => 'Pegawai Kedua tidak boleh sama dengan Pegawai Utama.',
        ]);
    }

    // --- PENGIRAAN KOS DINAMIK ---
    // Semua pengiraan dibuat dalam sen (integer) untuk mengelakkan ralat pembundaran float.

    public function calculateTotalDetailsCost(): float
    {
        return $this->subtotalSen() / 100;
    }

    public function sstRate(): float
    {
        return (float) config('sppict.sst_rate', 8);
    }

    /**
     * Ringkasan kos Fasa 3: Jumlah Kecil -> SST -> Jumlah Keseluruhan -> Baki Siling.
     */
    public function costSummary(): array
    {
        $subtotalSen = $this->subtotalSen();
        $sstSen = (int) round($subtotalSen * $this->sstRate() / 100);
        $totalSen = $subtotalSen + $sstSen;
        $ceilingSen = $this->toSen($this->ceiling_cost);
        $estimatedSen = $this->toSen($this->estimated_cost);

        return [
            'subtotal' => $subtotalSen / 100,
            'sst_rate' => $this->sstRate(),
            'sst_amount' => $sstSen / 100,
            'total' => $totalSen / 100,
            'ceiling' => $ceilingSen / 100,
            'ceiling_balance' => ($ceilingSen - $totalSen) / 100,
            'ceiling_usage_percent' => $ceilingSen > 0 ? round($totalSen / $ceilingSen * 100, 1) : 0,
            'estimated' => $estimatedSen / 100,
            'estimated_difference' => ($totalSen - $estimatedSen) / 100,
        ];
    }

    /**
     * Semak sama ada pemohon memasukkan SST sebagai item perincian.
     */
    public function hasManualSstItem(): bool
    {
        foreach ($this->details as $group) {
            foreach (($group['items'] ?? []) as $item) {
                if (preg_match('/\bSST\b/i', (string) ($item['technical_specifications'] ?? ''))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function subtotalSen(): int
    {
        $totalSen = 0;
        foreach ($this->details as $group) {
            foreach (($group['items'] ?? []) as $item) {
                $totalSen += (int) ($item['unit_quantity'] ?? 0) * $this->toSen($item['unit_cost'] ?? 0);
            }
        }

        return $totalSen;
    }

    private function toSen($amount): int
    {
        return (int) round(((float) ($amount ?: 0)) * 100);
    }
}
