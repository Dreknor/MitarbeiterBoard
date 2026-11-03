{{-- Kennzeichnung Änderungsvertrag / interner Wechsel inkl. Beschreibung und ersetzter Anstellung --}}
@if($employment->is_amendment || $employment->is_internal_transfer)
<div class="flex flex-wrap items-center gap-2 text-xs {{ $class ?? 'mt-1' }}">
    @if($employment->is_amendment)
        <span class="badge-blue">Änderungsvertrag</span>
    @endif
    @if($employment->is_internal_transfer)
        <span class="badge-blue">Interner Wechsel</span>
    @endif
    @if($employment->amendment_description)
        <span class="text-gray-700">{{ $employment->amendment_description }}</span>
    @endif
    @if($employment->replacedEmployment)
        <span class="text-gray-500">
            · ersetzt {{ $employment->replacedEmployment->department?->name ?? 'Anstellung' }}
            ({{ $employment->replacedEmployment->hours }}h, seit {{ $employment->replacedEmployment->start?->format('d.m.Y') }})
        </span>
    @endif
</div>
@endif
