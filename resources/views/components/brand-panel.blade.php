{{-- Panel identiti SPPICT (halaman tetamu & laman utama). Ringkas pada telefon, penuh pada skrin md ke atas. --}}
<section {{ $attributes->merge(['class' => 'relative overflow-hidden bg-brand-dark text-white px-6 py-8 md:px-14 md:py-12 flex flex-col justify-between gap-8 md:gap-12 rounded-b-3xl md:rounded-none']) }}>
    <svg aria-hidden="true" class="pointer-events-none absolute -right-44 -bottom-44 w-[560px] h-[560px] opacity-[0.09]" viewBox="0 0 560 560">
        <circle cx="280" cy="280" r="270" fill="none" stroke="#fbbf24" stroke-width="2" />
        <circle cx="280" cy="280" r="200" fill="none" stroke="#fbbf24" stroke-width="2" />
        <circle cx="280" cy="280" r="130" fill="none" stroke="#fbbf24" stroke-width="2" />
        <circle cx="280" cy="280" r="60" fill="none" stroke="#fbbf24" stroke-width="2" />
    </svg>

    <a href="{{ url('/') }}" class="relative flex items-center gap-3.5">
        <img src="{{ asset('images/logo-jata-n9.png') }}" alt="Jata Negeri Sembilan" class="h-11 md:h-[60px] w-auto" />
        <span class="flex flex-col gap-1">
            <span class="text-[11px] md:text-xs font-extrabold uppercase tracking-[0.14em] text-amber-400">Bahagian Teknologi Maklumat</span>
            <span class="text-xs md:text-sm font-semibold text-brand-soft">Pejabat Setiausaha Kerajaan Negeri Sembilan</span>
        </span>
    </a>

    <div class="relative flex flex-col gap-2 md:gap-5 max-w-md">
        <h1 class="text-4xl md:text-[56px] leading-none font-black tracking-tight">SPPICT <span class="text-amber-400">v2</span></h1>
        <p class="text-sm md:text-[17px] leading-relaxed text-brand-soft">
            Sistem Permohonan dan Pemantauan Projek ICT Negeri Sembilan<span class="hidden md:inline"> — berpusat, efisien dan telus</span>.
        </p>

        <ul class="hidden md:flex flex-col gap-3.5 mt-3">
            <li class="flex gap-3.5 items-start">
                <span class="flex-none w-9 h-9 rounded-[10px] bg-amber-400/15 text-amber-400 flex items-center justify-center">
                    <svg aria-hidden="true" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h2" /><path d="M6 3h9l4 4v14H6z" /></svg>
                </span>
                <span class="flex flex-col gap-0.5 text-sm">
                    <strong class="font-bold">Permohonan berfasa</strong>
                    <span class="text-brand-muted">Borang Fasa 1 hingga Fasa 5 dengan spesifikasi dinamik.</span>
                </span>
            </li>
            <li class="flex gap-3.5 items-start">
                <span class="flex-none w-9 h-9 rounded-[10px] bg-amber-400/15 text-amber-400 flex items-center justify-center">
                    <svg aria-hidden="true" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></svg>
                </span>
                <span class="flex flex-col gap-0.5 text-sm">
                    <strong class="font-bold">Pemantauan kelulusan</strong>
                    <span class="text-brand-muted">Jejak status mesyuarat, kemajuan fizikal dan kewangan.</span>
                </span>
            </li>
        </ul>
    </div>

    <div class="relative hidden md:flex justify-between items-center gap-3 pt-5 border-t border-white/15 text-[13px] text-brand-muted">
        <span>© {{ date('Y') }} Hak Cipta Terpelihara SUKNS</span>
        <span class="text-xs px-2 py-1 rounded-md border border-white/20">v2.0</span>
    </div>
</section>
