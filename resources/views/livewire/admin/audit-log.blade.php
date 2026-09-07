<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Log Audit</h2>
            <p class="text-xs text-slate-500 mt-0.5">Rekod perubahan data dan tindakan yang direkodkan oleh sistem.</p>
        </div>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari acara, model atau URL..." class="w-full md:w-80 text-sm border-slate-200 rounded-xl focus:border-blue-500 focus:ring-blue-500">
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto">
        <table class="min-w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 uppercase tracking-wide">
                <tr><th class="px-5 py-4 font-bold">Tarikh</th><th class="px-5 py-4 font-bold">Pengguna</th><th class="px-5 py-4 font-bold">Tindakan</th><th class="px-5 py-4 font-bold">Rekod</th><th class="px-5 py-4 font-bold">IP</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @forelse ($audits as $audit)
                    <tr>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $audit->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-4">{{ $audit->user?->name ?? 'Sistem / tidak diketahui' }}</td>
                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 font-bold uppercase">{{ $audit->event }}</span></td>
                        <td class="px-5 py-4">{{ class_basename($audit->auditable_type) }} #{{ $audit->auditable_id }}</td>
                        <td class="px-5 py-4 font-mono">{{ $audit->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">Tiada rekod audit ditemui.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $audits->links() }}</div>
</div>
