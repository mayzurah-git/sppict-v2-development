<x-app-layout>
    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Tajuk & Butang Permohonan Baharu -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Senarai Permohonan Projek ICT</h2>
                    <p class="mt-1 text-sm text-slate-500">Senarai status permohonan projek agensi anda di bawah SPPICT v2.</p>
                </div>
                <a href="{{ route('application.create') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    + Permohonan Baharu
                </a>
            </div>

            <!-- Panggil Komponen Livewire List -->
            <livewire:application-list />
        </div>
    </div>
</x-app-layout>
