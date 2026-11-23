{{-- Editor teks Trix. HTML dibersihkan semula di pelayan sebelum disimpan.
     size="lg": gaya borang permohonan (lebih besar, warna fokus ikut peranan). --}}
@props(['label' => '', 'error' => null, 'required' => false, 'id' => null, 'hint' => null, 'size' => 'sm', 'focus' => 'focus:border-blue-500 focus:ring-blue-500'])

@php($id ??= 'trix-' . md5($attributes->wire('model')->value()))

<div @class(['space-y-1.5' => $size === 'lg'])>
    @if ($label)
        <label for="{{ $id }}" @class(['block', 'text-xs font-medium text-gray-700 mb-1' => $size !== 'lg', 'text-[13px] font-bold text-slate-700' => $size === 'lg'])>
            {{ $label }} @if($required)<span class="text-red-600">*</span>@endif
        </label>
    @endif

    <div wire:ignore
         x-data="{ value: @entangle($attributes->wire('model')) }"
         x-init="$nextTick(() => { if ($refs.editor.editor && value) { $refs.editor.editor.loadHTML(value) } })"
         x-on:trix-initialize="if (value) { $event.target.editor.loadHTML(value) }"
         x-on:trix-change="value = $event.target.value"
         x-on:trix-file-accept.prevent
         @class(['rich-text-lg' => $size === 'lg'])>
        <input id="{{ $id }}-input" type="hidden">
        <trix-editor x-ref="editor" id="{{ $id }}" input="{{ $id }}-input" @if ($hint) aria-describedby="{{ $id }}-hint" @endif
                     @class([
                         'trix-content block w-full bg-white',
                         'min-h-[10rem] rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500' => $size !== 'lg',
                         'min-h-[9rem] rounded-b-xl border-slate-300 text-[15px] leading-relaxed ' . $focus => $size === 'lg',
                         '!border-red-400' => $error,
                     ])></trix-editor>
    </div>

    @if ($hint)
        <span id="{{ $id }}-hint" class="block text-xs text-slate-500">{{ $hint }}</span>
    @endif

    @if ($error)
        <span class="text-red-600 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>
