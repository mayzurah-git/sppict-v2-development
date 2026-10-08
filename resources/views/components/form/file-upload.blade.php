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
])

<div class="p-4 bg-gray-50 border rounded-lg space-y-3">
    <div class="flex justify-between items-center">
        <label class="block text-sm font-medium text-gray-700">
            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
        </label>
        <span class="text-[11px] bg-slate-100 text-slate-600 font-medium px-2 py-0.5 rounded">{{ $formats }} · Maks. 25MB</span>
    </div>

    {{-- Dokumen yang telah disimpan --}}
    @if ($existing)
        <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md text-xs gap-3">
            <div class="min-w-0">
                <span class="text-green-600 font-bold">✓ Dokumen Disimpan:</span>
                <span class="font-semibold text-gray-800 break-all">{{ $existing->file_name }}</span>
                <span class="text-gray-500">({{ number_format($existing->file_size_kb) }} KB)</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('application.document', [$applicationUuid, $existing->uuid]) }}" target="_blank" class="px-2.5 py-1 bg-white border border-gray-200 text-blue-600 hover:bg-blue-50 font-bold rounded">
                    Lihat
                </a>
                <button type="button"
                        wire:click="deleteDocument('{{ $existing->uuid }}')"
                        wire:confirm="Padam dokumen ini? Dokumen lama akan disimpan dalam rekod audit."
                        class="px-2.5 py-1 bg-white border border-red-200 text-red-600 hover:bg-red-50 font-bold rounded">
                    Padam
                </button>
            </div>
        </div>
    @endif

    {{-- Fail baharu yang dipilih (belum disimpan) --}}
    @if (is_object($file))
        <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-md text-xs gap-3">
            <div class="min-w-0">
                <span class="text-blue-700 font-bold">{{ $existing ? '↻ Akan Menggantikan:' : '✓ Fail Dipilih:' }}</span>
                <span class="font-semibold text-gray-800 break-all">{{ $file->getClientOriginalName() }}</span>
                <span class="text-gray-500">({{ number_format($file->getSize() / 1024, 1) }} KB)</span>
            </div>
            <button type="button" wire:click="$set('{{ $target }}', null)" class="px-2.5 py-1 bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 font-bold rounded shrink-0">
                Batal
            </button>
        </div>
        <p class="text-[11px] text-gray-500 italic">Fail akan disimpan apabila anda menekan Simpan Draf atau Hantar Permohonan.</p>
    @elseif ($existing)
        <p class="text-[11px] text-gray-500 italic">Pilih fail baharu di bawah sekiranya anda ingin menggantikan dokumen sedia ada ini:</p>
    @endif

    <input type="file" accept="{{ $accept }}" {{ $attributes->merge(['class' => 'block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer']) }}>

    @if ($target)
        <div wire:loading wire:target="{{ $target }}" class="text-xs text-blue-600 font-medium">
            ⏳ Memuat naik & mengesahkan fail...
        </div>
    @endif

    @if ($error)
        <span class="text-red-500 text-xs block">{{ $error }}</span>
    @endif
</div>
