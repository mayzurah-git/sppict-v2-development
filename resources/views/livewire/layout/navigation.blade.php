<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $user = Auth::user();

    // Menu ikut peranan: satu sumber untuk paparan desktop dan telefon.
    // Item dengan `children` dipaparkan sebagai dropdown; `null` dalam `children` ialah garis pemisah.
    $menu = [
        ['label' => 'Papan Pemuka', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
    ];

    // Urus Setia: menu dikumpulkan supaya bar menu kekal satu baris.
    if ($user->hasRole('pentadbir_urus_setia')) {
        $menu[] = ['label' => 'Permohonan', 'active' => request()->routeIs('pre-jtict.*', 'jtict.*') || request()->is('application/new'), 'children' => [
            ['label' => 'Semakan Permohonan', 'href' => route('dashboard', ['status' => \App\Enums\ApplicationStatus::Submitted->value]) . '#senarai-permohonan'],
            ['label' => 'Keputusan Pra JTICT', 'href' => route('pre-jtict.index')], // PP4.2
            ['label' => 'Keputusan JTICT', 'href' => route('jtict.index')], // PP4.3A
            ...($user->can('create', \App\Models\Application::class) ? [null, ['label' => 'Permohonan Baharu', 'href' => url('/application/new')]] : []),
        ]];
        $menu[] = ['label' => 'Mesyuarat', 'active' => request()->routeIs('meetings.*'), 'children' => [
            ['label' => 'Senarai Mesyuarat', 'href' => route('meetings.index')],
            ['label' => 'Daftar Mesyuarat', 'href' => route('meetings.create')],
            null,
            ['label' => 'Selenggara Nama Mesyuarat', 'href' => route('meetings.names')],
            ['label' => 'Selenggara Bilangan Mesyuarat', 'href' => route('meetings.numbers')],
            ['label' => 'Selenggara Tahun Mesyuarat', 'href' => route('meetings.years')],
        ]];
        $menu[] = ['label' => 'Pengguna', 'active' => request()->routeIs('users.*'), 'children' => [
            ['label' => 'Senarai Pengguna', 'href' => route('users.index')],
            ['label' => 'Daftar Pengguna', 'href' => route('users.create')],
            ['label' => 'Permohonan Pendaftaran', 'href' => route('users.requests')],
        ]];
        $menu[] = ['label' => 'Tetapan', 'active' => request()->routeIs('admin.agencies', 'master-data.*'), 'children' => [
            ['label' => 'Pengurusan Agensi', 'href' => route('admin.agencies')],
            null,
            ['label' => 'Kategori Permohonan', 'href' => route('master-data.application-categories')],
            ['label' => 'Kategori Projek', 'href' => route('master-data.project-categories')],
            ['label' => 'Jenis Perolehan', 'href' => route('master-data.procurement-types')],
            ['label' => 'Kaedah Perolehan', 'href' => route('master-data.procurement-methods')],
        ]];
    }

    if ($user->hasRole('superadmin')) {
        $menu[] = ['label' => 'Pengurusan Pengguna', 'href' => route('admin.users'), 'active' => request()->routeIs('admin.users')];
        $menu[] = ['label' => 'Jawatan', 'href' => route('admin.positions'), 'active' => request()->routeIs('admin.positions')];
        $menu[] = ['label' => 'Skim Perkhidmatan', 'href' => route('admin.service-schemes'), 'active' => request()->routeIs('admin.service-schemes')];
        $menu[] = ['label' => 'Gred Jawatan', 'href' => route('admin.grades'), 'active' => request()->routeIs('admin.grades')];
        $menu[] = ['label' => 'Log Audit', 'href' => route('admin.audit-logs'), 'active' => request()->routeIs('admin.audit-logs')];
    }

    // Pengguna Biasa: Permohonan ▾ (Senarai Permohonan | Permohonan Baru) dan Kemajuan Projek.
    // UM7A: Permohonan Baru ikut ApplicationPolicy::create; kawalan sebenar di route/komponen/servis.
    if ($user->hasRole('pengguna_biasa')) {
        $menu[] = ['label' => 'Permohonan', 'active' => request()->routeIs('applications.index', 'application.*'), 'children' => [
            ['label' => 'Senarai Permohonan', 'hint' => 'Semua permohonan anda, termasuk draf', 'href' => route('applications.index')],
            ...($user->can('create', \App\Models\Application::class) ? [['label' => 'Permohonan Baru', 'hint' => 'Daftar permohonan projek ICT baharu', 'href' => route('application.create')]] : []),
        ]];
        $menu[] = ['label' => 'Kemajuan Projek', 'href' => route('dashboard') . '#kemajuan-projek', 'active' => false];
    }

    $roleLabels = [
        'superadmin' => 'Superadmin',
        'pentadbir_urus_setia' => 'Pentadbir Urus Setia',
        'pengurusan' => 'Pengurusan',
        'pengguna_biasa' => 'Pengguna Biasa',
    ];
    $roleLabel = $roleLabels[$user->getRoleNames()->first()] ?? null;
    if ($roleLabel && $user->hasRole('pengguna_biasa') && $user->agency?->code) {
        $roleLabel .= ' · ' . $user->agency->code;
    }
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');

    // Warna bar menu ikut peranan (App\Support\RoleTheme).
    $theme = \App\Support\RoleTheme::for($user);
    $topLink = 'inline-flex items-center gap-1.5 h-14 px-3.5 border-b-[3px] text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-inset ' . $theme['focus'];
    $topOn = $theme['active'];
    $topOff = $theme['idle'];
@endphp

<header class="{{ $theme['bar'] }} text-white" x-data="{ mobileOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center gap-6">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-h-14 shrink-0">
            <img src="{{ asset('images/logo-jata-n9.png') }}" alt="Jata Negeri Sembilan" class="h-[34px] w-auto" />
            <span class="text-lg font-black tracking-tight">SPPICT <span class="{{ $theme['accent'] }}">v2</span></span>
        </a>

        <!-- Menu desktop -->
        <nav aria-label="Menu utama" class="hidden lg:flex flex-wrap flex-1 min-w-0">
            @foreach ($menu as $item)
                @isset($item['children'])
                    <x-dropdown align="left" width="w-64">
                        <x-slot name="trigger">
                            <button type="button" @class([$topLink, $topOn => $item['active'], $topOff => ! $item['active']])>
                                {{ $item['label'] }}
                                <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @foreach ($item['children'] as $child)
                                @if ($child)
                                    <x-dropdown-link :href="$child['href']">
                                        @isset($child['hint'])
                                            <span class="block font-bold text-slate-900">{{ $child['label'] }}</span>
                                            <span class="block text-xs text-slate-500">{{ $child['hint'] }}</span>
                                        @else
                                            {{ $child['label'] }}
                                        @endisset
                                    </x-dropdown-link>
                                @else
                                    <div class="border-t border-gray-100 my-1"></div>
                                @endif
                            @endforeach
                        </x-slot>
                    </x-dropdown>
                @else
                    <a href="{{ $item['href'] }}" @class([$topLink, $topOn => $item['active'], $topOff => ! $item['active']]) @if ($item['active']) aria-current="page" @endif>
                        {{ $item['label'] }}
                    </a>
                @endisset
            @endforeach
        </nav>

        <!-- Menu akaun (desktop) -->
        <div class="hidden lg:flex ms-auto">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button" class="flex items-center gap-2.5 min-h-14 text-white focus:outline-none focus-visible:ring-2 rounded {{ $theme['focus'] }}">
                        <span class="w-[34px] h-[34px] rounded-full text-[13px] font-extrabold flex items-center justify-center {{ $theme['avatar'] }}">{{ $initials }}</span>
                        <span class="flex flex-col items-start gap-0.5 text-left min-w-0">
                            <span class="text-sm font-bold max-w-[200px] truncate" title="{{ $user->name }}">{{ $user->name }}</span>
                            @if ($roleLabel)
                                <span class="text-xs {{ $theme['muted'] }}">{{ $roleLabel }}</span>
                            @endif
                        </span>
                        <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile')" wire:navigate>
                        {{ __('Profil') }}
                    </x-dropdown-link>

                    <button wire:click="logout" type="button" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition duration-150 ease-in-out">
                        {{ __('Log Keluar') }}
                    </button>
                </x-slot>
            </x-dropdown>
        </div>

        <!-- Butang menu telefon -->
        <button type="button" @click="mobileOpen = ! mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="menu-telefon" aria-label="Buka menu"
            class="lg:hidden ms-auto w-11 h-11 flex items-center justify-center rounded-lg focus:outline-none focus-visible:ring-2 {{ $theme['idle'] }} {{ $theme['focus'] }}">
            <svg x-show="! mobileOpen" aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            <svg x-show="mobileOpen" style="display: none" aria-hidden="true" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
    </div>

    <!-- Menu telefon -->
    <div id="menu-telefon" x-show="mobileOpen" style="display: none" class="lg:hidden border-t border-white/10">
        <nav aria-label="Menu utama (telefon)" class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex flex-col">
            @foreach ($menu as $item)
                @isset($item['children'])
                    <p class="pt-3 pb-1 px-3 text-xs font-extrabold uppercase tracking-[0.12em] {{ $theme['accent'] }}">{{ $item['label'] }}</p>
                    @foreach (array_filter($item['children']) as $child)
                        <a href="{{ $child['href'] }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold {{ $theme['mobileIdle'] }}">{{ $child['label'] }}</a>
                    @endforeach
                @else
                    <a href="{{ $item['href'] }}" @class(['px-3 py-2.5 rounded-lg text-sm font-semibold', 'bg-white/10 text-white' => $item['active'], $theme['mobileIdle'] => ! $item['active']]) @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endisset
            @endforeach

            <div class="mt-3 pt-3 border-t border-white/10 flex flex-col">
                <p class="px-3 pb-2 text-sm">
                    <span class="block font-bold">{{ $user->name }}</span>
                    @if ($roleLabel)
                        <span class="text-xs {{ $theme['muted'] }}">{{ $roleLabel }}</span>
                    @endif
                </p>
                <a href="{{ route('profile') }}" wire:navigate class="px-3 py-2.5 rounded-lg text-sm font-semibold {{ $theme['mobileIdle'] }}">{{ __('Profil') }}</a>
                <button wire:click="logout" type="button" class="px-3 py-2.5 rounded-lg text-start text-sm font-semibold {{ $theme['mobileIdle'] }}">{{ __('Log Keluar') }}</button>
            </div>
        </nav>
    </div>
</header>
