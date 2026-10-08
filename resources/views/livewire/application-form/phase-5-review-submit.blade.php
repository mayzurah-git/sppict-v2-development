<div class="space-y-6" wire:key="phase-5-form">
    <div class="border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-700">Fasa 5: Pegawai Bertanggungjawab & Semakan Akhir</h3>
        <p class="text-xs text-gray-500">Sila sahkan maklumat pegawai untuk dihubungi sebelum membuat penyerahan rasmi.</p>
    </div>

    <!-- STATUS MESYUARAT JTICT -->
    @include('livewire.partials.jtict-status', ['meeting' => $this->targetMeeting])

    <!-- RINGKASAN KOS (SEMAKAN AKHIR) -->
    @php($summary = $form->costSummary())
    <div class="p-4 bg-gray-50 border rounded-lg">
        <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wider mb-3">Ringkasan Kos Projek</h4>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
            <div>
                <p class="text-[11px] text-gray-500">Anggaran Kos Siling</p>
                <p class="font-bold text-gray-800">RM {{ number_format($summary['ceiling'], 2) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-gray-500">Anggaran Kos Projek</p>
                <p class="font-bold text-gray-800">RM {{ number_format($summary['estimated'], 2) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-gray-500">Jumlah Kecil</p>
                <p class="font-bold text-gray-800">RM {{ number_format($summary['subtotal'], 2) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-gray-500">SST ({{ rtrim(rtrim(number_format($summary['sst_rate'], 2), '0'), '.') }}%)</p>
                <p class="font-bold text-gray-800">RM {{ number_format($summary['sst_amount'], 2) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-gray-500">Jumlah Keseluruhan</p>
                <p class="font-black {{ $summary['ceiling_balance'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">RM {{ number_format($summary['total'], 2) }}</p>
            </div>
        </div>
    </div>

    @php($candidates = $this->officerCandidates)

    <!-- PEGAWAI UTAMA -->
    <div class="p-4 bg-gray-50 border rounded-lg space-y-4">
        <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
            Pegawai Utama / Pengurus Projek
        </h4>

        <x-form.select label="Pilih Pegawai Utama" wire:model.live="form.primary_officer_id" :error="$errors->first('form.primary_officer_id')" required>
            <option value="">-- Pilih pegawai berdaftar dalam agensi --</option>
            @foreach ($candidates as $candidate)
                <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
            @endforeach
        </x-form.select>

        @include('livewire.application-form.partials.officer-card', ['officer' => $candidates->find($form->primary_officer_id)])
    </div>

    <!-- TOGGLE CHECKBOX PEGAWAI KEDUA -->
    <div class="p-3 bg-gray-100 rounded-lg border border-gray-200">
        <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" wire:model.live="form.has_secondary_officer" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
            <span class="ml-2 text-xs font-bold text-gray-700">Tambah Pegawai Kedua / Alternatif (Pilihan)</span>
        </label>
        <p class="text-[11px] text-gray-500 mt-0.5 ml-6">Pegawai kedua boleh membantu mengisi kemajuan projek dan menjadi rujukan sekiranya Pegawai Utama tidak dapat dihubungi.</p>
    </div>

    <!-- PEGAWAI KEDUA (DYNAMIC) -->
    @if ($form->has_secondary_officer)
        <div class="p-4 bg-blue-50/50 border border-blue-200 rounded-lg space-y-4">
            <h4 class="text-xs font-bold text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                Pegawai Kedua / Alternatif
            </h4>

            <x-form.select label="Pilih Pegawai Kedua" wire:model.live="form.secondary_officer_id" :error="$errors->first('form.secondary_officer_id')" required>
                <option value="">-- Pilih pegawai berdaftar dalam agensi --</option>
                @foreach ($candidates as $candidate)
                    @continue((string) $candidate->id === (string) $form->primary_officer_id)
                    <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                @endforeach
            </x-form.select>

            @include('livewire.application-form.partials.officer-card', ['officer' => $candidates->find($form->secondary_officer_id)])
        </div>
    @endif

    <p class="text-[11px] text-gray-500">
        Maklumat jawatan, e-mel dan telefon diambil daripada profil pengguna. Pegawai yang tiada dalam senarai perlu didaftarkan terlebih dahulu dalam SPPICT.
        Selepas permohonan dihantar, pertukaran pegawai dibuat melalui halaman paparan permohonan dan sejarahnya akan direkodkan.
    </p>
</div>