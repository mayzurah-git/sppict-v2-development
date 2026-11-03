{{-- Status tempoh penerimaan permohonan Mesyuarat JTICT. $meeting: Meeting|null (mesyuarat yang sedang dibuka).
     PP2: permohonan Tidak Lengkap tidak lagi dihantar ke mesyuarat yang telah ditutup. --}}
@if ($meeting)
    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800">
        <p class="font-bold">✅ Permohonan dibuka untuk {{ $meeting->label() }}</p>
        <p class="mt-0.5">
            @if ($meeting->submission_closes_at)
                Tarikh tutup permohonan: <span class="font-bold">{{ $meeting->submission_closes_at->format('d/m/Y') }}</span>
            @endif
            @if ($meeting->meeting_date)
                • Tarikh mesyuarat: {{ $meeting->meeting_date->format('d/m/Y') }}
            @endif
        </p>
    </div>
@else
    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
        <p class="font-bold">⏸ Tiada Mesyuarat JTICT yang dibuka buat masa ini</p>
        <p class="mt-0.5">Permohonan belum boleh dihantar. Anda boleh menyimpan permohonan sebagai draf dan menghantarnya apabila Mesyuarat JTICT seterusnya dibuka oleh Urus Setia.</p>
    </div>
@endif
