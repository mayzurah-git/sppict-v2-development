<div class="space-y-6" wire:key="phase-5-form">
    <!-- Mesyuarat JTICT bagi penghantaran semula (status tempoh semasa dipaparkan di kepala halaman) -->
    @if ($this->requiresMeetingSelection)
        {{-- PP2: mesyuarat asal permohonan Tidak Lengkap tidak lagi dibuka; pemohon mesti memilih mesyuarat aktif --}}
        @if ($this->eligibleMeetings->isNotEmpty())
            <div class="p-4 rounded-xl border space-y-3 {{ $theme['panel'] }}">
                <div>
                    <p class="text-sm font-extrabold">Pilih Mesyuarat JTICT untuk penghantaran semula</p>
                    <p class="mt-0.5 text-[13px]">Mesyuarat JTICT asal permohonan ini tidak lagi menerima permohonan. Sila pilih Mesyuarat JTICT yang sedang dibuka.</p>
                </div>
                <x-form.select size="lg" :focus="$theme['input']" label="Mesyuarat JTICT" wire:model.live="resubmissionMeetingId" :error="$errors->first('resubmissionMeetingId')" required>
                    <option value="">-- Pilih Mesyuarat JTICT --</option>
                    @foreach ($this->eligibleMeetings as $meeting)
                        <option value="{{ $meeting->id }}">{{ $meeting->label() }} (tutup {{ $meeting->submission_closes_at->format('d/m/Y') }})</option>
                    @endforeach
                </x-form.select>
            </div>
        @else
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-[13px] text-amber-900">
                <p class="text-sm font-extrabold">Tiada Mesyuarat JTICT aktif untuk penghantaran semula</p>
                <p class="mt-0.5">Mesyuarat JTICT asal permohonan ini tidak lagi menerima permohonan dan tiada Mesyuarat JTICT lain yang dibuka. Anda boleh menyimpan pindaan dan menghantar semula apabila Mesyuarat JTICT dibuka oleh Urus Setia.</p>
            </div>
        @endif
    @elseif (($this->isAmendment || $this->preJtictAmendmentDecision) && $this->targetMeeting)
        <p class="text-[13px] font-bold {{ $theme['eyebrow'] }}">Penghantaran semula pindaan ke mesyuarat asal permohonan ({{ $this->targetMeeting->label() }}).</p>
    @endif

    @php
        $candidates = $this->officerCandidates;
    @endphp

    <!-- Pegawai Bertanggungjawab -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="space-y-3">
            <x-form.select size="lg" :focus="$theme['input']" label="Pegawai Utama / Pengurus Projek" wire:model.live="form.primary_officer_id" :error="$errors->first('form.primary_officer_id')" required>
                <option value="">-- Pilih pegawai berdaftar dalam agensi --</option>
                @foreach ($candidates as $candidate)
                    <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                @endforeach
            </x-form.select>
            @include('livewire.application-form.partials.officer-card', ['officer' => $candidates->find($form->primary_officer_id)])
        </div>

        <div class="space-y-3">
            <label class="flex items-start gap-2.5 min-h-[22px] cursor-pointer">
                <input type="checkbox" wire:model.live="form.has_secondary_officer" class="mt-0.5 w-[18px] h-[18px] rounded border-slate-300 {{ $theme['input'] }}">
                <span>
                    <span class="block text-[13px] font-bold text-slate-700">Tambah Pegawai Kedua / Alternatif (Pilihan)</span>
                    <span class="block text-xs text-slate-500 mt-0.5">Pegawai kedua boleh membantu mengisi kemajuan projek dan menjadi rujukan sekiranya Pegawai Utama tidak dapat dihubungi.</span>
                </span>
            </label>

            @if ($form->has_secondary_officer)
                <x-form.select size="lg" :focus="$theme['input']" label="Pegawai Kedua / Alternatif" wire:model.live="form.secondary_officer_id" :error="$errors->first('form.secondary_officer_id')" required>
                    <option value="">-- Pilih pegawai berdaftar dalam agensi --</option>
                    @foreach ($candidates as $candidate)
                        @continue((string) $candidate->id === (string) $form->primary_officer_id)
                        <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                    @endforeach
                </x-form.select>
                @include('livewire.application-form.partials.officer-card', ['officer' => $candidates->find($form->secondary_officer_id)])
            @endif
        </div>
    </div>

    <p class="text-xs text-slate-500">
        Maklumat jawatan, e-mel dan telefon diambil daripada profil pengguna. Pegawai yang tiada dalam senarai perlu didaftarkan terlebih dahulu dalam SPPICT.
        Selepas permohonan dihantar, pertukaran pegawai dibuat melalui halaman paparan permohonan dan sejarahnya akan direkodkan.
    </p>

    <!-- Semakan akhir -->
    @php
        $summary = $form->costSummary();
        $method = $this->procurementMethodOptions->firstWhere('id', (int) $form->procurement_method_id)?->name;
        $documentCount = collect([
            $this->existingDocuments->has('PROPOSAL_PAPER') || is_object($form->proposal_paper),
            $this->existingDocuments->has('PRESENTATION_SLIDE_JTICT') || is_object($form->presentation_slide),
        ])->filter()->count();
        $review = [
            ['label' => 'Nama Projek', 'value' => $form->title ?: '-', 'step' => 1],
            ['label' => 'Kaedah Perolehan', 'value' => collect([$method, filled($form->expected_duration_months) ? $form->expected_duration_months . ' bulan' : null])->filter()->implode(' · ') ?: '-', 'step' => 2],
            ['label' => 'Jumlah Keseluruhan', 'value' => 'RM ' . number_format($summary['total'], 2), 'step' => 3],
            ['label' => 'Dokumen', 'value' => $documentCount . ' daripada 2 dimuat naik', 'step' => 4],
        ];
    @endphp
    <section aria-labelledby="tajuk-semakan" class="border border-slate-200 rounded-xl overflow-hidden">
        <h3 id="tajuk-semakan" class="px-4 py-3 bg-slate-50 border-b border-slate-100 text-[13px] font-extrabold {{ $theme['eyebrow'] }}">Semakan akhir sebelum dihantar</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2">
            @foreach ($review as $row)
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-100">
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold text-slate-500">{{ $row['label'] }}</dt>
                        <dd class="mt-0.5 text-sm font-bold text-slate-900 break-words">{{ $row['value'] }}</dd>
                    </div>
                    <button type="button" wire:click="goToStep({{ $row['step'] }})" class="shrink-0 min-h-9 px-2 rounded-lg text-[13px] font-extrabold hover:bg-slate-50 {{ $theme['link'] }}">Ubah<span class="sr-only"> {{ $row['label'] }}</span></button>
                </div>
            @endforeach
        </dl>
    </section>
</div>
