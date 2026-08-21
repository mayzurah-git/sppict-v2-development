<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md border border-gray-100">
    
    <!-- STEPPER PROGRESS BAR (5 FASA) -->
    <div class="mb-8 border-b pb-4">
        <div class="flex justify-between items-center text-xs font-semibold text-gray-500">
            <span class="{{ $currentStep >= 1 ? 'text-blue-600 font-bold' : '' }}">1. Maklumat Am</span>
            <span class="{{ $currentStep >= 2 ? 'text-blue-600 font-bold' : '' }}">2. Perolehan</span>
            <span class="{{ $currentStep >= 3 ? 'text-blue-600 font-bold' : '' }}">3. Perincian Projek</span>
            <span class="{{ $currentStep >= 4 ? 'text-blue-600 font-bold' : '' }}">4. Muat Naik Dokumen</span>
            <span class="{{ $currentStep >= 5 ? 'text-blue-600 font-bold' : '' }}">5. Semakan & Pegawai</span>
        </div>
        <div class="w-full bg-gray-200 h-2 rounded-full mt-3 overflow-hidden">
            <div class="bg-blue-600 h-2 transition-all duration-300 ease-in-out" style="width: {{ ($currentStep / 5) * 100 }}%"></div>
        </div>
    </div>

    <!-- PENGENDALIAN MESEJ GLOBAL -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 text-xs rounded">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 text-xs rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- INCLUSION PARTIAL VIEWS (MENGIKUT 5 FASA) -->
    <div class="min-h-[350px]" wire:key="application-step-{{ $currentStep }}">
        @if ($currentStep === 1)
            @include('livewire.application-form.phase-1-project-details')
        @elseif ($currentStep === 2)
            @include('livewire.application-form.phase-2-financial-details')
        @elseif ($currentStep === 3)
            @include('livewire.application-form.phase-3-item-details')
        @elseif ($currentStep === 4)
            @include('livewire.application-form.phase-4-documents')
        @elseif ($currentStep === 5)
            @include('livewire.application-form.phase-5-review-submit')
        @endif
    </div>

    <!-- BUTANG NAVIGASI -->
    <div class="flex justify-between items-center pt-8 border-t mt-8">
        @if ($currentStep > 1)
            <button type="button" wire:click="previousStep" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">
                ← Kembali
            </button>
        @else
            <div></div>
        @endif

        @if ($currentStep < 5)
            <button type="button" wire:click="nextStep" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                Seterusnya →
            </button>
        @else
            <button type="button" wire:click="submitApplication" wire:loading.attr="disabled" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-md transition flex items-center space-x-2 disabled:opacity-50">
                <span wire:loading.remove wire:target="submitApplication">🚀 Hantar Permohonan Projek</span>
                <span wire:loading wire:target="submitApplication" class="flex items-center space-x-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Sedang Memproses...</span>
                </span>
            </button>
        @endif
    </div>

</div>