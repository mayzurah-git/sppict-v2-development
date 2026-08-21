@props(['label' => '', 'error' => null, 'required' => false, 'file' => null, 'target' => ''])

<div class="p-4 bg-gray-50 border rounded-lg space-y-3">
    <label class="block text-sm font-medium text-gray-700">
        {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
    </label>

    @if ($file)
        <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md text-xs">
            <div class="flex items-center space-x-2">
                <span class="text-green-600 font-bold">✓ Dokumen Bersedia:</span>
                <span class="font-semibold text-gray-800">
                    {{ is_object($file) ? $file->getClientOriginalName() : 'Dokumen Telah Dimuat Naik' }}
                </span>
                @if (is_object($file))
                    <span class="text-gray-500">({{ round($file->getSize() / 1024, 1) }} KB)</span>
                @endif
            </div>
            <span class="text-[11px] bg-green-100 text-green-800 font-medium px-2 py-0.5 rounded">PDF</span>
        </div>
        <p class="text-[11px] text-gray-500 italic">Pilih fail baharu di bawah sekiranya anda ingin menggantikan dokumen sedia ada ini:</p>
    @endif

    <input type="file" accept="application/pdf" {{ $attributes->merge(['class' => 'block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer']) }}>

    @if ($target)
        <div wire:loading wire:target="{{ $target }}" class="text-xs text-blue-600 font-medium">
            ⏳ Memproses & mengesahkan fail PDF...
        </div>
    @endif

    @if ($error)
        <span class="text-red-500 text-xs block">{{ $error }}</span>
    @endif
</div>