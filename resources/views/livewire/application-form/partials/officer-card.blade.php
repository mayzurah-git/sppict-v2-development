@if ($officer)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs bg-white border border-gray-200 rounded-md p-3">
        <div>
            <p class="text-[11px] text-gray-500">Jawatan & Gred</p>
            <p class="font-semibold text-gray-800">{{ $officer->getAttribute('position') ?: ($officer->positionRelation?->title ?? '-') }}</p>
        </div>
        <div>
            <p class="text-[11px] text-gray-500">E-mel Rasmi</p>
            <p class="font-semibold text-gray-800">{{ $officer->email }}</p>
        </div>
        <div>
            <p class="text-[11px] text-gray-500">No. Telefon</p>
            <p class="font-semibold text-gray-800">{{ $officer->phone_number ?: '-' }}</p>
        </div>
    </div>
@endif
