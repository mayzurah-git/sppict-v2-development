{{-- Senarai Permohonan (Pengguna Biasa): semua permohonan yang didaftarkan atau dipertanggungjawabkan kepada pengguna, termasuk draf. --}}
@php
    $theme = \App\Support\RoleTheme::for(auth()->user());
@endphp

<x-app-layout>
    <div class="min-h-screen {{ $theme['canvas'] }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-5">
            <section class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
                <div class="flex flex-col gap-1.5">
                    <nav aria-label="Laluan" class="text-[13px] text-slate-500">
                        <a href="{{ route('dashboard') }}" class="font-semibold hover:underline {{ $theme['link'] }}">Papan Pemuka</a>
                        <span aria-hidden="true">/</span>
                        Permohonan
                    </nav>
                    <h1 class="text-[28px] font-black tracking-tight text-slate-900">Senarai Permohonan</h1>
                    <p class="text-[15px] text-slate-600">Semua permohonan projek yang anda daftarkan atau dipertanggungjawabkan kepada anda, termasuk draf.</p>
                </div>
                @can('create', \App\Models\Application::class)
                    <a href="{{ route('application.create') }}" class="inline-flex items-center gap-2 min-h-[46px] px-5 rounded-xl text-[15px] font-extrabold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $theme['button'] }}">
                        <svg aria-hidden="true" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                        Permohonan Baru
                    </a>
                @endcan
            </section>

            <livewire:application-list />
        </div>
    </div>
</x-app-layout>
