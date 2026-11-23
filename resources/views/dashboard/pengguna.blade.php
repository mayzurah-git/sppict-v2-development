{{-- Papan Pemuka Pengguna Biasa (pemohon). Skema warna biru langit (App\Support\RoleTheme). --}}
@php
    use App\Enums\ApplicationDecision;
    use App\Enums\ApplicationStatus;
    use App\Models\Application;
    use App\Models\Meeting;
    use App\Models\Project;

    $user = auth()->user();

    // Permohonan pemohon: didaftarkan olehnya atau projek yang dia pegawai bertanggungjawab aktif.
    $totalApplications = Application::forApplicant($user)->count();
    $approvedApplications = Application::forApplicant($user)
        ->whereHas('latestJtictDecision', fn ($decision) => $decision->where('decision', ApplicationDecision::Approved->value))
        ->count();

    // Perlu tindakan: status yang boleh dipinda pemohon (Draf / Tidak Lengkap / Pindaan Pra JTICT)
    // dan yang pengguna ini dibenarkan mengemas kini (ApplicationPolicy::update).
    $actionApplications = Application::forApplicant($user)
        ->whereIn('status', array_map(fn (ApplicationStatus $status) => $status->value, ApplicationStatus::editableCases()))
        ->with(['latestAmendmentReview', 'latestPreJtictDecision'])
        ->latest('updated_at')
        ->get()
        ->filter(fn (Application $application) => $user->can('update', $application))
        ->values();

    // SP5: projek pemantauan (status kitaran, nilai cache kemajuan, laporan bulanan dihantar terkini).
    $projects = Project::query()
        ->whereHas('application', fn ($application) => $application->forApplicant($user))
        ->with(['application:id,uuid,reference_number,title', 'progressReports' => fn ($report) => $report
            ->where('status', \App\Enums\ProgressReportStatus::Submitted->value)
            ->orderByDesc('report_year')->orderByDesc('report_month')])
        ->get()
        ->sortBy(fn (Project $project) => [in_array($project->status, [\App\Enums\ProjectStatus::Completed, \App\Enums\ProjectStatus::Closed], true) ? 1 : 0, -$project->id])
        ->values();
    $ongoingProjects = $projects->filter(fn (Project $project) => in_array($project->status, [\App\Enums\ProjectStatus::InProgress, \App\Enums\ProjectStatus::PendingClosure], true))->count();

    $jtict = Meeting::currentJtict()?->load(['meetingName', 'meetingNumber', 'meetingYear']);
    $daysLeft = $jtict?->submission_closes_at ? (int) today()->diffInDays($jtict->submission_closes_at, false) : null;

    // Mesyuarat aktif yang menetapkan tarikh akhir kemas kini kemajuan projek.
    $progressMeeting = Meeting::query()
        ->with(['meetingName', 'meetingNumber', 'meetingYear'])
        ->where('is_active', true)
        ->whereDate('progress_update_deadline', '>=', today())
        ->orderBy('progress_update_deadline')
        ->first();

    $months = [1 => 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];
@endphp

<!-- Salam -->
<section class="flex flex-wrap justify-between items-end gap-4">
    <div class="flex flex-col gap-1.5">
        @if ($user->agency)
            <span class="text-xs font-extrabold uppercase tracking-[0.14em] text-sky-700">{{ $user->agency->name }}</span>
        @endif
        <h1 class="text-3xl font-black tracking-tight text-slate-900">Selamat datang, {{ $user->name }}</h1>
        <p class="text-[15px] text-slate-600">Pantau permohonan projek ICT anda dan status kemajuan projek yang diluluskan.</p>
    </div>
    @can('create', Application::class)
        <a href="{{ url('/application/new') }}" class="inline-flex items-center gap-2 h-11 px-[18px] rounded-[10px] bg-sky-700 text-sm font-bold text-white hover:bg-sky-800 transition focus:outline-none focus-visible:ring-[3px] focus-visible:ring-sky-300">
            <svg aria-hidden="true" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
            Permohonan Baharu
        </a>
    @endcan
</section>

