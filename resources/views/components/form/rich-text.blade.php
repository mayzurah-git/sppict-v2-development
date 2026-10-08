{{-- Editor teks Trix. HTML dibersihkan semula di pelayan sebelum disimpan. --}}
@props(['label' => '', 'error' => null, 'required' => false, 'id' => null])

@php($id ??= 'trix-' . md5($attributes->wire('model')->value()))

<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-xs font-medium text-gray-700 mb-1">
            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div wire:ignore
         x-data="{ value: @entangle($attributes->wire('model')) }"
         x-init="$nextTick(() => { if ($refs.editor.editor && value) { $refs.editor.editor.loadHTML(value) } })"
         x-on:trix-initialize="if (value) { $event.target.editor.loadHTML(value) }"
         x-on:trix-change="value = $event.target.value"
         x-on:trix-file-accept.prevent>
        <input id="{{ $id }}-input" type="hidden">
        <trix-editor x-ref="editor" id="{{ $id }}" input="{{ $id }}-input"
                     class="trix-content block w-full min-h-[10rem] rounded-md border-gray-300 bg-white text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"></trix-editor>
    </div>

    @if ($error)
        <span class="text-red-500 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>
