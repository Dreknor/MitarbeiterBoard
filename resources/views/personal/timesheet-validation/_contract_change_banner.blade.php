{{-- Aufgabe 5.2: Prominentes Hinweisfeld bei erkannten Vertragsänderungen --}}
@php
    $isRetroactive = $anomaly->rule_type->value === 'RETROACTIVE_CONTRACT_CHANGE';
@endphp
<div class="rounded-lg p-4 mb-4 border {{ $isRetroactive ? 'bg-orange-50 border-orange-200' : 'bg-yellow-50 border-yellow-200' }}">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-2">
            <span class="text-lg">{{ $isRetroactive ? '⏪' : '📄' }}</span>
            <div>
                <strong class="{{ $isRetroactive ? 'text-orange-800' : 'text-yellow-800' }}">
                    {{ $isRetroactive ? 'Rückwirkende Vertragsänderung' : 'Vertragsanpassung im Prüfmonat' }}
                </strong>
                <p class="{{ $isRetroactive ? 'text-orange-700' : 'text-yellow-700' }} mt-1 text-sm">
                    {{ $anomaly->description }}
                </p>
            </div>
        </div>
        @can('resolve timesheet anomalies')
        <form method="POST" action="{{ route('personal.timesheet-validation.resolve', $anomaly->id) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn-personal-primary text-xs">Vertragsänderung bestätigen</button>
        </form>
        @endcan
    </div>
</div>

