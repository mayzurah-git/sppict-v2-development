<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Keputusan Mesyuarat JTICT</h2>
            <p class="text-xs text-slate-500 mt-0.5">Permohonan yang telah Diterima pada peringkat Pra JTICT dan dibawa ke Mesyuarat JTICT.</p>
            <p class="text-xs text-slate-500 mt-0.5">Keputusan hanya boleh direkodkan selepas tarikh mesyuarat. Rekod keputusan selepas mesyuarat benar-benar bersidang.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">← Kembali ke Dashboard</a>
    </div>

    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col md:flex-row gap-3">
        <label for="carian-jtict" class="sr-only">Cari permohonan</label>
        <input id="carian-jtict" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari No. Rujukan / Nama Projek…"
            class="w-full md:max-w-sm text-xs border-slate-200 rounded-xl shadow-sm focus:border-indigo-700 focus:ring-indigo-700/20">
        <label for="penapis-mesyuarat" class="sr-only">Mesyuarat JTICT</label>
        <select id="penapis-mesyuarat" wire:model.live="meeting" class="w-full md:max-w-xs text-xs border-slate-200 rounded-xl shadow-sm focus:border-indigo-700 focus:ring-indigo-700/20">
            <option value="">Semua Mesyuarat JTICT</option>
            @foreach ($meetings as $option)
                <option value="{{ $option->id }}">{{ $option->label() }} ({{ $option->meeting_date->format('d/m/Y') }})</option>
            @endforeach
        </select>
    </div>

    @foreach ([
        ['key' => 'sedia', 'title' => 'Sedia Diputuskan', 'rows' => $ready, 'tone' => 'border-indigo-200', 'empty' => 'Tiada permohonan yang sedia diputuskan.',
            'desc' => 'Mesyuarat telah berlangsung dan semua syarat keputusan dipenuhi.'],
        ['key' => 'menunggu', 'title' => 'Menunggu Mesyuarat', 'rows' => $scheduled, 'tone' => 'border-slate-100', 'empty' => 'Tiada permohonan yang menunggu mesyuarat.',
            'desc' => 'Mesyuarat telah dijadualkan tetapi belum berlangsung. Keputusan belum boleh direkodkan.'],
        ['key' => 'manual', 'title' => 'Perlu Semakan Manual', 'rows' => $manual, 'tone' => 'border-amber-200', 'empty' => 'Tiada permohonan yang perlu semakan manual.',
            'desc' => 'Rekod mesyuarat atau keputusan Pra JTICT tidak selaras. Keputusan tidak boleh direkodkan melalui sistem sehingga rekod disemak melalui proses rasmi.'],
    ] as $section)
        <section aria-labelledby="bahagian-{{ $section['key'] }}" class="bg-white rounded-2xl shadow-sm border {{ $section['tone'] }} overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 id="bahagian-{{ $section['key'] }}" class="text-sm font-bold text-slate-800">{{ $section['title'] }} ({{ $section['rows']->count() }})</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ $section['desc'] }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                        <tr>
                            <th scope="col" class="p-3">No. Rujukan</th>
                            <th scope="col" class="p-3">Nama Projek</th>
                            <th scope="col" class="p-3">Jabatan/Agensi</th>
                            <th scope="col" class="p-3">Pra JTICT Diterima</th>
                            <th scope="col" class="p-3">Mesyuarat JTICT</th>
                            <th scope="col" class="p-3"><span class="sr-only">Tindakan</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($section['rows'] as $app)
                            <tr wire:key="jtict-{{ $section['key'] }}-{{ $app->id }}" class="hover:bg-slate-50 transition">
                                <td class="p-3 font-bold whitespace-nowrap tabular-nums">{{ $app->reference_number }}</td>
                                <td class="p-3 font-semibold text-slate-900 max-w-xs">
                                    <span class="block break-words">{{ $app->title }}</span>
                                    @if ($section['key'] === 'manual')
                                        <span class="block mt-1 text-[11px] font-semibold text-amber-800">{{ $app->jtictMeetingIssue() }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-slate-600" title="{{ $app->agency?->name }}">{{ $app->agency?->code ?? '-' }}</td>
                                <td class="p-3 whitespace-nowrap tabular-nums">
                                    @if ($app->latestPreJtictDecision?->decision === \App\Enums\ApplicationDecision::Accepted)
                                        {{ $app->latestPreJtictDecision->decision_date->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="p-3 text-slate-600">
                                    {{ $app->jtictMeeting?->label() ?? '-' }}
                                    @if ($app->jtictMeeting)
                                        <span class="block text-[11px] text-slate-500 tabular-nums">Tarikh: {{ $app->jtictMeeting->meeting_date->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    <a href="{{ route('application.view', $app->uuid) }}" class="inline-flex items-center min-h-8 text-[13px] font-bold text-indigo-800 hover:underline">
                                        {{ $section['key'] === 'sedia' ? 'Semak & Rekod Keputusan' : 'Papar' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-sm text-slate-500">{{ $section['empty'] }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
