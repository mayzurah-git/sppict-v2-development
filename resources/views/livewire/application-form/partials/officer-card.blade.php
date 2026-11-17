@if ($officer)
    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 px-3.5 py-3 rounded-xl bg-slate-50 border border-slate-200 text-[13px]">
        <div>
            <dt class="text-xs text-slate-500">Jawatan &amp; Gred</dt>
            <dd class="font-semibold text-slate-900">{{ $officer->getAttribute('position') ?: ($officer->positionRelation?->title ?? '-') }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="text-xs text-slate-500">E-mel Rasmi</dt>
            <dd class="font-semibold text-slate-900 break-all">{{ $officer->email }}</dd>
        </div>
        <div>
            <dt class="text-xs text-slate-500">No. Telefon</dt>
            <dd class="font-semibold text-slate-900">{{ $officer->phone_number ?: '-' }}</dd>
        </div>
    </dl>
@endif
