<div class="max-w-5xl mx-auto p-6 space-y-6">
    <!-- Header & Navigasi -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <div class="flex items-center space-x-2">
                <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-md">{{ $application->reference_number }}</span>
                <span class="text-xs text-slate-400">• Dihantar pada {{ $application->created_at ? $application->created_at->format('d/m/Y h:i A') : '-' }}</span>
            </div>
            <h2 class="text-xl font-black text-slate-800 mt-2">{{ $application->title }}</h2>
        </div>
        <a href="{{ url('/dashboard') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            ← Kembali ke Dashboard
        </a>
    </div>

    <!-- Ringkasan Fasa 1 & 2 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Maklumat Am Projek</h3>
            <div>
                <p class="text-[11px] text-slate-400">Kategori Projek</p>
                <p class="text-xs font-bold text-slate-800">{{ $application->project_category }}</p>
            </div>
            <div>
                <p class="text-[11px] text-slate-400">Ringkasan Projek</p>
                <p class="text-xs text-slate-700 leading-relaxed mt-1">{{ $application->description }}</p>
            </div>
            <div>
                <p class="text-[11px] text-slate-400">Objektif Utama</p>
                <p class="text-xs text-slate-700 leading-relaxed mt-1">{{ $application->objectives }}</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Maklumat Perolehan & Kos</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[11px] text-slate-400">Jenis Perolehan</p>
                    <p class="text-xs font-bold text-slate-800">{{ $application->procurement_type }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Kaedah Perolehan</p>
                    <p class="text-xs font-bold text-slate-800">{{ $application->procurement_method }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Anggaran Kos Projek</p>
                    <p class="text-sm font-black text-emerald-600">RM {{ number_format($application->estimated_cost, 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400">Tempoh Pelaksanaan</p>
                    <p class="text-xs font-bold text-slate-800">{{ $application->expected_duration_months }} Bulan</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Perincian Item Projek (Fasa 3) -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Perincian Item Perolehan</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold">
                    <tr>
                        <th class="p-2.5">Kategori</th>
                        <th class="p-2.5">Spesifikasi Teknikal</th>
                        <th class="p-2.5 text-center">Kuantiti</th>
                        <th class="p-2.5 text-right">Kos Seunit (RM)</th>
                        <th class="p-2.5 text-right">Jumlah (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($application->details as $detail)
                        <tr>
                            <td class="p-2.5 font-semibold text-slate-700">{{ $detail->item_category }}</td>
                            <td class="p-2.5 text-slate-600">{{ $detail->technical_specifications }}</td>
                            <td class="p-2.5 text-center font-bold">{{ $detail->unit_quantity }}</td>
                            <td class="p-2.5 text-right">{{ number_format($detail->unit_cost, 2) }}</td>
                            <td class="p-2.5 text-right font-bold text-slate-800">{{ number_format($detail->total_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Dokumen Sokongan & Pegawai (Fasa 4 & 5) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Dokumen Sokongan</h3>
            @forelse ($application->documents as $doc)
                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between text-xs">
                    <div>
                        <p class="font-bold text-slate-800">{{ $doc->document_type === 'PROPOSAL_PAPER' ? 'Kertas Cadangan' : 'Slaid Pembentangan' }}</p>
                        <p class="text-[10px] text-slate-400">{{ $doc->file_name }} ({{ $doc->file_size_kb }} KB)</p>
                    </div>
                    <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="px-3 py-1 bg-blue-50 text-blue-600 font-bold rounded-lg hover:bg-blue-100 transition">
                        📥 Buka PDF
                    </a>
                </div>
            @empty
                <p class="text-xs text-slate-400">Tiada dokumen dimuat naik.</p>
            @endforelse
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b pb-2">Pegawai Bertanggungjawab</h3>
            <div class="text-xs space-y-1">
                <p class="font-bold text-slate-800">{{ $application->primary_officer_name }}</p>
                <p class="text-slate-500">{{ $application->primary_officer_position }}</p>
                <p class="text-slate-500">📧 {{ $application->primary_officer_email }} | 📞 {{ $application->primary_officer_phone }}</p>
            </div>
        </div>
    </div>
</div>