<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md border border-gray-100">
    
    <!-- STEPPER PROGRESS BAR (5 FASA) -->
    <div class="mb-8 border-b pb-4">
        <div class="flex justify-between items-center text-xs font-semibold text-gray-500">
            <span class="{{ $currentStep >= 1 ? 'text-blue-600 font-bold' : '' }}">1. Maklumat Am</span>
            <span class="{{ $currentStep >= 2 ? 'text-blue-600 font-bold' : '' }}">2. Perolehan</span>
            <span class="{{ $currentStep >= 3 ? 'text-blue-600 font-bold' : '' }}">3. Perincian Projek</span>
            <span class="{{ $currentStep >= 4 ? 'text-blue-600 font-bold' : '' }}">4. Muat Naik Dokumen</span>
            <span class="{{ $currentStep >= 5 ? 'text-blue-600 font-bold' : '' }}">5. Pegawai Bertanggungjawab</span>
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

    {{-- PP2: ulasan Urus Setia bagi permohonan Tidak Lengkap (baca sahaja; tidak boleh diubah oleh pemohon) --}}
    @if ($this->isAmendment)
        <div class="mb-6 p-4 bg-purple-50 border-l-4 border-purple-500 text-purple-900 text-xs rounded space-y-1">
            <p class="font-bold">Permohonan ini Tidak Lengkap — sila buat pembetulan dan hantar semula.</p>
            @if ($this->amendmentRemarks)
                <p class="font-semibold">Ulasan Urus Setia{{ $this->amendmentReview ? ' (' . $this->amendmentReview->created_at->format('d/m/Y h:i A') . ')' : '' }}:</p>
                <p class="whitespace-pre-line">{{ $this->amendmentRemarks }}</p>
            @endif
        </div>
    @endif

    @error('form.agency')
        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 text-xs rounded">
            {{ $message }}
        </div>
    @enderror

    {{-- PP3: Urus Setia memilih & mengesahkan agensi sasaran sebelum mengisi borang --}}
    @if ($this->isOnBehalf && ! $confirmedAgencyId)
        <div class="p-5 bg-green-50 border border-green-200 rounded-lg space-y-4" wire:key="target-agency-picker">
            <div>
                <h3 class="text-sm font-bold text-green-900">Permohonan bagi Pihak Agensi</h3>
                <p class="text-xs text-green-800 mt-0.5">Pilih Jabatan/Agensi sasaran. Permohonan akan didaftarkan di bawah agensi ini dan Pegawai Bertanggungjawab dipilih daripada pegawai agensi tersebut. Agensi tidak boleh ditukar selepas permohonan disimpan.</p>
            </div>
            <x-form.select label="Jabatan/Agensi Sasaran" wire:model="targetAgencyId" :error="$errors->first('targetAgencyId')" required>
                <option value="">-- Pilih Jabatan/Agensi --</option>
                @foreach ($this->agencyOptions as $agency)
                    <option value="{{ $agency->id }}">{{ $agency->name }} ({{ $agency->code }})</option>
                @endforeach
            </x-form.select>
            <div class="flex justify-end">
                <button type="button" wire:click="confirmAgency" wire:loading.attr="disabled" class="px-5 py-2.5 bg-green-800 hover:bg-green-900 text-white text-xs font-bold rounded-lg shadow-sm transition">
                    Sahkan Agensi &amp; Teruskan →
                </button>
            </div>
        </div>
    @else
        @if ($this->applicationAgency)
            <div class="mb-6 px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs" wire:key="application-agency">
                <p class="text-slate-700">
                    <span class="font-semibold text-slate-500">{{ $this->isOnBehalf ? 'Permohonan bagi pihak:' : 'Jabatan/Agensi Permohonan:' }}</span>
                    <span class="font-bold text-slate-900">{{ $this->applicationAgency->name }} ({{ $this->applicationAgency->code }})</span>
                </p>
                @if ($this->isOnBehalf)
                    <button type="button" wire:click="changeAgency" wire:confirm="Tukar agensi sasaran? Pilihan Pegawai Bertanggungjawab akan dikosongkan." class="font-bold text-green-900 hover:underline">Tukar Agensi</button>
                @endif
            </div>
        @endif
        @error('targetAgencyId')
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 text-xs rounded">{{ $message }}</div>
        @enderror

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

    <!-- BUTANG NAVIGASI APLIKASI BERSAMA BUTANG SIMPAN DRAF -->
    <div class="flex justify-between items-center pt-8 border-t mt-8">
        <div>
            @if ($currentStep > 1)
                <button type="button" wire:click="previousStep" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">
                    ← Kembali
                </button>
            @endif
        </div>

        <div class="flex items-center space-x-3">
            <!-- BUTANG SIMPAN DRAF (SENTIASA ADA DI MANA-MANA FASA) -->
            <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" class="px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 text-xs font-bold rounded-lg transition flex items-center space-x-1.5">
                <span>💾</span>
                <span wire:loading.remove wire:target="saveDraft">Simpan Draf</span>
                <span wire:loading wire:target="saveDraft">Menyimpan...</span>
            </button>

            @if ($currentStep < 5)
                <button type="button" wire:click="nextStep" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                    Seterusnya →
                </button>
            @else
                <button type="button" wire:click="submitApplication" wire:loading.attr="disabled" @disabled(! $this->targetMeeting)
                        title="{{ $this->targetMeeting ? 'Hantar ke ' . $this->targetMeeting->label() : 'Tiada Mesyuarat JTICT yang dibuka. Sila simpan sebagai draf.' }}"
                        class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-md transition flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-emerald-600">
                    <span wire:loading.remove wire:target="submitApplication">🚀 Hantar Permohonan Projek</span>
                    <span wire:loading wire:target="submitApplication">Sedang Memproses...</span>
                </button>
            @endif
        </div>
    </div>
    @endif

</div>