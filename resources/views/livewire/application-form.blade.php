<!-- resources/views/livewire/application-form.blade.php -->

<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\Application;
use App\Models\ApplicationDetail;
use App\Models\ApplicationDocument;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component 
{
    use WithFileUploads;

    // Fasa Tracker
    public int $currentStep = 1;

    // Fasa 1: Maklumat Am
    public string $title = '';
    public string $project_category = 'System Development';
    public string $objectives = '';    
    public string $project_scope = ''; 

    // Fasa 2: Perolehan & Kos
    public string $procurement_type = 'Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)';
    public string $procurement_method = 'Sebut Harga';
    public $ceiling_cost = '';
    public $estimated_cost = '';
    public $expected_duration_months = '';
    public string $outcome_code = '';

    // Fasa 3: Perincian Projek Mengikut Kategori
    public array $details = []; // Structure: [ ['item_category' => '...', 'items' => [ [...] ]] ]

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            $this->officer_name = $user->name ?? '';
            $this->officer_position = $user->position ?? '';
            $this->officer_email = $user->email ?? '';
            $this->officer_phone = $user->phone_number ?? '';
        }

        if (empty($this->details)) {
            $this->addCategoryGroup();
        }
    }

    // 1. Tambah Kategori Baharu
    public function addCategoryGroup()
    {
        $this->details[] = [
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

    // 2. Hapus Kategori
    public function removeCategoryGroup(int $categoryIndex)
    {
        if (count($this->details) > 1) {
            unset($this->details[$categoryIndex]);
            $this->details = array_values($this->details);
        }
    }

    // 3. Tambah Sub-Spesifikasi di dalam Kategori yang sama
    public function addSubItem(int $categoryIndex)
    {
        $this->details[$categoryIndex]['items'][] = [
            'technical_specifications' => '',
            'unit_quantity' => 1,
            'unit_cost' => 0.00,
        ];
    }

    // 4. Hapus Sub-Spesifikasi
    public function removeSubItem(int $categoryIndex, int $itemIndex)
    {
        if (count($this->details[$categoryIndex]['items']) > 1) {
            unset($this->details[$categoryIndex]['items'][$itemIndex]);
            $this->details[$categoryIndex]['items'] = array_values($this->details[$categoryIndex]['items']);
        }
    }

    // 5. Kirim Jumlah Keseluruhan Kos Fasa 3
    public function getTotalDetailsCostProperty()
    {
        $total = 0;
        foreach ($this->details as $group) {
            foreach ($group['items'] as $item) {
                $qty = (int) ($item['unit_quantity'] ?? 0);
                $cost = (float) ($item['unit_cost'] ?? 0);
                $total += ($qty * $cost);
            }
        }
        return $total;
    }

    // Fasa 4: Dokumen Sokongan (PDF Only)
    public $proposal_paper;
    public $presentation_slide;

    // Fasa 5: Pegawai Utama & Pegawai Pengganti
    public string $officer_name = '';
    public string $officer_position = '';
    public string $officer_email = '';
    public string $officer_phone = '';

    // Toggle & Maklumat Pegawai Pengganti (Optional)
    public bool $has_secondary_officer = false;
    public string $secondary_officer_name = '';
    public string $secondary_officer_position = '';
    public string $secondary_officer_email = '';
    public string $secondary_officer_phone = '';

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
            $this->validate([
                'title' => 'required|string|min:5|max:255',
                'project_category' => 'required|string',
                'objectives' => 'required|string|min:10',    
                'project_scope' => 'required|string|min:10', 
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'procurement_type' => 'required|string',
                'procurement_method' => 'required|string',
                'ceiling_cost' => 'required|numeric|min:100',
                'estimated_cost' => 'required|numeric|min:100',
                'expected_duration_months' => 'required|integer|min:1|max:120',
                'outcome_code' => 'required|string|max:50',

                ], [
                    'estimated_cost.lte' => 'Anggaran Kos Projek tidak boleh melebihi Anggaran Kos Siling.',
                    'expected_duration_months.integer' => 'Jangkaan tempoh pelaksanaan hendaklah dalam format angka bulan sahaja.',
                ]);
        } elseif ($this->currentStep === 3) {
            // 1. Semak syarat pengisian setiap item (kos seunit dibenarkan min:0 / 0.00)
            $this->validate([
                'details' => 'required|array|min:1',
                'details.*.item_category' => 'required|string',
                'details.*.items' => 'required|array|min:1',
                'details.*.items.*.technical_specifications' => 'required|string|min:3',
                'details.*.items.*.unit_quantity' => 'required|integer|min:1',
                'details.*.items.*.unit_cost' => 'required|numeric|min:0', // Dibenarkan 0.00 ke atas
            ], [
                'details.*.items.*.technical_specifications.required' => 'Spesifikasi teknikal wajib diisi.',
                'details.*.items.*.unit_quantity.min' => 'Jumlah unit sekurang-kurangnya 1.',
                'details.*.items.*.unit_cost.min' => 'Kos seunit tidak boleh kurang daripada 0.00.',
            ]);

            $estimatedCost = (float) $this->estimated_cost;
            $totalDetails = (float) $this->totalDetailsCost;

            // 2. Semak jika Jumlah Perincian KURANG daripada Anggaran Kos Projek
            if ($totalDetails < $estimatedCost) {
                $baki = $estimatedCost - $totalDetails;
                $message = 'Jumlah Keseluruhan Perincian Projek (RM ' . number_format($totalDetails, 2) . 
                        ') masih KURANG daripada Anggaran Kos Projek (RM ' . number_format($estimatedCost, 2) . 
                        '). Terdapat baki sebanyak RM ' . number_format($baki, 2) . ' yang belum diperincikan.';
                
                $this->addError('details_total', $message);
                throw \Illuminate\Validation\ValidationException::withMessages(['details_total' => $message]);
            }

            // 3. Semak jika Jumlah Perincian MELEBIHI Anggaran Kos Projek
            if ($totalDetails > $estimatedCost) {
                $lebih = $totalDetails - $estimatedCost;
                $message = 'Jumlah Keseluruhan Perincian Projek (RM ' . number_format($totalDetails, 2) . 
                        ') MELEBIHI Anggaran Kos Projek (RM ' . number_format($estimatedCost, 2) . 
                        ') sebanyak RM ' . number_format($lebih, 2) . '.';

                $this->addError('details_total', $message);
                throw \Illuminate\Validation\ValidationException::withMessages(['details_total' => $message]);
            }
        } elseif ($this->currentStep === 4) {
            // Kertas kerja wajib diisi jika BELUM ada fail yang dimuat naik sebelum ini
            $rules = [
                'proposal_paper' => $this->proposal_paper 
                    ? 'file|mimes:pdf|max:15360' 
                    : 'required|file|mimes:pdf|max:15360',
                'presentation_slide' => 'nullable|file|mimes:pdf|max:15360',
            ];

            $messages = [
                'proposal_paper.required' => 'Sila muat naik Kertas Cadangan / Permohonan dalam format PDF.',
                'proposal_paper.mimes' => 'Kertas Cadangan hendaklah dalam format PDF sahaja.',
                'presentation_slide.mimes' => 'Slaid Pembentangan hendaklah dalam format PDF sahaja.',
            ];

            $this->validate($rules, $messages);
        }
    }

    public function submitApplication()
    {
        // 1. Pengesahan asas bagi Pegawai Utama (Fasa 5)
        $rules = [
            'officer_name' => 'required|string|max:255',
            'officer_position' => 'required|string|max:255',
            'officer_email' => 'required|email|max:255',
            'officer_phone' => 'required|string|max:50',
        ];

        // Tambah pengesahan Pegawai Pengganti sekiranya toggle diaktifkan
        if ($this->has_secondary_officer) {
            $rules['secondary_officer_name'] = 'required|string|max:255';
            $rules['secondary_officer_position'] = 'required|string|max:255';
            $rules['secondary_officer_email'] = 'required|email|max:255';
            $rules['secondary_officer_phone'] = 'required|string|max:50';
        }

        $this->validate($rules);

        $user = Auth::user();

        // 2. Jana No. Rujukan Permohonan Unik
        $year = date('Y');
        $count = Application::whereYear('created_at', $year)->count() + 1;
        $refNo = sprintf('SPPICT/%s/%04d', $year, $count);

        // 3. Cipta Rekod Permohonan Utama
        $application = Application::create([
            'uuid' => (string) Str::uuid(),
            'reference_number' => $refNo,
            'agency_id' => $user->agency_id ?? 1,
            'applicant_id' => $user->id,
            'title' => $this->title,
            'project_category' => $this->project_category,
            'description' => $this->description,
            'objectives' => $this->objectives,       
            'project_scope' => $this->project_scope, 
            'procurement_type' => $this->procurement_type,
            'procurement_method' => $this->procurement_method,
            'ceiling_cost' => $this->ceiling_cost,
            'estimated_cost' => $this->estimated_cost,
            'expected_duration_months' => $this->expected_duration_months,
            'outcome_code' => $this->outcome_code,
            'status' => 'SUBMITTED',
            'primary_officer_name' => $this->officer_name,
            'primary_officer_position' => $this->officer_position,
            'primary_officer_email' => $this->officer_email,
            'primary_officer_phone' => $this->officer_phone,
            'secondary_officer_name' => $this->has_secondary_officer ? $this->secondary_officer_name : null,
            'secondary_officer_position' => $this->has_secondary_officer ? $this->secondary_officer_position : null,
            'secondary_officer_email' => $this->has_secondary_officer ? $this->secondary_officer_email : null,
            'secondary_officer_phone' => $this->has_secondary_officer ? $this->secondary_officer_phone : null,
        ]);

        // 4. Simpan senarai sub-item perincian (Fasa 3) ke pangkalan data
        foreach ($this->details as $group) {
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

        // 5. Simpan Dokumen Kertas Cadangan PDF (Fasa 4)
        if ($this->proposal_paper) {
            $path = $this->proposal_paper->store('documents/proposals', 'private');
            ApplicationDocument::create([
                'uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'document_type' => 'PROPOSAL_PAPER',
                'file_name' => $this->proposal_paper->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size_kb' => round($this->proposal_paper->getSize() / 1024),
            ]);
        }

        // 6. Simpan Dokumen Slaid Pembentangan PDF (Fasa 4)
        if ($this->presentation_slide) {
            $pathSlide = $this->presentation_slide->store('documents/slides', 'private');
            ApplicationDocument::create([
                'uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'document_type' => 'PRESENTATION_SLIDE_JTICT',
                'file_name' => $this->presentation_slide->getClientOriginalName(),
                'file_path' => $pathSlide,
                'mime_type' => 'application/pdf',
                'file_size_kb' => round($this->presentation_slide->getSize() / 1024),
            ]);
        }

        session()->flash('message', "Permohonan Projek ({$refNo}) telah berjaya dihantar ke Urus Setia!");

        return redirect()->to('/dashboard');
    }

}; ?>

