{{-- Krankenschein-Status einer Krankmeldung (/sick_notes) --}}
@php $st = $service->status($absence); @endphp
<span class="abw-badge {{ $statusKlasse[$st] }}">{{ \App\Services\KrankmeldungService::STATUS[$st] }}</span>
@if($st === 'liegt_vor')
    <div class="text-xs text-gray-500 mt-0.5">erfasst am {{ $absence->sick_note_date->format('d.m.Y') }}</div>
@elseif(in_array($st, ['fehlt', 'ausstehend'], true))
    <div class="text-xs text-gray-500 mt-0.5">
        fällig ab {{ $service->faelligAb($absence)->format('d.m.Y') }}{{ !$service->erreichtSchwelle($absence) ? ' (verlangt)' : '' }}
    </div>
@elseif($service->istKurzerkrankung($absence))
    <div class="text-xs text-gray-500 mt-0.5">Kurzerkrankung</div>
@endif
