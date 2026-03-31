@can('view contracts')
@if(isset($expiringContracts) && $expiringContracts->count() > 0)
<div class="personal-wrapper">
    <div class="personal-card">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                <span class="text-yellow-500">⚠️</span> Auslaufende Verträge
            </h3>
            <span class="badge-yellow">{{ $expiringContracts->count() }}</span>
        </div>
        <ul class="space-y-2 text-sm">
            @foreach($expiringContracts->take(5) as $emp)
            <li class="flex items-center justify-between py-1 border-b border-gray-50 last:border-0">
                <div>
                    <span class="font-medium">{{ $emp->employe?->name ?? '—' }}</span>
                    <span class="text-gray-400 ml-1">({{ $emp->department?->name ?? '—' }})</span>
                </div>
                <span class="{{ $emp->end->diffInDays(now()) <= 30 ? 'text-red-600 font-semibold' : 'text-yellow-600' }}">
                    {{ $emp->end->format('d.m.Y') }}
                </span>
            </li>
            @endforeach
        </ul>
        @if($expiringContracts->count() > 5)
        <p class="text-xs text-gray-400 mt-2">und {{ $expiringContracts->count() - 5 }} weitere…</p>
        @endif
    </div>
</div>
@endif
@endcan