<div class="max-w-4xl mx-auto p-6 bg-white rounded-xl shadow-md my-8 border border-gray-100">
    <!-- Header Borang -->
    <div class="border-b pb-4 mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Permohonan Projek ICT</h2>
        <p class="text-sm text-gray-500">Sila isi semua maklumat permohonan mengikut fasa yang telah ditetapkan.</p>
    </div>

    <!-- Indicator Wizard Fasa (1 hingga 5) -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            @for ($step = 1; $step <= 5; $step++)
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-200 
                        {{ $currentStep === $step ? 'bg-blue-600 text-white ring-4 ring-blue-100' : ($currentStep > $step ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600') }}">
                        @if ($currentStep > $step)
                            ✓
                        @else
                            {{ $step }}
                        @endif
                    </div>
                    <span class="text-xs mt-2 font-medium {{ $currentStep === $step ? 'text-blue-600 font-bold' : 'text-gray-500' }}">
                        @if ($step === 1) Fasa 1: Am
                        @elseif ($step === 2) Fasa 2: Perolehan
                        @elseif ($step === 3) Fasa 3: Perincian
                        @elseif ($step === 4) Fasa 4: Dokumen
                        @elseif ($step === 5) Fasa 5: Pengurus
                        @endif
                    </span>
                </div>
                @if ($step < 5)
                    <div class="flex-1 h-1 mx-2 {{ $currentStep > $step ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                @endif
            @endfor
        </div>
    </div>

    <!-- Kandungan Form Berfasa -->
    <form wire:submit.prevent="submitApplication">

        <!-- FASA 1: MAKLUMAT AM -->
        @if ($currentStep === 1)
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">Fasa 1: Maklumat Am & Kategori Projek</h3>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tajuk Projek *</label>
                    <input type="text" wire:model="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Contoh: Naik Taraf Infrastruktur Rangkaian LAN Agensi">
                    @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Kategori Projek ICT *</label>
                    <select wire:model="project_category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="System Development">Pembangunan Sistem / Aplikasi</option>
                        <option value="Hardware">Perkakasan (Hardware)</option>
                        <option value="Software">Perisian / Lesen (Software)</option>
                        <option value="Network">Rangkaian & Keselamatan (Network/Security)</option>
                        <option value="Maintenance">Penyelenggaraan & Perkhidmatan</option>
                    </select>
                    @error('project_category') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- MEDAN BAHARU: OBJEKTIF PROJEK -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Objektif Projek *</label>
                    <textarea wire:model="objectives" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Nyatakan objektif-objektif utama projek (cth: 1. Meningkatkan kelajuan capaian rangkaian...)"></textarea>
                    @error('objectives') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- MEDAN BAHARU: SKOP PROJEK -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Skop Projek *</label>
                    <textarea wire:model="project_scope" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Nyatakan skop pelaksanaan projek secara terperinci..."></textarea>
                    @error('project_scope') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
        @endif

        <!-- FASA 2: PEROLEHAN & KOS -->
        @if ($currentStep === 2)
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2">Fasa 2: Perolehan & Anggaran Kos</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jenis Perolehan *</label>
                        <select wire:model="procurement_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)">Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)</option>
                            <option value="Pembekalan / Perkhidmatan Bermasa">Pembekalan / Perkhidmatan Bermasa</option>
                            <option value="Penyelenggaraan">Penyelenggaraan</option>
                            <option value="Pembangunan Aplikasi">Pembangunan Aplikasi</option>
                        </select>
                        @error('procurement_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kaedah Perolehan *</label>
                        <select wire:model="procurement_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="Sebut Harga">Sebut Harga</option>
                            <option value="Tender">Tender Awam</option>
                            <option value="Direct Purchase">Pembelian Terus</option>
                            <option value="Rundingan Terus">Rundingan Terus</option>
                        </select>
                        @error('procurement_method') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- MEDAN BAHARU 1: ANGGARAN KOS SILING (DI ATAS KOS PROJEK) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Anggaran Kos Siling (RM) *</label>
                    <input type="number" step="0.01" wire:model="ceiling_cost" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Contoh: 200000.00">
                    @error('ceiling_cost') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Anggaran Kos Projek (RM) *</label>
                    <input type="number" step="0.01" wire:model="estimated_cost" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Contoh: 150000.00">
                    <p class="text-xs text-gray-500 mt-1">Sistem akan menyalurkan peringkat kelulusan automatik mengikut nilai ini (RM 20k-50k / RM 50k-500k / >RM 500k).</p>
                    @error('estimated_cost') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- MEDAN BAHARU 2: JANGKAAN TEMPOH PELAKSANAAN (BULAN) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Jangkaan Tempoh Pelaksanaan (Bulan) *</label>
                    <div class="relative mt-1 rounded-md shadow-sm">
                        <input type="number" min="1" step="1" wire:model="expected_duration_months" class="block w-full rounded-md border-gray-300 pr-16 focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Contoh: 12">
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                            <span class="text-gray-500 text-xs font-semibold">Bulan</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Nyatakan tempoh dalam bentuk bilangan bulan sahaja (cth: 6, 12, 24, 36).</p>
                    @error('expected_duration_months') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Rujukan Kelulusan Jabatan / Vot Peruntukan *</label>
                    <input type="text" wire:model="outcome_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="Contoh: B02-28101">
                    @error('outcome_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
        @endif

        <!-- FASA 3: PERINCIAN PROJEK (PENGISIAN & RINGKASAN JADUAL) -->
        @if ($currentStep === 3)
            <div class="space-y-8">
                <!-- HEADER FASA 3 -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b pb-4 gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Fasa 3: Perincian Projek</h3>
                        <p class="text-xs text-gray-500 mt-1">Sila isi spesifikasi teknikal dan semak Jadual Ringkasan Perincian di bahagian bawah.</p>
                    </div>
                    
                    <!-- BADGE STATUS PERBANDINGAN KOS -->
                    @php
                        $isMatch = abs($this->totalDetailsCost - (float)$estimated_cost) < 0.01;
                        $diff = (float)$estimated_cost - $this->totalDetailsCost;
                    @endphp
                    <div class="p-3 rounded-lg border text-right w-full md:w-auto {{ $isMatch ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }}">
                        <span class="text-xs text-gray-600 block">Anggaran Kos Projek (Fasa 2): <strong>RM {{ number_format((float)$estimated_cost, 2) }}</strong></span>
                        <span class="text-xs font-bold block mt-0.5 {{ $isMatch ? 'text-green-700' : 'text-amber-800' }}">
                            Jumlah Perincian: RM {{ number_format($this->totalDetailsCost, 2) }}
                            @if ($isMatch)
                                ✓
                            @elseif ($diff > 0)
                                ⚠ (Kurang RM {{ number_format($diff, 2) }})
                            @else
                                ⚠ (Melebihi RM {{ number_format(abs($diff), 2) }})
                            @endif
                        </span>
                    </div>
                </div>

                @error('details_total')
                    <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700 text-xs rounded-md shadow-xs">
                        <strong>Ralat Pengesahan Kos:</strong> {{ $message }}
                    </div>
                @enderror

                <!-- 1. BORANG PENGISIAN KATEGORI & ITEM -->
                <div class="space-y-6">
                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                        Borang Pengisian Perincian Item
                    </h4>

                    @foreach ($details as $catIndex => $group)
                        <div class="p-5 bg-gray-50 border border-gray-200 rounded-xl space-y-4">
                            
                            <!-- KATEGORI HEADER -->
                            <div class="flex justify-between items-center bg-white p-3 rounded-lg border shadow-xs">
                                <div class="flex-1 mr-4">
                                    <label class="block text-xs font-bold text-gray-700 uppercase">Kategori Projek #{{ $catIndex + 1 }} *</label>
                                    <select wire:model="details.{{ $catIndex }}.item_category" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-semibold focus:border-blue-500 focus:ring-blue-500">
                                        <option value="Projek Baharu">A. PROJEK BAHARU</option>
                                        <option value="Peningkatan Sistem">B. PENINGKATAN SISTEM</option>
                                        <option value="Peluasan Sistem/Projek">C. PELUASAN SISTEM / PROJEK</option>
                                        <option value="Penambahbaikan Peralatan">D. PENAMBAHBAIKAN PERALATAN</option>
                                        <option value="Penyelenggaraan">E. PENYELENGGARAAN</option>
                                        <option value="Khidmat Perunding ICT">F. KHIDMAT PERUNDING ICT</option>
                                    </select>
                                </div>
                                
                                @if (count($details) > 1)
                                    <button type="button" wire:click="removeCategoryGroup({{ $catIndex }})" class="text-red-600 hover:text-red-800 text-xs font-bold px-3 py-2 bg-red-50 rounded-md border border-red-200">
                                        ✕ Padam Kategori
                                    </button>
                                @endif
                            </div>

                            <!-- SUB-ITEMS -->
                            <div class="space-y-3">
                                @foreach ($group['items'] as $itemIndex => $item)
                                    <div class="p-4 bg-white border rounded-lg space-y-3 shadow-xs">
                                        <div class="flex justify-between items-center border-b pb-2">
                                            <span class="text-xs font-semibold text-blue-600">Spesifikasi {{ $catIndex + 1 }}.{{ $itemIndex + 1 }}</span>
                                            @if (count($group['items']) > 1)
                                                <button type="button" wire:click="removeSubItem({{ $catIndex }}, {{ $itemIndex }})" class="text-red-500 hover:text-red-700 text-xs">
                                                    ✕ Hapus Spesifikasi
                                                </button>
                                            @endif
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Spesifikasi Teknikal (Minima) *</label>
                                            <textarea wire:model="details.{{ $catIndex }}.items.{{ $itemIndex }}.technical_specifications" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500" placeholder="Nyatakan perincian spesifikasi teknikal..."></textarea>
                                            @error("details.$catIndex.items.$itemIndex.technical_specifications") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Jumlah Unit Diperlukan *</label>
                                                <input type="number" min="1" step="1" wire:model.live="details.{{ $catIndex }}.items.{{ $itemIndex }}.unit_quantity" class="mt-1 block w-full rounded-md border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500">
                                                @error("details.$catIndex.items.$itemIndex.unit_quantity") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Anggaran Kos Seunit (RM) *</label>
                                                <input type="number" step="0.01" min="0" wire:model.live="details.{{ $catIndex }}.items.{{ $itemIndex }}.unit_cost" class="mt-1 block w-full rounded-md border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500" placeholder="0.00">
                                                <span class="text-[10px] text-gray-400">Dibenarkan 0.00 jika FOC / waranti</span>
                                                @error("details.$catIndex.items.$itemIndex.unit_cost") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <button type="button" wire:click="addSubItem({{ $catIndex }})" class="px-3 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-semibold rounded-md hover:bg-blue-100 transition">
                                + Tambah Spesifikasi (Kategori Ini)
                            </button>
                        </div>
                    @endforeach

                    <button type="button" wire:click="addCategoryGroup" class="px-4 py-2 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-700 transition">
                        + Tambah Kategori Projek
                    </button>
                </div>

                <hr class="my-8 border-gray-200">

                <!-- 2. PAPARAN JADUAL RINGKASAN PERINCIAN PROJEK (PREVIEW TABLE) -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wide flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-green-600"></span>
                            Ringkasan Jadual Perincian Projek (Paparan Awalan)
                        </h4>
                        <span class="text-xs text-gray-500 italic">* Jadual ini dikemaskini secara langsung semasa anda mengisi borang di atas.</span>
                    </div>

                    <div class="overflow-x-auto border border-gray-300 rounded-lg shadow-sm bg-white">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead>
                                <tr class="bg-gray-800 text-white">
                                    <th scope="col" class="px-3 py-3 text-center font-bold uppercase w-12 border-r border-gray-700">BIL</th>
                                    <th scope="col" class="px-4 py-3 text-left font-bold uppercase border-r border-gray-700">SPESIFIKASI TEKNIKAL (MINIMA)</th>
                                    <th scope="col" class="px-3 py-3 text-center font-bold uppercase w-28 border-r border-gray-700">JUMLAH UNIT DIPERLUKAN</th>
                                    <th scope="col" class="px-4 py-3 text-right font-bold uppercase w-36 border-r border-gray-700">ANGGARAN KOS SEUNIT (RM)</th>
                                    <th scope="col" class="px-4 py-3 text-right font-bold uppercase w-36">JUMLAH KOS (RM)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @php $overallTotal = 0; @endphp

                                @foreach ($details as $catIndex => $group)
                                    @php $categorySubtotal = 0; @endphp
                                    
                                    <!-- ROW TAJUK KATEGORI (PROJEK BAHARU, PENINGKATAN SISTEM, DLL) -->
                                    <tr class="bg-blue-50/70 border-y border-blue-200 font-bold text-blue-900">
                                        <td colspan="5" class="px-4 py-2.5 text-left uppercase tracking-wider text-xs bg-blue-100/50">
                                            {{ chr(65 + $catIndex) }}. {{ strtoupper($group['item_category']) }}
                                        </td>
                                    </tr>

                                    <!-- ROW SUB-ITEMS SPESIFIKASI -->
                                    @foreach ($group['items'] as $itemIndex => $item)
                                        @php
                                            $qty = (int) ($item['unit_quantity'] ?? 0);
                                            $unitCost = (float) ($item['unit_cost'] ?? 0);
                                            $itemTotal = $qty * $unitCost;
                                            $categorySubtotal += $itemTotal;
                                            $overallTotal += $itemTotal;
                                        @endphp
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-3 py-3 text-center font-semibold text-gray-500 border-r border-gray-200">
                                                {{ $itemIndex + 1 }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-800 border-r border-gray-200 whitespace-pre-line leading-relaxed">
                                                {{ $item['technical_specifications'] ?: '(Spesifikasi belum diisi)' }}
                                            </td>
                                            <td class="px-3 py-3 text-center font-semibold text-gray-700 border-r border-gray-200">
                                                {{ $qty }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700 border-r border-gray-200 font-mono">
                                                {{ number_format($unitCost, 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-right font-bold text-gray-900 font-mono">
                                                {{ number_format($itemTotal, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach

                                    <!-- SUB-TOTAL KATEGORI -->
                                    <tr class="bg-gray-100/80 font-bold text-gray-800 border-b-2 border-gray-300">
                                        <td colspan="4" class="px-4 py-2 text-right uppercase text-[11px] tracking-wider border-r border-gray-200">
                                            JUMLAH {{ strtoupper($group['item_category']) }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-xs text-blue-900 bg-blue-50/50">
                                            RM {{ number_format($categorySubtotal, 2) }}
                                        </td>
                                    </tr>
                                @endforeach

                                <!-- JUMLAH KESELURUHAN -->
                                <tr class="bg-gray-900 text-white font-extrabold text-sm">
                                    <td colspan="4" class="px-4 py-3 text-right uppercase tracking-wider border-r border-gray-700">
                                        JUMLAH KESELURUHAN
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-green-400 bg-gray-950">
                                        RM {{ number_format($overallTotal, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- FASA 4: DOKUMEN SOKONGAN (PDF ONLY) -->
            @if ($currentStep === 4)
                <div class="space-y-6">
                    <div class="border-b pb-2">
                        <h3 class="text-lg font-semibold text-gray-700">Fasa 4: Muat Naik Dokumen Sokongan</h3>
                        <p class="text-xs text-gray-500">Sila muat naik dokumen sokongan dalam format PDF. Dokumen yang telah dimuat naik akan dikekalkan.</p>
                    </div>

                    <div class="bg-amber-50 border-l-4 border-amber-400 p-3 text-xs text-amber-800 rounded">
                        <strong>Syarat Keselamatan & Format:</strong> Semua dokumen sokongan hendaklah dimuat naik dalam format <strong>PDF sahaja</strong> dengan saiz maksimum 15MB bagi setiap fail[cite: 2, 3].
                    </div>

                    <!-- 1. KERTAS CADANGAN / PERMOHONAN -->
                    <div class="p-4 bg-gray-50 border rounded-lg space-y-3">
                        <label class="block text-sm font-medium text-gray-700">1. Kertas Cadangan / Permohonan (PDF) *</label>

                        <!-- KAD STATUS JIKA FAIL SUDAH DIPILIH / DIMUAT NAIK -->
                        @if ($proposal_paper)
                            <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md text-xs">
                                <div class="flex items-center space-x-2">
                                    <span class="text-green-600 font-bold">✓ Dokumen Bersedia:</span>
                                    <span class="font-semibold text-gray-800">
                                        {{ is_object($proposal_paper) ? $proposal_paper->getClientOriginalName() : 'Kertas Cadangan Telah Dimuat Naik' }}
                                    </span>
                                    @if (is_object($proposal_paper))
                                        <span class="text-gray-500">({{ round($proposal_paper->getSize() / 1024, 1) }} KB)</span>
                                    @endif
                                </div>
                                <span class="text-[11px] bg-green-100 text-green-800 font-medium px-2 py-0.5 rounded">PDF</span>
                            </div>
                            <p class="text-[11px] text-gray-500 italic">Pilih fail baharu di bawah sekiranya anda ingin menggantikan dokumen sedia ada ini:</p>
                        @endif

                        <!-- INPUT FILE -->
                        <input type="file" wire:model="proposal_paper" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        
                        <div wire:loading wire:target="proposal_paper" class="text-xs text-blue-600 font-medium">
                            ⏳ Memproses & mengesahkan fail PDF...
                        </div>
                        
                        @error('proposal_paper') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>

                    <!-- 2. SLAID PEMBENTANGAN JTICT / JPICT -->
                    <div class="p-4 bg-gray-50 border rounded-lg space-y-3">
                        <label class="block text-sm font-medium text-gray-700">2. Slaid Pembentangan JTICT/JPICT (PDF) (Pilihan)</label>

                        <!-- KAD STATUS JIKA FAIL SUDAH DIPILIH / DIMUAT NAIK -->
                        @if ($presentation_slide)
                            <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-md text-xs">
                                <div class="flex items-center space-x-2">
                                    <span class="text-blue-600 font-bold">✓ Slaid Bersedia:</span>
                                    <span class="font-semibold text-gray-800">
                                        {{ is_object($presentation_slide) ? $presentation_slide->getClientOriginalName() : 'Slaid Pembentangan Telah Dimuat Naik' }}
                                    </span>
                                    @if (is_object($presentation_slide))
                                        <span class="text-gray-500">({{ round($presentation_slide->getSize() / 1024, 1) }} KB)</span>
                                    @endif
                                </div>
                                <span class="text-[11px] bg-blue-100 text-blue-800 font-medium px-2 py-0.5 rounded">PDF</span>
                            </div>
                            <p class="text-[11px] text-gray-500 italic">Pilih fail baharu di bawah sekiranya anda ingin menggantikan slaid sedia ada ini:</p>
                        @endif

                        <!-- INPUT FILE -->
                        <input type="file" wire:model="presentation_slide" accept="application/pdf" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        
                        <div wire:loading wire:target="presentation_slide" class="text-xs text-blue-600 font-medium">
                            ⏳ Memproses & mengesahkan fail PDF...
                        </div>

                        @error('presentation_slide') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>
                </div>
            @endif

        <!-- FASA 5: MAKLUMAT PEGAWAI BERTANGGUNGJAWAB & PEGAWAI PENGGANTI -->
        @if ($currentStep === 5)
            <div class="space-y-6">
                <div class="border-b pb-2">
                    <h3 class="text-lg font-semibold text-gray-700">Fasa 5: Maklumat Pegawai Bertanggungjawab</h3>
                    <p class="text-xs text-gray-500">Sila sahkan maklumat Pegawai Utama yang boleh dihubungi bagi permohonan ini.</p>
                </div>

                <!-- SEKSYEN 1: PEGAWAI UTAMA (PENGURUS PROJEK) -->
                <div class="p-4 bg-gray-50 border rounded-lg space-y-4">
                    <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                        Pegawai Utama / Pengurus Projek (Pemohon)
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Nama Pegawai *</label>
                            <input type="text" wire:model="officer_name" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('officer_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Jawatan & Gred *</label>
                            <input type="text" wire:model="officer_position" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('officer_position') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">E-mel Rasmi *</label>
                            <input type="email" wire:model="officer_email" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('officer_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">No. Telefon Pejabat / Bimbit *</label>
                            <input type="text" wire:model="officer_phone" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('officer_phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- TOGGLE CHECKBOX UNTUK PEGAWAI PENGGANTI -->
                <div class="p-3 bg-gray-100 rounded-lg border border-gray-200">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="has_secondary_officer" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span class="ml-2 text-xs font-bold text-gray-700">Tambah Maklumat Pegawai Pengganti / Urus Setia Agensi (Pilihan)</span>
                    </label>
                    <p class="text-[11px] text-gray-500 mt-0.5 ml-6">Tanda pilihan ini sekiranya anda ingin mendaftarkan pegawai kedua sebagai alternatif jika berlaku pertukaran pegawai.</p>
                </div>

                <!-- SEKSYEN 2: PEGAWAI PENGGANTI (HANYA DIPAPARKAN JIKA TOGGLE DITANDA) -->
                @if ($has_secondary_officer)
                    <div class="p-4 bg-blue-50/50 border border-blue-200 rounded-lg space-y-4">
                        <h4 class="text-xs font-bold text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Maklumat Pegawai Pengganti (Alternatif)
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Nama Pegawai Pengganti *</label>
                                <input type="text" wire:model="secondary_officer_name" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Contoh: Encik Ahmad bin Hassan">
                                @error('secondary_officer_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Jawatan & Gred *</label>
                                <input type="text" wire:model="secondary_officer_position" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Contoh: Penolong Pegawai TM F29">
                                @error('secondary_officer_position') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700">E-mel Rasmi *</label>
                                <input type="email" wire:model="secondary_officer_email" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="ahmad@ns.gov.my">
                                @error('secondary_officer_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">No. Telefon Pejabat / Bimbit *</label>
                                <input type="text" wire:model="secondary_officer_phone" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="06-1234567 / 012-3456789">
                                @error('secondary_officer_phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Butang Navigasi (Kembali / Seterusnya / Hantar) -->
        <div class="mt-8 flex justify-between items-center border-t pt-4">
            @if ($currentStep > 1)
                <button type="button" wire:click="previousStep" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm font-medium transition">
                    ← Kembali
                </button>
            @else
                <div></div>
            @endif

            @if ($currentStep < 5)
                <button type="button" wire:click="nextStep" class="px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium transition shadow">
                    Seterusnya →
                </button>
            @else
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-bold transition shadow-lg">
                    ✓ Hantar Permohonan Projek
                </button>
            @endif
        </div>

    </form>
</div>