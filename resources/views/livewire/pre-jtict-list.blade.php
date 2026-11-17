<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Keputusan Pra JTICT</h2>
            <p class="text-xs text-slate-500 mt-0.5">Permohonan yang telah disahkan Lengkap melalui semakan kelengkapan dan menunggu keputusan Pra JTICT.</p>
            <p class="text-xs text-slate-500 mt-0.5">Keputusan Diterima hanya dibenarkan jika Mesyuarat JTICT permohonan Aktif dan belum berlalu. Jika tidak, gunakan Ditolak dengan Pindaan (pemohon menghantar semula ke mesyuarat yang dibuka) atau Ditolak.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">← Kembali ke Dashboard</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <label for="carian-pra-jtict" class="sr-only">Cari permohonan</label>
            <input id="carian-pra-jtict" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari No. Rujukan / Nama Projek…"
                class="w-full md:max-w-sm text-xs border-slate-200 rounded-xl shadow-sm focus:border-green-700 focus:ring-green-700/20">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                    <tr>
                        <th scope="col" class="p-3">No. Rujukan</th>
                        <th scope="col" class="p-3">Nama Projek</th>
                        <th scope="col" class="p-3">Jabatan/Agensi</th>
                        <th scope="col" class="p-3">Tarikh Permohonan</th>
                        <th scope="col" class="p-3">Pemohon</th>
                        <th scope="col" class="p-3">Status</th>
                        <th scope="col" class="p-3">Mesyuarat JTICT</th>
                        <th scope="col" class="p-3"><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($applications as $app)
                        <tr wire:key="pre-jtict-{{ $app->id }}" class="hover:bg-slate-50 transition">
                            <td class="p-3 font-bold whitespace-nowrap tabular-nums">{{ $app->reference_number }}</td>
                            <td class="p-3 font-semibold text-slate-900 max-w-xs">
                                <span class="block break-words">{{ $app->title }}</span>
                                {{-- Kitaran pindaan: keputusan terdahulu Ditolak dengan Pindaan --}}
                                @if ($app->latestPreJtictDecision)
                                    <span class="block mt-1 text-[11px] font-semibold text-orange-700">Dihantar semula selepas Pindaan Pra JTICT</span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-600" title="{{ $app->agency?->name }}">{{ $app->agency?->code ?? '-' }}</td>
                            <td class="p-3 whitespace-nowrap tabular-nums">
                                {{ $app->created_at?->format('d/m/Y') ?? '-' }}
                                @if ($app->latestCompleteReview)
                                    <span class="block text-[11px] text-slate-500">Lengkap: {{ $app->latestCompleteReview->created_at->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-600">{{ $app->applicant?->name ?? '-' }}</td>
                            <td class="p-3"><x-application-status-badge :status="$app->status" :label="$app->statusDisplayLabel()" /></td>
                            <td class="p-3 text-slate-600">
                                {{ $app->jtictMeeting?->label() ?? '-' }}
                                {{-- PP4.3A (PP42B-01): Diterima hanya bagi mesyuarat Aktif yang belum berlalu --}}
                                @if ($blocker = $app->preJtictAcceptanceBlocker())
                                    <span class="block mt-1 text-[11px] font-semibold text-amber-800">Mesyuarat tidak sah untuk Diterima: {{ $blocker }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="{{ route('application.view', $app->uuid) }}" class="inline-flex items-center min-h-8 text-[13px] font-bold text-green-800 hover:underline">Semak &amp; Putuskan</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-sm text-slate-500">Tiada permohonan yang menunggu keputusan Pra JTICT.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($applications->hasPages())
            <div class="p-4 border-t border-slate-100">{{ $applications->links() }}</div>
        @endif
    </div>

    {{-- Rekod lama / tidak konsisten: tidak diproses secara automatik; perlu keputusan pentadbir sistem. --}}
    @if ($manual->isNotEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 space-y-3">
            <div>
                <h3 class="text-sm font-bold text-amber-900">Perlu Semakan Manual ({{ $manual->count() }})</h3>
                <p class="text-xs text-amber-800 mt-0.5">Permohonan Lengkap ini tiada Mesyuarat JTICT, tiada rekod semakan kelengkapan PP2, atau telah mempunyai keputusan Pra JTICT. Keputusan Pra JTICT tidak boleh direkodkan melalui sistem sehingga rekod disemak oleh pentadbir sistem.</p>
            </div>
            <ul class="divide-y divide-amber-200 text-xs">
                @foreach ($manual as $app)
                    <li wire:key="manual-{{ $app->id }}" class="py-2 flex flex-wrap justify-between gap-2">
                        <span><strong>{{ $app->reference_number }}</strong> — {{ $app->title }} · {{ $app->agency?->code ?? '-' }}</span>
                        <a href="{{ route('application.view', $app->uuid) }}" class="font-bold text-amber-900 hover:underline">Papar</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
