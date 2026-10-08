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

<div class="flex items-center justify-between">
    <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
        {{ __('Dashboard') }}
    </x-nav-link>

    @hasrole('pentadbir_urus_setia')
        <x-nav-link :href="route('admin.agencies')" :active="request()->routeIs('admin.agencies')">
            {{ __('Pengurusan Agensi') }}
        </x-nav-link>

        <div class="flex items-center">
            <x-dropdown align="left" width="w-64">
                <x-slot name="trigger">
                    <button type="button" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('meetings.*') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        {{ __('Pengurusan Mesyuarat') }}
                        <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25 12 15.75 4.5 8.25" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('meetings.index')">{{ __('Senarai Mesyuarat') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('meetings.create')">{{ __('Daftar Mesyuarat') }}</x-dropdown-link>
                    <div class="border-t border-gray-100 my-1"></div>
                    <x-dropdown-link :href="route('meetings.names')">{{ __('Selenggara Nama Mesyuarat') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('meetings.numbers')">{{ __('Selenggara Bilangan Mesyuarat') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('meetings.years')">{{ __('Selenggara Tahun Mesyuarat') }}</x-dropdown-link>
                </x-slot>
            </x-dropdown>
        </div>

        <div class="flex items-center">
            <x-dropdown align="left" width="w-64">
                <x-slot name="trigger">
                    <button type="button" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('master-data.*') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        {{ __('Master Data') }}
                        <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25 12 15.75 4.5 8.25" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('master-data.application-categories')">{{ __('Kategori Permohonan') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('master-data.project-categories')">{{ __('Kategori Projek') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('master-data.procurement-types')">{{ __('Jenis Perolehan') }}</x-dropdown-link>
                    <x-dropdown-link :href="route('master-data.procurement-methods')">{{ __('Kaedah Perolehan') }}</x-dropdown-link>
                </x-slot>
            </x-dropdown>
        </div>
    @endhasrole

    @hasrole('superadmin')
        <x-nav-link :href="route('admin.users')" :active="request()->routeIs('admin.users')">
            {{ __('Pengurusan Pengguna') }}
        </x-nav-link>

        <x-nav-link :href="route('admin.positions')" :active="request()->routeIs('admin.positions')">
            {{ __('Jawatan') }}
        </x-nav-link>

        <x-nav-link :href="route('admin.service-schemes')" :active="request()->routeIs('admin.service-schemes')">
            {{ __('Skim Perkhidmatan') }}
        </x-nav-link>

        <x-nav-link :href="route('admin.grades')" :active="request()->routeIs('admin.grades')">
            {{ __('Gred Jawatan') }}
        </x-nav-link>

        <x-nav-link :href="route('admin.audit-logs')" :active="request()->routeIs('admin.audit-logs')">
            {{ __('Log Audit') }}
        </x-nav-link>
    @endhasrole

    @if (! auth()->user()->hasAnyRole(['superadmin', 'pentadbir_urus_setia']))
        <x-nav-link :href="url('/application/new')" :active="request()->is('application/new')">
            {{ __('Permohonan Baru') }}
        </x-nav-link>
    @endif

    </div>

    <div class="hidden sm:flex sm:items-center sm:ms-6">
        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                    <span>{{ Auth::user()->name }}</span>
                    <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25 12 15.75 4.5 8.25" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-dropdown-link>

                <button wire:click="logout" type="button" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition duration-150 ease-in-out">
                    {{ __('Log Out') }}
                </button>
            </x-slot>
        </x-dropdown>
    </div>
</div>