<!-- Makluman Mesyuarat Aktif -->
<section aria-labelledby="makluman-mesyuarat" class="bg-white border border-sky-100 rounded-[14px] overflow-hidden">
    <div class="px-5 py-3.5 border-b border-sky-100 flex items-center gap-2.5">
        <svg aria-hidden="true" class="w-5 h-5 text-sky-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
        <h2 id="makluman-mesyuarat" class="text-base font-extrabold">Makluman Mesyuarat Aktif</h2>
    </div>
    <div class="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] divide-y md:divide-y-0 md:divide-x divide-sky-100">
        @if ($jtict)
            <div class="px-5 py-[18px] flex flex-col gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex-none w-11 h-11 rounded-xl bg-green-100 text-green-800 flex items-center justify-center">
                        <svg aria-hidden="true" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z" /><path d="M12 11v6M9 14h6" /></svg>
                    </span>
                    <div class="flex flex-col gap-0.5">
                        <strong class="text-[15px] font-extrabold">Permohonan dibuka untuk {{ $jtict->label() }}</strong>
                        <span class="text-sm text-slate-600">Hantar permohonan baharu atau pindaan sebelum tarikh tutup.</span>
                    </div>
                </div>
                <dl class="flex flex-wrap gap-x-7 gap-y-2.5">
                    <div class="flex flex-col gap-0.5"><dt class="text-xs font-semibold text-slate-500">Tarikh tutup</dt><dd class="text-[15px] font-extrabold">{{ $jtict->submission_closes_at->format('d/m/Y') }}</dd></div>
                    @if ($jtict->meeting_date)
                        <div class="flex flex-col gap-0.5"><dt class="text-xs font-semibold text-slate-500">Tarikh mesyuarat</dt><dd class="text-[15px] font-extrabold">{{ $jtict->meeting_date->format('d/m/Y') }}</dd></div>
                    @endif
                    <div class="flex flex-col gap-0.5"><dt class="text-xs font-semibold text-slate-500">Baki tempoh</dt><dd class="text-[15px] font-extrabold text-amber-700">{{ $daysLeft > 0 ? $daysLeft . ' hari' : 'Hari terakhir' }}</dd></div>
                </dl>
            </div>
        @else
            <div class="px-5 py-[18px] flex items-start gap-3">
                <span class="flex-none w-11 h-11 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center">
                    <svg aria-hidden="true" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9" /><path d="M10 9v6M14 9v6" /></svg>
                </span>
                <div class="flex flex-col gap-0.5">
                    <strong class="text-[15px] font-extrabold">Tiada Mesyuarat JTICT yang dibuka buat masa ini</strong>
                    <span class="text-sm text-slate-600">Anda boleh menyimpan permohonan sebagai draf dan menghantarnya apabila Mesyuarat JTICT seterusnya dibuka oleh Urus Setia.</span>
                </div>
            </div>
        @endif

        @if ($progressMeeting)
            <div class="px-5 py-[18px] flex flex-col gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex-none w-11 h-11 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center">
                        <svg aria-hidden="true" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="m7 15 4-4 3 3 5-6" /></svg>
                    </span>
                    <div class="flex flex-col gap-0.5">
                        <strong class="text-[15px] font-extrabold">Kemas kini kemajuan projek untuk {{ $progressMeeting->label() }}</strong>
                        <span class="text-sm text-slate-600">Pastikan laporan kemajuan projek anda dikemas kini sebelum tarikh akhir.</span>
                    </div>
                </div>
                <dl class="flex flex-wrap gap-x-7 gap-y-2.5">
                    <div class="flex flex-col gap-0.5"><dt class="text-xs font-semibold text-slate-500">Tarikh akhir kemas kini</dt><dd class="text-[15px] font-extrabold">{{ $progressMeeting->progress_update_deadline->format('d/m/Y') }}</dd></div>
                </dl>
            </div>
        @endif
    </div>
</section>

<!-- Ringkasan -->
<section aria-label="Ringkasan permohonan pemohon" class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3.5">
    <div class="bg-sky-700 text-white rounded-[14px] p-5 flex flex-col gap-2.5">
        <span class="text-[13px] font-bold text-sky-100">Jumlah Permohonan Projek Pemohon</span>
        <span class="text-[40px] leading-none font-black tracking-tight">{{ number_format($totalApplications) }}</span>
        <span class="text-xs text-sky-100">Permohonan yang anda daftarkan, termasuk draf</span>
    </div>
    <div class="bg-white border border-sky-100 rounded-[14px] p-5 flex flex-col gap-2.5">
        <span class="flex justify-between items-center gap-2"><span class="text-[13px] font-bold text-slate-600">Permohonan Projek Lulus</span><span class="w-2.5 h-2.5 rounded-full bg-green-700"></span></span>
        <span class="text-[40px] leading-none font-black tracking-tight">{{ number_format($approvedApplications) }}</span>
        <span class="text-xs text-slate-500">Keputusan Mesyuarat JTICT: Lulus</span>
    </div>
    <div class="bg-white border border-orange-200 rounded-[14px] p-5 flex flex-col gap-2.5">
        <span class="flex justify-between items-center gap-2"><span class="text-[13px] font-bold text-slate-600">Perlu Tindakan</span><span class="w-2.5 h-2.5 rounded-full bg-orange-700"></span></span>
        <span class="text-[40px] leading-none font-black tracking-tight text-orange-800">{{ number_format($actionApplications->count()) }}</span>
        <span class="text-xs text-slate-500">Draf, Tidak Lengkap atau Pindaan Pra JTICT</span>
    </div>
    <div class="bg-white border border-sky-100 rounded-[14px] p-5 flex flex-col gap-2.5">
        <span class="flex justify-between items-center gap-2"><span class="text-[13px] font-bold text-slate-600">Projek Dalam Pelaksanaan</span><span class="w-2.5 h-2.5 rounded-full bg-sky-700"></span></span>
        <span class="text-[40px] leading-none font-black tracking-tight">{{ number_format($ongoingProjects) }}</span>
        <span class="text-xs text-slate-500">Daripada {{ number_format($projects->count()) }} projek yang diluluskan</span>
    </div>
