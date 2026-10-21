<x-app-layout>
    <div class="py-8 bg-brand-canvas min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @hasrole('superadmin')
                @php
                    $agencyCount = \App\Models\Agency::count();
                    $userCount = \App\Models\User::count();
                    $applicationCount = \App\Models\Application::count();
                    $projectCount = \App\Models\Project::count();
                @endphp

                <div class="bg-gradient-to-r from-slate-900 to-indigo-900 p-6 sm:p-8 rounded-2xl shadow-sm text-white flex flex-col sm:flex-row justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-indigo-200 uppercase">SPPICT v2</p>
                        <h2 class="mt-2 text-2xl font-black">Dashboard Induk</h2>
                        <p class="text-sm text-slate-300 mt-1">Kawalan data asas, pengguna, peranan dan pemantauan keseluruhan sistem.</p>
                    </div>
                    <span class="self-start px-3 py-1 bg-white/10 border border-white/20 text-xs font-bold rounded-full">Akses Superadmin</span>
                </div>

                <section>
                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-600 mb-3">Statistik & Analitik Ringkasan</h3>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm"><p class="text-xs font-semibold text-slate-500">Agensi Berdaftar</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($agencyCount) }}</p></div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm"><p class="text-xs font-semibold text-slate-500">Pengguna Aktif</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($userCount) }}</p></div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm"><p class="text-xs font-semibold text-slate-500">Permohonan Direkodkan</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($applicationCount) }}</p></div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm"><p class="text-xs font-semibold text-slate-500">Projek Direkodkan</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($projectCount) }}</p></div>
                    </div>
                </section>

                <section>
                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-600 mb-3">Modul Pentadbiran</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <a href="{{ route('admin.users') }}" class="p-6 bg-white rounded-2xl shadow-sm border border-slate-100 hover:border-blue-500 transition group">
                            <div class="w-10 h-10 mb-4 rounded-xl bg-violet-50 text-violet-700 flex items-center justify-center font-black">U</div><h4 class="text-base font-bold text-slate-800 group-hover:text-blue-600">Pengguna & Peranan</h4><p class="text-xs text-slate-500 mt-1">Urus akaun, peranan capaian, dan kata laluan.</p>
                        </a>
                        <a href="{{ route('admin.positions-grades') }}" class="p-6 bg-white rounded-2xl shadow-sm border border-slate-100 hover:border-blue-500 transition group">
                            <div class="w-10 h-10 mb-4 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-black">JG</div><h4 class="text-base font-bold text-slate-800 group-hover:text-blue-600">Jawatan & Gred</h4><p class="text-xs text-slate-500 mt-1">Urus gred perkhidmatan dan jawatan rasmi.</p>
                        </a>
                        <a href="{{ route('admin.audit-logs') }}" class="p-6 bg-white rounded-2xl shadow-sm border border-slate-100 hover:border-blue-500 transition group">
                            <div class="w-10 h-10 mb-4 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center font-black">LA</div><h4 class="text-base font-bold text-slate-800 group-hover:text-blue-600">Log Audit</h4><p class="text-xs text-slate-500 mt-1">Semak rekod tindakan dan perubahan data sistem.</p>
                        </a>
                    </div>
                </section>
            @else
                @hasrole('pentadbir_urus_setia')
                    @include('dashboard.urus-setia')
                @else
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <div><h2 class="text-2xl font-black text-slate-900 tracking-tight">Sistem Permohonan Projek ICT (SPPICT) v2</h2><p class="text-xs text-slate-500 mt-1">Sistem Permohonan dan Pemantauan Projek ICT.</p></div>
                        @can('create', \App\Models\Application::class)
                            <a href="{{ url('/application/new') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition hover:scale-105"><span class="mr-1.5 text-base">+</span> Permohonan Baharu</a>
                        @endcan
                    </div>

                    @include('livewire.partials.jtict-status', ['meeting' => \App\Models\Meeting::currentJtict()])
                    <livewire:application-list />
                @endhasrole
            @endhasrole
        </div>
    </div>
</x-app-layout>
