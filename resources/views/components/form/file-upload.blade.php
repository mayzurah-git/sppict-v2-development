@props([
    'label' => '',
    'error' => null,
    'required' => false,
    'file' => null,          // Fail sementara (TemporaryUploadedFile) yang baru dipilih
    'existing' => null,      // Dokumen yang telah disimpan (ApplicationDocument)
    'applicationUuid' => null,
    'accept' => '',
    'formats' => '',         // Label format, cth: "PDF / DOCX"
    'target' => '',
    'linkClass' => 'text-blue-700', // Warna pautan ikut peranan (App\Support\RoleTheme 'link')
])

@php
    $hasFile = $existing || is_object($file);
    $inputId = 'upload-' . md5($target ?: $label);
@endphp

<div @class(['flex flex-col gap-3 p-[18px] rounded-2xl border', 'bg-green-50/60 border-green-200' => $hasFile, 'bg-slate-50 border-slate-200' => ! $hasFile, '!border-red-300' => $error])>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[15px] font-extrabold text-slate-900">{{ $label }} @if($required)<span class="text-red-600">*</span>@endif</p>
            <p class="mt-0.5 text-xs text-slate-500">{{ $formats }} · Maks. 25MB</p>
        </div>
        <span @class([
            'shrink-0 text-[11px] font-extrabold px-2 py-0.5 rounded-full',
            'bg-green-100 text-green-800' => $hasFile,
            'bg-red-100 text-red-800' => ! $hasFile && $required,
            'bg-slate-200 text-slate-700' => ! $hasFile && ! $required,
        ])>{{ $hasFile ? 'Dimuat naik' : ($required ? 'Wajib' : 'Pilihan') }}</span>
    </div>

    {{-- Dokumen yang telah disimpan --}}
    @if ($existing)
        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-white border border-green-200">
            <span aria-hidden="true" class="w-9 h-9 shrink-0 rounded-[9px] bg-green-100 text-green-700 flex items-center justify-center">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5M9 15l2 2 4-4" /></svg>
            </span>
            <span class="flex-1 min-w-0 flex flex-col">
                <span class="text-[11px] font-bold text-green-700">Dokumen Disimpan</span>
                <span class="text-sm font-bold text-slate-900 break-all">{{ $existing->file_name }}</span>
                <span class="text-xs text-slate-500">{{ number_format($existing->file_size_kb) }} KB</span>
            </span>
            <span class="flex items-center gap-1 shrink-0">
                <a href="{{ route('application.document', [$applicationUuid, $existing->uuid]) }}" target="_blank" class="inline-flex items-center min-h-9 px-2.5 rounded-lg text-[13px] font-bold hover:bg-slate-50 {{ $linkClass }}">Lihat</a>
                <button type="button"
                        wire:click="deleteDocument('{{ $existing->uuid }}')"
                        wire:confirm="Padam dokumen ini? Dokumen lama akan disimpan dalam rekod audit."
                        class="inline-flex items-center min-h-9 px-2.5 rounded-lg text-[13px] font-bold text-red-700 hover:bg-red-50">Padam</button>
            </span>
        </div>
    @endif

    {{-- Fail baharu yang dipilih (belum disimpan) --}}
    @if (is_object($file))
        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-white border border-slate-200">
            <span class="flex-1 min-w-0 flex flex-col">
                <span class="text-[11px] font-bold text-slate-600">{{ $existing ? 'Akan Menggantikan' : 'Fail Dipilih' }}</span>
                <span class="text-sm font-bold text-slate-900 break-all">{{ $file->getClientOriginalName() }}</span>
                <span class="text-xs text-slate-500">{{ number_format($file->getSize() / 1024, 1) }} KB</span>
            </span>
            <button type="button" wire:click="$set('{{ $target }}', null)" class="shrink-0 inline-flex items-center min-h-9 px-2.5 rounded-lg text-[13px] font-bold text-slate-600 hover:bg-slate-100">Batal</button>
        </div>
        <p class="text-xs text-slate-500">Fail akan disimpan apabila anda menekan Simpan atau Hantar Permohonan.</p>
    @endif

    <label for="{{ $inputId }}" class="flex flex-col items-center gap-1.5 px-3 py-5 rounded-xl border-2 border-dashed border-slate-300 bg-white text-center cursor-pointer hover:border-slate-400 focus-within:ring-2 focus-within:ring-slate-400 {{ $linkClass }}">
        <svg aria-hidden="true" class="w-[26px] h-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" /></svg>
        <span class="text-sm font-extrabold">{{ $existing ? 'Pilih fail baharu untuk menggantikan' : 'Pilih fail untuk dimuat naik' }}</span>
        <span class="text-xs text-slate-500">{{ $formats }} · Maks. 25MB</span>
        <input id="{{ $inputId }}" type="file" accept="{{ $accept }}" {{ $attributes->merge(['class' => 'sr-only']) }}>
    </label>

    @if ($target)
        <div wire:loading wire:target="{{ $target }}" class="text-xs font-semibold text-slate-600">
            Memuat naik &amp; mengesahkan fail...
        </div>
    @endif

    @if ($error)
        <span class="text-red-600 text-xs block">{{ $error }}</span>
    @endif
</div>
