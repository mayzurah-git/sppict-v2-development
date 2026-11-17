<x-app-layout>
    {{-- UM12: Profil Pengguna. Kepala profil & Maklumat Rasmi (rujukan sahaja) dipaparkan di sini;
         medan yang boleh dikemas kini berada dalam komponen Volt profile.* di lajur kanan.
         Warna ikut peranan (App\Support\RoleTheme). --}}
    @php
        $profile = auth()->user()->load(['agency:id,code,name', 'positionRelation.serviceScheme', 'roles:id,name']);
        $theme = \App\Support\RoleTheme::for($profile);
        $role = $profile->roles->first()?->name;
        $roleLabel = \App\Livewire\Admin\UserManagement::ROLE_LABELS[$role] ?? ($role ?: '-');
        $initials = collect(preg_split('/\s+/', trim($profile->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
        $scheme = $profile->positionRelation?->serviceScheme;
        $isActive = $profile->status === \App\Enums\UserStatus::Active;

        $details = [
            ['label' => 'No. Kad Pengenalan', 'value' => $profile->no_kad_pengenalan ?? '-', 'mono' => true,
                'icon' => '<rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="12" r="2.5" /><path d="M14 10h4M14 14h3" />'],
            ['label' => 'Jabatan/Agensi', 'value' => $profile->agency?->name ?? '-',
                'icon' => '<path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6" />'],
            ['label' => 'Jawatan', 'value' => $profile->positionRelation?->title ?? ($profile->getAttribute('position') ?: '-'),
                'icon' => '<rect x="3" y="7" width="18" height="13" rx="2" /><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18" />'],
            ['label' => 'Skim Perkhidmatan', 'value' => $scheme ? "{$scheme->code} — {$scheme->name}" : '-',
                'icon' => '<path d="M4 19.5V5a2 2 0 0 1 2-2h14v16H6.5A2.5 2.5 0 0 0 4 21.5ZM8 7h8M8 11h6" />'],
            ['label' => 'Tahap Capaian', 'value' => $roleLabel,
                'icon' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6Z" /><path d="m9 12 2 2 4-4" />'],
        ];
    @endphp

    <div class="min-h-screen {{ $theme['canvas'] }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-5">

            <!-- Kepala profil -->
            <section aria-labelledby="tajuk-profil" class="bg-white border border-slate-200 rounded-[18px] overflow-hidden">
                <div class="relative h-[84px] {{ $theme['bar'] }}">
                    <svg aria-hidden="true" class="absolute inset-0 w-full h-full {{ $theme['dots'] }}"><defs><pattern id="profil-titik" width="18" height="18" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.4" fill="currentColor" opacity="0.45" /></pattern></defs><rect width="100%" height="100%" fill="url(#profil-titik)" /></svg>
                </div>
                <div class="px-5 sm:px-7 pt-4 pb-6 flex flex-wrap items-start gap-x-6 gap-y-4">
                    <span aria-hidden="true" class="relative z-10 -mt-[60px] shrink-0 w-24 h-24 rounded-3xl border-4 border-white shadow-lg flex items-center justify-center text-[34px] font-black {{ $theme['avatar'] }}">{{ $initials }}</span>
                    <div class="flex-1 min-w-0 basis-80 space-y-1.5">
                        <p class="text-xs font-bold tracking-[0.12em] uppercase {{ $theme['eyebrow'] }}">Profil Pengguna</p>
                        <h1 id="tajuk-profil" class="text-[19px] font-extrabold leading-snug text-slate-900 break-words">{{ $profile->name }}</h1>
                        <div class="flex flex-wrap gap-2 pt-0.5">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[13px] font-bold {{ $theme['soft'] }}">
                                <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6Z" /></svg>{{ $roleLabel }}
                            </span>
                            @if ($profile->agency)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[13px] font-semibold">
                                    <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6" /></svg>{{ $profile->agency->name }}
                                </span>
                            @endif
                            <span @class(['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[13px] font-bold', 'bg-green-100 text-green-800' => $isActive, 'bg-slate-100 text-slate-700' => ! $isActive])>
                                <span aria-hidden="true" @class(['w-2 h-2 rounded-full', 'bg-green-600' => $isActive, 'bg-slate-500' => ! $isActive])></span>{{ $profile->status?->label() ?? '-' }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-start gap-5">
                <!-- Kiri: Maklumat Rasmi (rujukan sahaja; dipinda oleh Urus Setia) -->
                <section aria-labelledby="tajuk-rasmi" class="flex-1 basis-[340px] max-w-full bg-white border border-slate-200 rounded-[18px] px-6 py-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="tajuk-rasmi" class="text-[17px] font-extrabold text-slate-900">Maklumat Rasmi</h2>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-slate-500">
                            <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>Rujukan sahaja
                        </span>
                    </div>
                    <dl class="mt-3 divide-y divide-slate-100">
                        @foreach ($details as $detail)
                            <div class="flex gap-3.5 py-3">
                                <span aria-hidden="true" class="w-9 h-9 shrink-0 rounded-[10px] flex items-center justify-center {{ $theme['soft'] }}">
                                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $detail['icon'] !!}</svg>
                                </span>
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold text-slate-500">{{ $detail['label'] }}</dt>
                                    <dd @class(['mt-0.5 text-[15px] text-slate-900 break-words', 'font-bold tabular-nums tracking-wide' => $detail['mono'] ?? false, 'font-semibold' => ! ($detail['mono'] ?? false)])>{{ $detail['value'] }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                    <div class="mt-3 flex gap-2.5 px-3.5 py-3 rounded-xl bg-yellow-50 border border-amber-200 text-[13px] leading-snug text-yellow-900">
                        <svg aria-hidden="true" class="w-[18px] h-[18px] shrink-0 mt-px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 8h.01M11 12h1v5h1" /></svg>
                        <span>Untuk mengemaskini maklumat di atas, sila hubungi Urus Setia.</span>
                    </div>
                </section>

                <!-- Kanan: medan yang boleh dikemas kini -->
                <div class="flex-[999_1_560px] min-w-0 space-y-5">
                    <livewire:profile.update-profile-information-form />
                    <livewire:profile.update-password-form />
                </div>
            </div>

            {{-- UM7A: fungsi padam akaun sendiri dibuang (komponen profile.delete-user-form dipadam).
                 Akaun yang tidak lagi diperlukan diurus melalui proses pentadbiran. --}}
        </div>
    </div>
</x-app-layout>
