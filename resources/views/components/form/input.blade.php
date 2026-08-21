@props(['label' => '', 'error' => null, 'required' => false, 'hint' => null])

<div>
    @if ($label)
        <label class="block text-xs font-medium text-gray-700">
            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <input {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500']) }}>

    @if ($hint)
        <span class="text-[10px] text-gray-400 block mt-0.5">{{ $hint }}</span>
    @endif

    @if ($error)
        <span class="text-red-500 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>