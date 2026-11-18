{{-- Borang Permohonan Projek (5 fasa). Warna ikut peranan (App\Support\RoleTheme). --}}
@php
    $status = $savedApplication?->statusEnum();
    $isUrusSetia = auth()->user()->hasRole('pentadbir_urus_setia');
    $summary = $form->costSummary();
    $overCeiling = $summary['ceiling_balance'] < 0;
    $sstRateLabel = rtrim(rtrim(number_format($summary['sst_rate'], 2), '0'), '.');
    $usage = (float) $summary['ceiling_usage_percent'];
    $formReady = ! ($this->isOnBehalf && ! $confirmedAgencyId);
@endphp

<div class="min-h-screen {{ $theme['canvas'] }}">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- Kepala halaman -->
    <section class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div class="flex flex-col gap-1.5 min-w-0">
            <nav aria-label="Laluan" class="text-[13px] text-slate-500">
                <a href="{{ auth()->user()->hasRole('pengguna_biasa') ? route('applications.index') : route('dashboard') }}" class="font-semibold hover:underline {{ $theme['link'] }}">{{ auth()->user()->hasRole('pengguna_biasa') ? 'Senarai Permohonan' : 'Papan Pemuka' }}</a>
                <span aria-hidden="true">/</span>
                {{ $savedApplication ? 'Kemaskini Permohonan' : 'Permohonan Baharu' }}
            </nav>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">{{ $this->isOnBehalf ? 'Permohonan bagi Pihak Agensi' : 'Permohonan Projek ICT' }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-[13px]">
                @if ($status)
                    <x-application-status-badge :status="$status" />
                @else
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Draf</span>
                @endif
                @if ($savedApplication?->reference_number)
                    <span class="font-bold tabular-nums text-slate-700">{{ $savedApplication->reference_number }}</span>
                @endif
            </div>
        </div>

        @if ($formReady)
            @if ($this->targetMeeting)
                <div class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-green-50 border border-green-200 text-[13px] text-green-900">
                    <svg aria-hidden="true" class="w-[18px] h-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg>
                    <span><strong>Permohonan dibuka untuk {{ $this->targetMeeting->label() }}</strong> · tutup {{ $this->targetMeeting->submission_closes_at->format('d/m/Y') }}</span>
                </div>
            @elseif (! $this->requiresMeetingSelection)
                <div class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-[13px] text-amber-900">
                    <svg aria-hidden="true" class="w-[18px] h-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M10 9v6M14 9v6" /></svg>
                    <span><strong>Tiada Mesyuarat JTICT yang dibuka buat masa ini.</strong> Permohonan boleh disimpan dan dihantar apabila tempoh dibuka.</span>
                </div>
            @endif
        @endif
    </section>

    <!-- Mesej -->
    @if (session()->has('message'))
        <div role="status" class="px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-sm font-semibold text-green-800">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div role="alert" class="px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm font-semibold text-red-800">{{ session('error') }}</div>
    @endif

    {{-- PP2: ulasan Urus Setia bagi permohonan Tidak Lengkap (baca sahaja; tidak boleh diubah oleh pemohon) --}}
    @if ($this->isAmendment)
        <div class="px-4 py-3.5 rounded-xl bg-purple-50 border border-purple-200 text-sm text-purple-900 space-y-1">
            <p class="font-bold">Permohonan ini Tidak Lengkap — sila buat pembetulan dan hantar semula.</p>
            @if ($this->amendmentRemarks)
                <p class="font-semibold">Ulasan Urus Setia{{ $this->amendmentReview ? ' (' . $this->amendmentReview->created_at->format('d/m/Y h:i A') . ')' : '' }}:</p>
                <p class="whitespace-pre-line">{{ $this->amendmentRemarks }}</p>
            @endif
        </div>
    @endif

    {{-- PP4.3C: Draf salinan daripada permohonan Kaji Semula --}}
    @if ($this->copySource)
        <div class="px-4 py-3.5 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900 space-y-1">
            <p class="font-bold">Permohonan ini disalin daripada {{ $this->copySource->reference_number }} (keputusan JTICT Kaji Semula).</p>
            <p>Semak dan kemas kini maklumat Fasa 1 hingga 3. Pilih semula pilihan yang kosong (pilihan tidak aktif tidak disalin), muat naik semula dokumen Fasa 4, dan lantik Pegawai Bertanggungjawab sebelum menghantar.</p>
        </div>
    @endif

    {{-- PP4.2: ulasan keputusan Pra JTICT "Ditolak dengan Pindaan" (baca sahaja) --}}
    @if ($this->preJtictAmendmentDecision)
        <div class="px-4 py-3.5 rounded-xl bg-orange-50 border border-orange-200 text-sm text-orange-900 space-y-1">
            <p class="font-bold">Keputusan Pra JTICT: Ditolak dengan Pindaan — sila buat pindaan dan hantar semula.</p>
            <p class="font-semibold">Ulasan Urus Setia ({{ $this->preJtictAmendmentDecision->decision_date->format('d/m/Y') }}):</p>
            <p class="whitespace-pre-line">{{ $this->preJtictAmendmentDecision->remarks }}</p>
        </div>
    @endif

    @error('form.agency')
        <div role="alert" class="px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm font-semibold text-red-800">{{ $message }}</div>
    @enderror

    {{-- PP3: Urus Setia memilih & mengesahkan agensi sasaran sebelum mengisi borang --}}
    @if (! $formReady)
        <section class="max-w-3xl bg-white border border-slate-200 rounded-2xl p-6 space-y-4" wire:key="target-agency-picker">
            <div>
                <h2 class="text-lg font-extrabold text-slate-900">Permohonan bagi Pihak Agensi</h2>
                <p class="text-sm text-slate-600 mt-1">Pilih Jabatan/Agensi sasaran. Permohonan akan didaftarkan di bawah agensi ini dan Pegawai Bertanggungjawab dipilih daripada pegawai agensi tersebut. Agensi tidak boleh ditukar selepas permohonan disimpan.</p>
            </div>
            <x-form.select size="lg" :focus="$theme['input']" label="Jabatan/Agensi Sasaran" wire:model="targetAgencyId" :error="$errors->first('targetAgencyId')" required>
                <option value="">-- Pilih Jabatan/Agensi --</option>
                @foreach ($this->agencyOptions as $agency)
                    <option value="{{ $agency->id }}">{{ $agency->name }} ({{ $agency->code }})</option>
                @endforeach
            </x-form.select>
            <div class="flex justify-end">
                <button type="button" wire:click="confirmAgency" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 min-h-11 px-5 rounded-xl text-sm font-extrabold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $theme['button'] }}">
                    Sahkan Agensi &amp; Teruskan
                    <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </button>
            </div>
        </section>
    @else
        @if ($this->applicationAgency)
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-[13px]" wire:key="application-agency">
                <p class="inline-flex flex-wrap items-center gap-x-1.5 text-slate-700">
                    <svg aria-hidden="true" class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6" /></svg>
                    <span class="font-semibold text-slate-500">{{ $this->isOnBehalf ? 'Permohonan bagi pihak:' : 'Jabatan/Agensi Permohonan:' }}</span>
                    <span class="font-bold text-slate-900">{{ $this->applicationAgency->name }} ({{ $this->applicationAgency->code }})</span>
                </p>
                @if ($this->isOnBehalf)
                    <button type="button" wire:click="changeAgency" wire:confirm="Tukar agensi sasaran? Pilihan Pegawai Bertanggungjawab akan dikosongkan." class="font-bold hover:underline {{ $theme['link'] }}">Tukar Agensi</button>
                @endif
            </div>
        @endif
        @error('targetAgencyId')
            <div role="alert" class="px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm font-semibold text-red-800">{{ $message }}</div>
        @enderror

        <!-- Penunjuk fasa -->
        <nav aria-label="Fasa permohonan" class="bg-white border border-slate-200 rounded-2xl px-4 py-3.5">
            <ol class="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-2">
                @foreach ($steps as $n => $step)
                    @php($current = $n === $currentStep)
                    @php($done = $n < $currentStep)
                    <li>
                        <button type="button" wire:click="goToStep({{ $n }})" @if ($current) aria-current="step" @endif
                            @class(['w-full min-h-[52px] flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-left transition focus:outline-none focus-visible:ring-2', $theme['soft'] => $current, 'hover:bg-slate-50' => ! $current])>
                            <span @class([
                                'w-8 h-8 shrink-0 rounded-full flex items-center justify-center text-sm font-extrabold',
                                $theme['button'] => $current,
                                'bg-green-100 text-green-700' => $done,
                                'bg-white border-2 border-slate-300 text-slate-500' => ! $current && ! $done,
                            ])>
                                @if ($done)
                                    <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10" /></svg>
                                    <span class="sr-only">Selesai:</span>
                                @else
                                    {{ $n }}
                                @endif
                            </span>
                            <span @class(['text-sm font-extrabold', $theme['eyebrow'] => $current, 'text-slate-900' => ! $current])>{{ $step['label'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
            <div aria-hidden="true" class="mt-3 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-300 {{ $theme['bar'] }}" style="width: {{ ($currentStep / 5) * 100 }}%"></div>
            </div>
        </nav>

        <div class="flex flex-wrap items-start gap-5">
            <!-- Borang fasa semasa -->
            <section aria-labelledby="tajuk-fasa" class="flex-[999_1_640px] min-w-0 bg-white border border-slate-200 rounded-2xl">
                <header class="px-6 pt-5 pb-4 border-b border-slate-100 flex items-start gap-3.5">
                    <span aria-hidden="true" class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center text-base font-black {{ $theme['soft'] }}">{{ $currentStep }}</span>
                    <div>
                        <h2 id="tajuk-fasa" class="text-lg font-extrabold text-slate-900">{{ $steps[$currentStep]['title'] }}</h2>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $steps[$currentStep]['desc'] }}</p>
                    </div>
                </header>

                <div class="px-6 py-6 min-h-[320px]" wire:key="application-step-{{ $currentStep }}">
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

                <!-- Bar tindakan -->
                <footer class="px-6 py-3.5 bg-slate-50/80 border-t border-slate-100 rounded-b-2xl flex flex-wrap items-center gap-2.5">
                    @if ($currentStep > 1)
                        <button type="button" wire:click="previousStep" class="inline-flex items-center gap-1.5 min-h-11 px-4 rounded-xl border border-slate-300 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                            <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6" /></svg>
                            Kembali
                        </button>
                    @endif
                    <span class="flex-1"></span>

                    <!-- Simpan (draf) — sentiasa ada di setiap fasa -->
                    <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 min-h-11 px-4 rounded-xl border bg-white text-sm font-extrabold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 {{ $theme['buttonOutline'] }}">
                        <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h11l3 3v15H5zM8 3v5h7M8 21v-7h8v7" /></svg>
                        <span wire:loading.remove wire:target="saveDraft">Simpan</span>
                        <span wire:loading wire:target="saveDraft">Menyimpan...</span>
                    </button>

                    @if ($currentStep < 5)
                        <button type="button" wire:click="nextStep" class="inline-flex items-center gap-1.5 min-h-11 px-5 rounded-xl text-sm font-extrabold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $theme['button'] }}">
                            Seterusnya: {{ $steps[$currentStep + 1]['label'] }}
                            <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </button>
                    @else
                        <button type="button" wire:click="submitApplication" wire:loading.attr="disabled" @disabled(! $this->targetMeeting)
                                title="{{ $this->targetMeeting ? 'Hantar ke ' . $this->targetMeeting->label() : 'Tiada Mesyuarat JTICT yang dibuka. Sila simpan permohonan.' }}"
                                class="inline-flex items-center gap-1.5 min-h-11 px-5 rounded-xl bg-green-700 hover:bg-green-800 text-white text-sm font-extrabold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-green-700">
                            <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4Z" /></svg>
                            <span wire:loading.remove wire:target="submitApplication">Hantar Permohonan</span>
                            <span wire:loading wire:target="submitApplication">Sedang Memproses...</span>
                        </button>
                    @endif
                </footer>
            </section>

            <!-- Ringkasan kos -->
            <aside aria-labelledby="tajuk-kos" class="flex-[1_1_300px] min-w-0 flex flex-col gap-4">
                <section class="bg-white border border-slate-200 rounded-2xl px-5 py-[18px] flex flex-col gap-3.5">
                    <h2 id="tajuk-kos" class="text-base font-extrabold text-slate-900">Ringkasan Kos Projek</h2>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-600">Jumlah Kecil (sebelum SST)</dt><dd class="font-bold tabular-nums">RM {{ number_format($summary['subtotal'], 2) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-600">SST ({{ $sstRateLabel }}%)</dt><dd class="font-bold tabular-nums">RM {{ number_format($summary['sst_amount'], 2) }}</dd></div>
                        <div class="flex justify-between gap-3 pt-2 border-t border-slate-100"><dt class="font-extrabold">Jumlah Keseluruhan</dt><dd @class(['text-[17px] font-black tabular-nums', 'text-red-700' => $overCeiling])>RM {{ number_format($summary['total'], 2) }}</dd></div>
                    </dl>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex justify-between text-xs text-slate-600"><span>Penggunaan Kos Siling</span><span @class(['font-extrabold', 'text-red-700' => $overCeiling])>{{ number_format($usage, 1) }}%</span></div>
                        <div aria-hidden="true" class="h-2 rounded-full bg-slate-200 overflow-hidden">
                            <div @class(['h-full rounded-full', 'bg-red-600' => $overCeiling, 'bg-amber-500' => ! $overCeiling && $usage >= 90, $theme['bar'] => ! $overCeiling && $usage < 90]) style="width: {{ min(100, $usage) }}%"></div>
                        </div>
                        <div class="flex flex-wrap justify-between gap-x-3 text-[13px]">
                            <span class="text-slate-600">Kos Siling RM {{ number_format($summary['ceiling'], 2) }}</span>
                            <span @class(['font-extrabold', 'text-red-700' => $overCeiling, 'text-green-700' => ! $overCeiling])>Baki {{ $overCeiling ? '-' : '' }}RM {{ number_format(abs($summary['ceiling_balance']), 2) }}</span>
                        </div>
                    </div>
                    @if ($overCeiling)
                        <p role="alert" class="px-3 py-2.5 rounded-xl bg-red-50 border border-red-200 text-[13px] text-red-800">Jumlah Keseluruhan melebihi Anggaran Kos Siling sebanyak <strong>RM {{ number_format(abs($summary['ceiling_balance']), 2) }}</strong>. Sila kurangkan kuantiti atau kos seunit.</p>
                    @endif
                    @if (abs($summary['estimated_difference']) >= 0.01 && $summary['subtotal'] > 0)
                        <p class="text-xs text-slate-500">
                            Jumlah Keseluruhan {{ $summary['estimated_difference'] > 0 ? 'melebihi' : 'kurang daripada' }} Anggaran Kos Projek (RM {{ number_format($summary['estimated'], 2) }})
                            sebanyak RM {{ number_format(abs($summary['estimated_difference']), 2) }}.
                        </p>
                    @endif
                </section>
                <section class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex flex-col gap-1.5 text-[13px] text-slate-600">
                    <strong class="text-sm text-slate-900">Perlu bantuan?</strong>
                    <span>Sila hubungi Urus Setia jika terdapat pertanyaan tentang permohonan.</span>
                </section>
            </aside>
        </div>
    @endif

</div>
</div>
