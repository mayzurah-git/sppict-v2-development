{{-- Papan Pemuka Urus Setia (pentadbir_urus_setia). --}}
@php
    $user = auth()->user();

    $statusCounts = \App\Models\Application::query()
        ->whereIn('status', [\App\Enums\ApplicationStatus::Submitted->value, \App\Enums\ApplicationStatus::InJtict->value])
        ->selectRaw('status, count(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    $jtict = \App\Models\Meeting::currentJtict()?->load(['meetingName', 'meetingNumber', 'meetingYear']);
    $daysLeft = $jtict?->submission_closes_at ? (int) today()->diffInDays($jtict->submission_closes_at, false) : null;

    $upcomingMeetings = \App\Models\Meeting::query()
        ->with(['meetingName', 'meetingNumber', 'meetingYear'])
        ->where('is_active', true)
        ->whereDate('meeting_date', '>=', today())
        ->orderBy('meeting_date')
        ->limit(3)
        ->get();

    $needsOfficer = \App\Models\Application::needsOfficerReassignment()->with('agency')->latest()->get();

    // PP4.2: permohonan Lengkap menunggu keputusan Pra JTICT (application_decisions sebagai sumber).
    $preJtictPending = $user->can('viewPreJtictQueue', \App\Models\Application::class)
        ? \App\Models\Application::awaitingPreJtictDecision()->count()
        : null;

    $pendingCount = \App\Models\User::pending()->count();

    // Kad hanya untuk kerja yang menunggu tindakan Urus Setia; status yang menunggu pemohon
    // (Tidak Lengkap, Pindaan Pra JTICT) atau telah Selesai tidak dipaparkan. Setiap kad boleh diklik.
    $listUrl = fn (\App\Enums\ApplicationStatus $status) => route('dashboard', ['status' => $status->value]) . '#senarai-permohonan';
    $actionCards = array_values(array_filter([
        ['title' => 'Semakan Permohonan', 'count' => $statusCounts[\App\Enums\ApplicationStatus::Submitted->value] ?? 0, 'unit' => 'permohonan',
            'desc' => 'Dihantar oleh agensi, menunggu semakan kelengkapan.', 'cta' => 'Semak permohonan',
            'href' => $listUrl(\App\Enums\ApplicationStatus::Submitted), 'tone' => 'bg-blue-100 text-blue-800', 'ink' => 'text-blue-800', 'ring' => 'hover:border-blue-700',
            'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2ZM9 14l2 2 4-4'],
        $preJtictPending === null ? null : ['title' => 'Keputusan Pra JTICT', 'count' => $preJtictPending, 'unit' => 'permohonan',
            'desc' => 'Lengkap, menunggu keputusan Pra JTICT direkodkan.', 'cta' => 'Rekod keputusan',
            'href' => route('pre-jtict.index'), 'tone' => 'bg-cyan-100 text-cyan-800', 'ink' => 'text-cyan-800', 'ring' => 'hover:border-cyan-700',
            'icon' => 'M12 3v18M5 7h14M5 7l-3 7a4 4 0 0 0 6 0ZM19 7l-3 7a4 4 0 0 0 6 0Z'],
        ['title' => 'Keputusan JTICT', 'count' => $statusCounts[\App\Enums\ApplicationStatus::InJtict->value] ?? 0, 'unit' => 'permohonan',
            'desc' => 'Dalam Proses JTICT, menunggu keputusan mesyuarat.', 'cta' => 'Rekod keputusan',
            'href' => route('jtict.index'), 'tone' => 'bg-indigo-100 text-indigo-800', 'ink' => 'text-indigo-800', 'ring' => 'hover:border-indigo-700',
            'icon' => 'M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-6 10 6Z'],
        ['title' => 'Pendaftaran Pengguna', 'count' => $pendingCount, 'unit' => 'menunggu',
            'desc' => 'Pendaftaran sendiri menunggu kelulusan Urus Setia.', 'cta' => 'Semak pendaftaran',
            'href' => route('users.requests'), 'tone' => 'bg-amber-100 text-amber-800', 'ink' => 'text-amber-800', 'ring' => 'hover:border-amber-700',
            'icon' => 'M2.5 20a6.5 6.5 0 0 1 13 0M19 8v6M16 11h6M12.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0'],
    ]));
@endphp

<!-- Salam & tindakan utama -->
<section class="flex flex-wrap justify-between items-end gap-4">
    <div class="flex flex-col gap-1.5">
        <span class="text-xs font-extrabold uppercase tracking-[0.14em] text-green-700">Papan Pemuka Urus Setia</span>
        <h1 class="text-3xl font-black tracking-tight text-slate-900">Selamat datang, {{ $user->name }}</h1>
        <p class="text-[15px] text-slate-600">Semak permohonan projek, urus mesyuarat JTICT dan luluskan pendaftaran pengguna.</p>
    </div>
    <div class="flex flex-wrap gap-2.5">
        <a href="{{ route('meetings.create') }}" class="inline-flex items-center gap-2 h-11 px-[18px] rounded-[10px] bg-white border border-slate-300 text-sm font-bold text-green-900 hover:border-green-900 transition">
            <svg aria-hidden="true" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg>
            Daftar Mesyuarat
        </a>
        @can('create', \App\Models\Application::class)
            <a href="{{ url('/application/new') }}" class="inline-flex items-center gap-2 h-11 px-[18px] rounded-[10px] bg-green-900 text-sm font-bold text-white hover:bg-green-800 transition">
                <svg aria-hidden="true" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                Permohonan Baharu
            </a>
        @endcan
    </div>
</section>

<!-- Status Mesyuarat JTICT -->
@if ($jtict)
    <section aria-label="Status Mesyuarat JTICT" class="bg-white border border-green-200 rounded-[14px] px-5 py-[18px] flex flex-wrap items-center gap-x-7 gap-y-4">
        <div class="flex items-center gap-3.5 flex-[1_1_340px] min-w-0">
            <span class="flex-none w-11 h-11 rounded-xl bg-green-100 text-green-800 flex items-center justify-center">
                <svg aria-hidden="true" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" /></svg>
            </span>
            <div class="flex flex-col gap-0.5">
                <strong class="text-base font-extrabold">Permohonan dibuka untuk {{ $jtict->label() }}</strong>
                <span class="text-sm text-slate-600">Agensi boleh menghantar permohonan sehingga tarikh tutup.</span>
            </div>
        </div>
        <dl class="flex flex-wrap gap-x-8 gap-y-3">
            <div class="flex flex-col gap-0.5"><dt class="text-xs font-semibold text-slate-500">Tarikh tutup</dt><dd class="text-[15px] font-extrabold">{{ $jtict->submission_closes_at->format('d/m/Y') }}</dd></div>
            @if ($jtict->meeting_date)
                <div class="flex flex-col gap-0.5"><dt class="text-xs font-semibold text-slate-500">Tarikh mesyuarat</dt><dd class="text-[15px] font-extrabold">{{ $jtict->meeting_date->format('d/m/Y') }}</dd></div>
            @endif
            <div class="flex flex-col gap-0.5"><dt class="text-xs font-semibold text-slate-500">Baki tempoh</dt><dd class="text-[15px] font-extrabold text-amber-700">{{ $daysLeft > 0 ? $daysLeft . ' hari' : 'Hari terakhir' }}</dd></div>
        </dl>
        <a href="{{ route('meetings.edit', $jtict->uuid) }}" class="text-[13px] font-bold text-green-900 hover:underline">Urus mesyuarat →</a>
    </section>
@else
    <section aria-label="Status Mesyuarat JTICT" class="bg-white border border-amber-200 rounded-[14px] px-5 py-[18px] flex flex-wrap items-center gap-x-7 gap-y-4">
        <div class="flex items-center gap-3.5 flex-[1_1_340px] min-w-0">
            <span class="flex-none w-11 h-11 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center">
                <svg aria-hidden="true" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M10 9v6M14 9v6" /></svg>
            </span>
            <div class="flex flex-col gap-0.5">
                <strong class="text-base font-extrabold">Tiada Mesyuarat JTICT yang dibuka buat masa ini</strong>
                <span class="text-sm text-slate-600">Agensi hanya boleh menyimpan draf sehingga tempoh permohonan dibuka.</span>
            </div>
        </div>
        <a href="{{ route('meetings.index') }}" class="text-[13px] font-bold text-green-900 hover:underline">Urus mesyuarat →</a>
    </section>
@endif

<!-- Kad tindakan Urus Setia -->
<section aria-labelledby="tajuk-tindakan" class="flex flex-col gap-3">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="tajuk-tindakan" class="text-lg font-extrabold">Perlu Tindakan Anda</h2>
        <span class="text-[13px] text-slate-500">Klik kad untuk melihat senarai berkaitan.</span>
    </div>
    <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-3.5">
        @foreach ($actionCards as $card)
            <a href="{{ $card['href'] }}" class="group bg-white border border-slate-200 rounded-[14px] p-[18px] flex flex-col gap-3 transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 {{ $card['ring'] }}">
                <span class="flex items-center justify-between gap-2.5">
                    <span class="inline-flex items-center gap-2.5">
                        <span aria-hidden="true" class="w-[38px] h-[38px] rounded-[11px] flex items-center justify-center {{ $card['tone'] }}">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $card['icon'] }}" /></svg>
                        </span>
                        <span class="text-sm font-extrabold text-slate-900">{{ $card['title'] }}</span>
                    </span>
                    <span @class(['text-[11px] font-extrabold px-2 py-0.5 rounded-full whitespace-nowrap', $card['tone'] => $card['count'] > 0, 'bg-slate-100 text-slate-500' => $card['count'] === 0])>{{ $card['count'] > 0 ? 'Perlu tindakan' : 'Tiada' }}</span>
                </span>
                <span class="flex items-baseline gap-2">
                    <span @class(['text-4xl leading-none font-black tracking-tight', 'text-slate-900' => $card['count'] > 0, 'text-slate-400' => $card['count'] === 0])>{{ number_format($card['count']) }}</span>
                    <span class="text-[13px] font-semibold text-slate-500">{{ $card['unit'] }}</span>
                </span>
                <span class="text-[13px] leading-snug text-slate-600">{{ $card['desc'] }}</span>
                <span class="mt-auto pt-2.5 border-t border-slate-100 flex items-center justify-between text-[13px] font-extrabold {{ $card['ink'] }}">
                    {{ $card['cta'] }}
                    <svg aria-hidden="true" class="w-4 h-4 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </span>
            </a>
        @endforeach
    </div>
