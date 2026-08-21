@props(['label' => '', 'error' => null, 'required' => false])

<div>
    @if ($label)
        <label class="block text-xs font-medium text-gray-700">
            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <select {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500']) }}>
        {{ $slot }}
    </select>

    @if ($error)
        <span class="text-red-500 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>