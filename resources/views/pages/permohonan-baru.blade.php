<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Permohonan Projek Baru') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <!-- Panggil Komponent Livewire Volt -->
        <livewire:application-form />
    </div>
</x-app-layout>