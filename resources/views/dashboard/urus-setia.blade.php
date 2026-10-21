{{-- Papan Pemuka Urus Setia (pentadbir_urus_setia). --}}
@php
    $user = auth()->user();

    // Status permohonan yang wujud dalam aliran kerja (draf tidak dikira; ia milik pemohon).
    $statusCards = [
        'SUBMITTED' => ['label' => 'Dihantar', 'hint' => 'Menunggu semakan Urus Setia', 'dot' => 'bg-blue-800'],
        'NEEDS_AMENDMENT' => ['label' => 'Perlu Pembetulan', 'hint' => 'Dikembalikan kepada agensi', 'dot' => 'bg-purple-800'],
        'APPROVED' => ['label' => 'Lulus', 'hint' => 'Diluluskan mesyuarat', 'dot' => 'bg-emerald-800'],
        'REJECTED' => ['label' => 'Ditolak', 'hint' => 'Tidak diluluskan', 'dot' => 'bg-red-800'],
    ];
    $statusCounts = \App\Models\Application::query()
        ->whereIn('status', array_keys($statusCards))
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

    $pendingUsers = \App\Models\User::pending()->with('agency:id,name')->oldest()->limit(5)->get();
    $pendingCount = \App\Models\User::pending()->count();
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

<!-- Ringkasan ikut status -->
<section aria-label="Ringkasan status permohonan" class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3.5">
    @foreach ($statusCards as $status => $card)
        <div class="bg-white border border-slate-200 rounded-[14px] p-[18px] flex flex-col gap-2.5">
            <span class="flex items-center justify-between gap-2">
                <span class="text-[13px] font-bold text-slate-600">{{ $card['label'] }}</span>
                <span class="w-2.5 h-2.5 rounded-full {{ $card['dot'] }}"></span>
            </span>
            <span class="text-[32px] leading-none font-black tracking-tight">{{ number_format($statusCounts[$status] ?? 0) }}</span>
            <span class="text-xs text-slate-500">{{ $card['hint'] }}</span>
        </div>
    @endforeach
</section>

<!-- Kandungan utama -->
<div class="flex flex-wrap gap-6 items-start">

    <!-- Permohonan untuk semakan -->
    <section class="flex-[999_1_640px] min-w-0 flex flex-col gap-3">
        <div class="flex flex-col gap-0.5">
            <h2 class="text-lg font-extrabold">Permohonan untuk Semakan</h2>
            <span class="text-[13px] text-slate-500">Permohonan projek yang telah dihantar untuk semakan Urus Setia.</span>
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

        <section class="bg-white border border-slate-200 rounded-[14px] px-5 py-[18px] flex flex-col gap-3.5">
            <h2 class="text-base font-extrabold">Perlu Tindakan</h2>

            @if ($needsOfficer->isNotEmpty())
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 flex flex-col gap-2.5">
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
                                <a href="{{ route('application.view', $app->uuid) }}" class="flex-none text-[13px] font-bold text-amber-800 hover:underline">Lantik Pegawai</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="border border-slate-200 rounded-xl p-3.5 flex flex-col gap-2.5">
                <div class="flex justify-between items-center gap-2">
                    <strong class="text-sm">Permohonan pendaftaran pengguna</strong>
                    <span class="min-w-6 h-6 px-2 rounded-full bg-blue-700 text-white text-xs font-extrabold inline-flex items-center justify-center">{{ $pendingCount }}</span>
                </div>
                @forelse ($pendingUsers as $pending)
                    <div class="flex flex-col gap-px text-[13px]">
                        <strong>{{ $pending->name }}</strong>
                        <span class="text-slate-500">{{ collect([$pending->agency?->name, $pending->created_at?->format('d/m/Y')])->filter()->implode(' · ') }}</span>
                    </div>
                @empty
                    <p class="text-[13px] font-semibold text-green-800">Semua permohonan pendaftaran telah disemak.</p>
                @endforelse
                <a href="{{ route('users.requests') }}" class="text-[13px] font-bold text-green-900 hover:underline">Semak permohonan pendaftaran →</a>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-[14px] px-5 py-[18px] flex flex-col gap-3">
            <h2 class="text-base font-extrabold">Pintasan</h2>
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
