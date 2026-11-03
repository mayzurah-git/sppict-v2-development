<div class="max-w-5xl mx-auto p-6 space-y-6">
    <!-- Header & Navigasi -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <div class="flex items-center space-x-2">
                <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-md">{{ $application->reference_number }}</span>
                <x-application-status-badge :status="$application->status" />
                <span class="text-xs text-slate-400">• Dihantar pada {{ $application->created_at ? $application->created_at->format('d/m/Y h:i A') : '-' }}</span>
                @if ($application->jtictMeeting)
                    <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md">{{ $application->jtictMeeting->label() }}</span>
                @endif
            </div>
            <h2 class="text-xl font-black text-slate-800 mt-2">{{ $application->title }}</h2>
            {{-- PP3: agensi permohonan dan pengguna yang mendaftar rekod (applicant_id) dipaparkan berasingan --}}
            <dl class="mt-1.5 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                <div class="flex gap-1"><dt class="text-slate-400">Jabatan/Agensi:</dt><dd class="font-semibold text-slate-700">{{ $application->agency ? $application->agency->name . ' (' . $application->agency->code . ')' : '-' }}</dd></div>
                <div class="flex gap-1"><dt class="text-slate-400">Didaftarkan oleh:</dt><dd class="font-semibold text-slate-700">{{ $application->applicant?->name ?? '-' }}</dd></div>
                <div class="flex gap-1"><dt class="text-slate-400">Pegawai Utama:</dt><dd class="font-semibold text-slate-700">{{ $this->officerHistory->whereNull('ended_at')->first(fn ($officer) => $officer->role === \App\Enums\OfficerRole::Primary)?->name ?? '-' }}</dd></div>
            </dl>
        </div>
        <a href="{{ url('/dashboard') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            ← Kembali ke Dashboard
        </a>
    </div>

    <!-- PP2: Semakan Kelengkapan -->
    @if (session()->has('review_message'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold rounded-xl">{{ session('review_message') }}</div>
    @endif
    @if (session()->has('review_error'))
        <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ session('review_error') }}</div>
    @endif

    @can('review', $application)
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-blue-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-slate-800">Semakan Kelengkapan</h3>
                <p class="text-xs text-slate-500 mt-0.5">Teliti maklumat dan dokumen permohonan di bawah sebelum membuat keputusan. "Lengkap" bukan keputusan kelulusan mesyuarat.</p>
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="button" wire:click="openReviewModal('NEEDS_AMENDMENT')" class="px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-800 border border-purple-200 text-xs font-bold rounded-xl transition">Tandakan Tidak Lengkap</button>
                <button type="button" wire:click="openReviewModal('COMPLETE')" class="px-4 py-2 bg-cyan-700 hover:bg-cyan-800 text-white text-xs font-bold rounded-xl transition">Tandakan Lengkap</button>
            </div>
        </div>
    @endcan

    @if ($this->amendmentRemarks)
        <div class="p-4 bg-purple-50 border-l-4 border-purple-500 text-purple-900 text-xs rounded-xl space-y-2">
            <p class="font-bold">Permohonan ini Tidak Lengkap. Ulasan Urus Setia:</p>
            <p class="whitespace-pre-line">{{ $this->amendmentRemarks }}</p>
            @can('update', $application)
                <a href="{{ route('application.edit', $application->uuid) }}" class="inline-flex px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white font-bold rounded-xl transition">Kemas Kini &amp; Hantar Semula</a>
            @endcan
        </div>
    @endif

    <!-- Ringkasan Fasa 1 & 2 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Maklumat Am Projek</h3>
            <div>
                <p class="text-[11px] text-slate-400">Kategori Permohonan</p>
                <p class="text-xs font-bold text-slate-800">{{ $application->applicationCategoryMaster?->name ?? $application->application_category ?? '-' }}</p>
            </div>
            <div>
                <p class="text-[11px] text-slate-400">Ringkasan Projek</p>
                <p class="text-xs text-slate-700 leading-relaxed mt-1">{{ $application->description }}</p>
            </div>
            <div>
                <p class="text-[11px] text-slate-400">Objektif Utama</p>
                <p class="text-xs text-slate-700 leading-relaxed mt-1">{{ $application->objectives }}</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Maklumat Perolehan & Kos</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[11px] text-slate-400">Jenis Perolehan</p>
                    <p class="text-xs font-bold text-slate-800">{{ $application->procurementType?->name ?? $application->procurement_type ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Kaedah Perolehan</p>
                    <p class="text-xs font-bold text-slate-800">{{ $application->procurementMethod?->name ?? $application->procurement_method ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Anggaran Kos Siling</p>
                    <p class="text-xs font-bold text-slate-800">RM {{ number_format($application->ceiling_cost ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Anggaran Kos Projek</p>
                    <p class="text-xs font-bold text-slate-800">RM {{ number_format($application->estimated_cost, 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Jumlah Keseluruhan (termasuk SST)</p>
                    <p class="text-sm font-black text-emerald-600">RM {{ number_format($application->total_cost ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Tempoh Pelaksanaan</p>
                    <p class="text-xs font-bold text-slate-800">{{ $application->expected_duration_months }} Bulan</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Perincian Item Projek (Fasa 3) -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Perincian Item Perolehan</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold">
                    <tr>
                        <th class="p-2.5">Kategori</th>
                        <th class="p-2.5">Spesifikasi Teknikal</th>
                        <th class="p-2.5 text-center">Kuantiti</th>
                        <th class="p-2.5 text-right">Kos Seunit (RM)</th>
                        <th class="p-2.5 text-right">Jumlah (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($application->details as $detail)
                        <tr>
                            <td class="p-2.5 font-semibold text-slate-700">{{ $detail->projectCategory?->name ?? $detail->item_category }}</td>
                            <td class="p-2.5 text-slate-600">{{ $detail->technical_specifications }}</td>
                            <td class="p-2.5 text-center font-bold">{{ $detail->unit_quantity }}</td>
                            <td class="p-2.5 text-right">{{ number_format($detail->unit_cost, 2) }}</td>
                            <td class="p-2.5 text-right font-bold text-slate-800">{{ number_format($detail->total_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-200 text-slate-700">
                    <tr>
                        <td colspan="4" class="p-2.5 text-right">Jumlah Kecil</td>
                        <td class="p-2.5 text-right font-bold">{{ number_format($application->subtotal_cost ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="p-2.5 text-right">SST ({{ rtrim(rtrim(number_format($application->sst_rate ?? 0, 2), '0'), '.') }}%)</td>
                        <td class="p-2.5 text-right font-bold">{{ number_format($application->sst_amount ?? 0, 2) }}</td>
                    </tr>
                    <tr class="bg-slate-50">
                        <td colspan="4" class="p-2.5 text-right font-bold text-slate-900">Jumlah Keseluruhan</td>
                        <td class="p-2.5 text-right font-black text-slate-900">{{ number_format($application->total_cost ?? 0, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Dokumen Sokongan & Pegawai (Fasa 4 & 5) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Dokumen Sokongan</h3>
            @forelse ($application->documents as $doc)
                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between text-xs">
                    <div>
                        <p class="font-bold text-slate-800">{{ $doc->document_type === 'PROPOSAL_PAPER' ? 'Kertas Cadangan' : 'Slaid Pembentangan' }}</p>
                        <p class="text-[10px] text-slate-400">{{ $doc->file_name }} ({{ number_format($doc->file_size_kb) }} KB)</p>
                    </div>
                    <a href="{{ route('application.document', [$application->uuid, $doc->uuid]) }}" target="_blank" class="px-3 py-1 bg-blue-50 text-blue-600 font-bold rounded-lg hover:bg-blue-100 transition">
                        {{ $doc->mime_type === 'application/pdf' ? '📄 Buka PDF' : '📥 Muat Turun Word' }}
                    </a>
                </div>
            @empty
                <p class="text-xs text-slate-400">Tiada dokumen dimuat naik.</p>
            @endforelse
        </div>

        @php($activeOfficers = $this->officerHistory->whereNull('ended_at')->keyBy(fn ($officer) => $officer->role->value))
        @php($canManageOfficers = auth()->user()->can('manageOfficers', $application))
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Pegawai Bertanggungjawab</h3>
            @foreach (\App\Enums\OfficerRole::cases() as $role)
                @php($officer = $activeOfficers->get($role->value))
                @continue(! $officer && $role === \App\Enums\OfficerRole::Secondary && ! $canManageOfficers)
                <div class="text-xs space-y-1 {{ $loop->first ? '' : 'border-t border-slate-100 pt-3' }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] text-slate-400">{{ $role->label() }}</p>
                        @if ($canManageOfficers)
                            <button type="button" wire:click="openHandoverModal('{{ $role->value }}')" class="px-2.5 py-1 bg-amber-50 text-amber-700 font-bold rounded-lg hover:bg-amber-100 transition text-[11px]">
                                {{ $officer ? '⇄ Kemaskini Pegawai' : '+ Tambah Pegawai' }}
                            </button>
                        @endif
                    </div>
                    @if ($officer)
                        <p class="font-bold text-slate-800">{{ $officer->name }}</p>
                        <p class="text-slate-500">{{ $officer->position }}{{ $officer->grade ? " ({$officer->grade})" : '' }}</p>
                        <p class="text-slate-500">📧 {{ $officer->email ?: '-' }} | 📞 {{ $officer->phone ?: '-' }}</p>
                        @if ($officer->started_at)
                            <p class="text-[10px] text-slate-400">Bertanggungjawab sejak {{ $officer->started_at->format('d/m/Y') }}</p>
                        @endif
                    @else
                        <p class="text-slate-400 italic">Tiada pegawai dilantik.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold rounded-xl">{{ session('message') }}</div>
    @endif

    <!-- Sejarah Pegawai Bertanggungjawab -->
    @if ($this->officerHistory->whereNotNull('ended_at')->isNotEmpty())
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Sejarah Pegawai Bertanggungjawab</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold">
                        <tr>
                            <th class="p-2.5">Pegawai</th>
                            <th class="p-2.5">Peranan</th>
                            <th class="p-2.5">Tempoh</th>
                            <th class="p-2.5">Sebab / Catatan Serahan</th>
                            <th class="p-2.5">Direkod Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->officerHistory as $officer)
                            <tr class="{{ $officer->isActive() ? 'bg-emerald-50/40' : '' }}">
                                <td class="p-2.5">
                                    <p class="font-semibold text-slate-800">{{ $officer->name }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $officer->position }}{{ $officer->grade ? " ({$officer->grade})" : '' }}</p>
                                </td>
                                <td class="p-2.5 text-slate-600">{{ $officer->role->label() }}</td>
                                <td class="p-2.5 text-slate-600 whitespace-nowrap">
                                    {{ $officer->started_at?->format('d/m/Y') ?? '-' }} –
                                    @if ($officer->isActive())
                                        <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-700 font-bold rounded text-[10px]">Aktif</span>
                                    @else
                                        {{ $officer->ended_at->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td class="p-2.5 text-slate-600">
                                    <p>{{ $officer->end_reason?->label() ?? '-' }}</p>
                                    @if ($officer->handover_remarks)
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $officer->handover_remarks }}</p>
                                    @endif
                                </td>
                                <td class="p-2.5 text-[10px] text-slate-500">
                                    <p>Lantik: {{ $officer->assigner?->name ?? '-' }}</p>
                                    @unless ($officer->isActive())
                                        <p>Tamat: {{ $officer->ender?->name ?? '-' }}</p>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- PP2: Sejarah Semakan (terkini dahulu). Nama individu hanya dipaparkan kepada Urus Setia. -->
    @if ($this->reviews->isNotEmpty())
        @php($showActorNames = auth()->user()->hasRole('pentadbir_urus_setia'))
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Sejarah Semakan</h3>
            <ol class="space-y-3">
                @foreach ($this->reviews as $review)
                    <li wire:key="review-{{ $review->id }}" class="text-xs p-3 rounded-xl {{ $loop->first ? 'bg-slate-50 border border-slate-200' : '' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-800">{{ $review->actionLabel() }}</span>
                            @if ($loop->first)
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-800 font-bold rounded-full text-[10px]">Terkini</span>
                            @endif
                            <span class="text-slate-400">{{ $review->created_at->format('d/m/Y h:i A') }}</span>
                            <span class="text-slate-500">• {{ $review->actorRoleLabel() }}{{ $showActorNames && $review->user ? ': ' . $review->user->name : '' }}</span>
                        </div>
                        @if ($review->remarks)
                            <p class="mt-1 text-slate-700 whitespace-pre-line">{{ $review->remarks }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    <!-- PP2: Modal Pengesahan Semakan -->
    @if ($showReviewModal)
        @php($isIncomplete = $reviewDecision === \App\Enums\ApplicationStatus::NeedsAmendment->value)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">{{ $isIncomplete ? 'Tandakan Tidak Lengkap' : 'Tandakan Lengkap' }}</h3>
                <p class="text-xs text-slate-600">
                    {{ $application->reference_number }} — {{ $application->title }}
                </p>

                <div>
                    <label for="review-remarks" class="block text-xs font-medium text-gray-700">
                        Ulasan Semakan @if ($isIncomplete)<span class="text-red-500">*</span>@else<span class="text-slate-400">(pilihan)</span>@endif
                    </label>
                    <textarea id="review-remarks" wire:model="reviewRemarks" rows="5" maxlength="{{ \App\Services\ApplicationReviewService::REMARKS_MAX }}" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="{{ $isIncomplete ? 'Nyatakan maklumat, dokumen atau bahagian yang perlu dipinda atau ditambah baik.' : 'Catatan tambahan (jika ada).' }}"></textarea>
                    @error('reviewRemarks') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>

                <p class="text-[11px] text-slate-500">
                    @if ($isIncomplete)
                        Permohonan akan dikembalikan kepada pemohon untuk pindaan. Pemohon dan Pegawai Utama akan dimaklumkan melalui e-mel. Keputusan ini direkodkan dalam sejarah semakan dan tidak boleh diubah.
                    @else
                        Permohonan akan ditetapkan sebagai Lengkap dan menunggu proses Pra JTICT. Ini bukan keputusan kelulusan. Keputusan ini direkodkan dalam sejarah semakan dan tidak boleh diubah.
                    @endif
                </p>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="closeReviewModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="submitReview" wire:loading.attr="disabled" wire:target="submitReview" class="px-4 py-2 {{ $isIncomplete ? 'bg-purple-700 hover:bg-purple-800' : 'bg-cyan-700 hover:bg-cyan-800' }} text-white text-xs font-bold rounded-xl transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="submitReview">Sahkan Keputusan</span>
                        <span wire:loading wire:target="submitReview">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Tukar Pegawai Bertanggungjawab -->
    @if ($showHandoverModal)
        @php($handoverRoleEnum = \App\Enums\OfficerRole::from($handoverRole))
        @php($currentOfficer = $activeOfficers->get($handoverRole))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">
                    {{ $currentOfficer ? 'Kemaskini' : 'Tambah' }} {{ $handoverRoleEnum->label() }}
                </h3>

                @if ($currentOfficer)
                    <div class="p-3 bg-slate-50 rounded-xl text-xs">
                        <p class="text-[11px] text-slate-400">Pegawai semasa</p>
                        <p class="font-bold text-slate-800">{{ $currentOfficer->name }}</p>
                        <p class="text-slate-500">{{ $currentOfficer->position }}</p>
                    </div>
                @endif

                <x-form.select label="Pegawai Baharu" wire:model="newOfficerId" :error="$errors->first('newOfficerId')" :required="$handoverRoleEnum === \App\Enums\OfficerRole::Primary">
                    <option value="">
                        {{ $handoverRoleEnum === \App\Enums\OfficerRole::Secondary && $currentOfficer ? '-- Tiada (tamatkan tugasan Pegawai Kedua) --' : '-- Pilih pegawai berdaftar dalam agensi --' }}
                    </option>
                    @foreach ($this->officerCandidates as $candidate)
                        @continue($currentOfficer && (int) $currentOfficer->user_id === $candidate->id)
                        <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                    @endforeach
                </x-form.select>

                <x-form.input label="Tarikh Berkuat Kuasa" type="date" wire:model="effectiveDate" max="{{ today()->toDateString() }}" :error="$errors->first('effectiveDate')" required />

                @if ($currentOfficer)
                    <x-form.select label="Sebab Pertukaran" wire:model="endReason" :error="$errors->first('endReason')" required>
                        @foreach (\App\Enums\OfficerEndReason::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-form.select>

                    <div>
                        <label class="block text-xs font-medium text-gray-700">Catatan Serahan Tugas</label>
                        <textarea wire:model="handoverRemarks" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Contoh: Status semasa projek, dokumen yang telah diserahkan, isu tertunggak."></textarea>
                        @error('handoverRemarks') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                @endif

                <p class="text-[11px] text-slate-500">Pertukaran ini tidak memerlukan kelulusan. Urus Setia, pegawai baharu dan pegawai terdahulu akan dimaklumkan melalui e-mel. Rekod pegawai terdahulu kekal dalam sejarah projek.</p>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="closeHandoverModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="reassignOfficer" wire:loading.attr="disabled" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition">Simpan Pertukaran</button>
                </div>
            </div>
        </div>
    @endif
</div>