</section>

<!-- Permohonan yang perlu tindakan -->
<section aria-labelledby="perlu-tindakan" class="flex flex-col gap-3">
    <div class="flex flex-wrap justify-between items-baseline gap-3">
        <h2 id="perlu-tindakan" class="text-lg font-extrabold">Permohonan Projek yang Perlu Tindakan</h2>
        <span class="text-[13px] text-slate-500">Permohonan yang boleh anda pinda dan hantar</span>
    </div>
    @if ($actionApplications->isEmpty())
        <p class="bg-white border border-sky-100 rounded-[14px] px-5 py-6 text-sm font-semibold text-green-800">Tiada permohonan yang memerlukan tindakan anda.</p>
    @else
        <div class="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-3.5">
            @foreach ($actionApplications as $application)
                @php
                    $status = $application->statusEnum();
                    $note = match ($status) {
                        ApplicationStatus::NeedsAmendment => $application->latestAmendmentReview?->remarks
                            ? 'Ulasan Urus Setia: ' . $application->latestAmendmentReview->remarks
                            : 'Dikembalikan oleh Urus Setia untuk pindaan.',
                        ApplicationStatus::PreJtictAmendment => $application->latestPreJtictDecision?->remarks
                            ? 'Keputusan Pra JTICT: ' . $application->latestPreJtictDecision->remarks
                            : 'Pra JTICT: Ditolak dengan Pindaan.',
                        default => 'Draf belum dihantar.',
                    };
                @endphp
                <article wire:key="tindakan-{{ $application->id }}" class="bg-white border border-sky-100 rounded-[14px] p-[18px] flex flex-col gap-3">
                    <div class="flex justify-between items-center gap-2">
                        <span class="text-[13px] font-bold tabular-nums text-slate-600">{{ $application->reference_number }}</span>
                        <x-application-status-badge :status="$application->status" :label="$application->statusDisplayLabel()" />
                    </div>
                    <strong class="text-[15px] font-extrabold leading-snug">{{ $application->title ?: 'Tanpa tajuk' }}</strong>
                    <p @class([
                        'text-[13px] leading-relaxed px-3 py-2.5 rounded-[10px]',
                        'bg-slate-50 text-slate-600' => $status === ApplicationStatus::Draft,
                        'bg-orange-50 text-orange-900' => $status !== ApplicationStatus::Draft,
                    ])>{{ $note }}</p>
                    <div class="mt-auto flex justify-between items-center gap-2.5">
                        <span class="text-xs text-slate-500">Dikemas kini {{ $application->updated_at?->format('d/m/Y') }}</span>
                        <a href="{{ route('application.edit', $application->uuid) }}" class="inline-flex items-center h-9 px-3.5 rounded-[10px] bg-sky-700 text-[13px] font-bold text-white hover:bg-sky-800 transition">
                            {{ $status === ApplicationStatus::Draft ? 'Sambung draf' : 'Pinda & hantar semula' }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<!-- Senarai permohonan projek -->
<section id="senarai-permohonan" class="flex flex-col gap-3 scroll-mt-6">
    <div class="flex flex-col gap-0.5">
        <h2 class="text-lg font-extrabold">Senarai Permohonan Projek</h2>
        <span class="text-[13px] text-slate-500">Status proses dan keputusan mesyuarat terkini bagi setiap permohonan anda.</span>
    </div>
    <livewire:application-list />
</section>

<!-- Status kemajuan projek -->
<section id="kemajuan-projek" class="bg-white border border-sky-100 rounded-[14px] overflow-hidden scroll-mt-6">
    <div class="px-5 py-[18px] flex flex-wrap justify-between items-end gap-3 border-b border-sky-100">
        <div class="flex flex-col gap-0.5">
            <h2 class="text-lg font-extrabold">Senarai Status Kemajuan Projek</h2>
            <span class="text-[13px] text-slate-500">Kemajuan sebenar berbanding jadual (dirancang), dikemas kini setiap kali aktiviti ditanda siap dan setiap hari.</span>
        </div>
        <div class="flex flex-wrap gap-3.5 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px] bg-green-700"></span>Hijau: tidak lewat</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px] bg-amber-600"></span>Kuning: lewat kurang 20</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px] bg-red-700"></span>Merah: lewat 20 atau lebih</span>
        </div>
    </div>

    @forelse ($projects as $project)
        @php
            // SP5: nilai cache SP4 (ProgressCalculator) dan status kitaran projek.
            $report = $project->progressReports->first();
            $physical = (float) $project->actual_progress_percent;
            $planned = $project->planned_progress_percent !== null ? (float) $project->planned_progress_percent : null;
            $variance = $project->variance_percent !== null ? (float) $project->variance_percent : null;
            $indicator = $variance !== null ? \App\Enums\ScheduleStatus::indicatorFor($variance) : null;
            $pct = fn (float $value) => rtrim(rtrim(number_format($value, 2), '0'), '.');
            [$indicatorLabel, $indicatorClass, $barClass] = match (true) {
                $project->status === \App\Enums\ProjectStatus::Completed => ['Selesai', 'bg-slate-200 text-slate-700', 'bg-green-700'],
                $project->status === \App\Enums\ProjectStatus::Closed => ['Ditutup', 'bg-slate-200 text-slate-700', 'bg-slate-500'],
                $project->status === \App\Enums\ProjectStatus::PendingClosure => ['Menunggu Pengesahan Penutupan', 'bg-sky-100 text-sky-800', 'bg-green-700'],
                $indicator === 'RED' => ['Lewat · ' . $pct(abs($variance)) . ' mata', 'bg-red-100 text-red-800', 'bg-red-700'],
                $indicator === 'AMBER' => ['Lewat · ' . $pct(abs($variance)) . ' mata', 'bg-amber-100 text-amber-800', 'bg-amber-600'],
                $indicator === 'GREEN' => [\App\Enums\ScheduleStatus::fromVariance($variance)->label(), 'bg-green-100 text-green-800', 'bg-green-700'],
                default => ['Belum dinilai', 'bg-slate-100 text-slate-700', 'bg-sky-700'],
            };
        @endphp
        <article wire:key="projek-{{ $project->id }}" class="px-5 py-[18px] border-b border-sky-50 last:border-b-0 grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-x-7 gap-y-4 items-center">
            <div class="flex flex-col gap-1 min-w-0">
                <span class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-extrabold {{ $indicatorClass }}">{{ $indicatorLabel }}</span>
                    <span class="text-xs font-bold tabular-nums text-slate-500">{{ $project->application?->reference_number }}</span>
                </span>
                <strong class="text-[15px] font-extrabold"><a href="{{ route('projects.show', $project->uuid) }}" class="hover:underline">{{ $project->application?->title }}</a></strong>
                <span class="text-[13px] text-slate-500">
                    {{ collect([
                        $project->status === \App\Enums\ProjectStatus::Completed && $project->actual_completion_date ? 'Selesai ' . $project->actual_completion_date->format('d/m/Y') : null,
                        $project->status !== \App\Enums\ProjectStatus::Completed && $project->planned_end_date ? 'Tamat ' . $project->planned_end_date->format('d/m/Y') : null,
                        $project->isAwaitingJpict() ? 'Menunggu JPICT' : null,
                    ])->filter()->implode(' · ') }}
                </span>
            </div>
            <div class="flex flex-col gap-1.5">
                <span class="flex justify-between text-[13px]"><span class="font-bold">Kemajuan</span><span class="tabular-nums"><strong>{{ $pct($physical) }}%</strong>@if ($planned !== null) <span class="text-slate-500">/ dirancang {{ $pct($planned) }}%</span>@endif</span></span>
                <span class="relative h-2.5 rounded-full bg-sky-50">
                    <span class="absolute inset-y-0 left-0 rounded-full {{ $barClass }}" style="width: {{ min(100, max(0, $physical)) }}%"></span>
                    @if ($planned !== null)
                        <span class="absolute -top-[3px] -bottom-[3px] w-0.5 bg-slate-900" style="left: calc({{ min(100, max(0, $planned)) }}% - 1px)" title="Dirancang"></span>
                    @endif
                </span>
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="text-xs text-slate-500">Laporan bulanan terkini</span>
                <span class="text-[13px] font-bold">{{ $report ? ($months[$report->report_month] ?? $report->report_month) . ' ' . $report->report_year : 'Belum ada laporan' }}</span>
            </div>
        </article>
    @empty
        <p class="px-5 py-8 text-sm text-slate-500">Tiada projek dalam pelaksanaan. Projek akan dipaparkan di sini selepas permohonan anda diluluskan oleh Mesyuarat JTICT.</p>
    @endforelse
</section>