</section>

<!-- Kandungan utama -->
<div class="flex flex-wrap gap-6 items-start">

    <!-- Senarai permohonan (status ikut ?status= daripada kad / menu; lalai Dihantar) -->
    @php($listStatus = \App\Enums\ApplicationStatus::tryFrom((string) request()->query('status')) ?? \App\Enums\ApplicationStatus::Submitted)
    <section id="senarai-permohonan" class="scroll-mt-6 flex-[999_1_640px] min-w-0 flex flex-col gap-3">
        <div class="flex flex-col gap-0.5">
            @if ($listStatus === \App\Enums\ApplicationStatus::Submitted)
                <h2 class="text-lg font-extrabold">Permohonan untuk Semakan</h2>
                <span class="text-[13px] text-slate-500">Permohonan projek yang telah dihantar untuk semakan Urus Setia.</span>
            @else
                <h2 class="text-lg font-extrabold">Permohonan {{ $listStatus->label() }}</h2>
                <span class="text-[13px] text-slate-500">{{ $listStatus->description() }}.</span>
            @endif
        </div>
        <livewire:application-list />
    </section>

    <!-- Lajur sisi -->
    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">

        <section class="bg-white border border-slate-200 rounded-[14px] px-5 py-[18px] flex flex-col gap-3.5">
            <div class="flex justify-between items-center gap-2">
                <h2 class="text-base font-extrabold">Mesyuarat Akan Datang</h2>
                <a href="{{ route('meetings.index') }}" class="text-[13px] font-bold text-green-900 hover:underline">Senarai</a>
            </div>
            @forelse ($upcomingMeetings as $meeting)
                <div class="flex gap-3.5 items-center">
                    <span @class([
                        'flex-none w-[52px] h-14 rounded-[10px] flex flex-col items-center justify-center gap-0.5',
                        'bg-brand-dark text-white' => $loop->first,
                        'bg-slate-200 text-slate-900' => ! $loop->first,
                    ])>
                        <span @class(['text-[11px] font-bold uppercase', 'text-amber-400' => $loop->first, 'text-slate-600' => ! $loop->first])>{{ $meeting->meeting_date->translatedFormat('M') }}</span>
                        <span class="text-xl leading-none font-black">{{ $meeting->meeting_date->format('d') }}</span>
                    </span>
                    <span class="flex flex-col gap-0.5 min-w-0">
                        <strong class="text-sm">{{ $meeting->label() }}</strong>
                        <span class="text-[13px] text-slate-500">{{ collect([$meeting->meeting_time ? $meeting->meetingTimeLabel() : null, $meeting->venue])->filter()->implode(' · ') ?: 'Masa dan tempat belum ditetapkan' }}</span>
                    </span>
                </div>
            @empty
                <p class="text-[13px] text-slate-500">Tiada mesyuarat aktif yang dijadualkan.</p>
            @endforelse
        </section>

        {{-- Pra JTICT & pendaftaran pengguna kini kad tindakan di atas; hanya amaran Pegawai Utama kekal di sini. --}}
        @if ($needsOfficer->isNotEmpty())
            <section aria-label="Tanpa Pegawai Utama aktif" class="bg-amber-50 border border-amber-200 rounded-[14px] px-[18px] py-4 flex flex-col gap-2.5">
                <div class="flex justify-between items-center gap-2">
                    <strong class="text-sm text-amber-800">Tanpa Pegawai Utama aktif</strong>
                    <span class="min-w-6 h-6 px-2 rounded-full bg-amber-800 text-white text-xs font-extrabold inline-flex items-center justify-center">{{ $needsOfficer->count() }}</span>
                </div>
                <p class="text-xs text-amber-900">Pegawai Utama telah bertukar keluar agensi, akaunnya dipadam, atau belum dilantik.</p>
                <ul class="flex flex-col gap-2">
                    @foreach ($needsOfficer as $app)
                        <li class="flex justify-between items-center gap-2.5 text-[13px]">
                            <span class="flex flex-col gap-px min-w-0">
                                <strong>{{ $app->reference_number }}</strong>
                                <span class="text-amber-900 truncate" title="{{ $app->title }}">{{ $app->title }}@if ($app->agency) · {{ $app->agency->code }}@endif</span>
                            </span>
                            <a href="{{ route('application.view', $app->uuid) }}" class="flex-none text-[13px] font-bold text-amber-800 hover:underline">Tambah Pegawai</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="bg-white border border-slate-200 rounded-[14px] px-5 py-[18px] flex flex-col gap-3">
            <h2 class="text-base font-extrabold">Pautan Pantas</h2>
            <div class="grid grid-cols-2 gap-2.5">
                @foreach ([
                    ['label' => 'Agensi', 'href' => route('admin.agencies'), 'icon' => 'M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6'],
                    ['label' => 'Senarai Pengguna', 'href' => route('users.index'), 'icon' => 'M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6M12.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0'],
                    ['label' => 'Daftar Pengguna', 'href' => route('users.create'), 'icon' => 'M2.5 20a6.5 6.5 0 0 1 13 0M19 8v6M16 11h6M12.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0'],
                    ['label' => 'Master Data', 'href' => route('master-data.application-categories'), 'icon' => 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3'],
                ] as $shortcut)
                    <a href="{{ $shortcut['href'] }}" class="flex items-center gap-3 p-3 min-h-11 rounded-[10px] border border-slate-200 bg-white text-slate-900 hover:border-green-900 transition">
                        <svg aria-hidden="true" class="flex-none w-5 h-5 text-green-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $shortcut['icon'] }}" /></svg>
                        <span class="text-[13px] font-bold">{{ $shortcut['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </aside>
</div>
