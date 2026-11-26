{{-- Plan-Karte für die Übersicht --}}
@php
    $heute = now()->startOfDay();
    $aktuell = $plan->gueltig_von && $plan->gueltig_bis && $plan->gueltig_von->lte($heute) && $plan->gueltig_bis->gte($heute);
    $art = $plan->is_vorlage ? 'is-vorlage' : ($plan->isSchuelerplan() ? 'is-kinderplan' : 'is-klassenplan');
    $badge = $plan->is_vorlage ? 'wp-badge-violet' : ($plan->isSchuelerplan() ? 'wp-badge-amber' : 'wp-badge-green');
@endphp
<article class="wp-plan {{ $art }} {{ $aktuell ? 'is-aktuell' : '' }}">
    <a href="{{ route('wp.edit', $plan) }}" class="flex-1 px-4 pt-3 pb-2 block">
        <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
            <span class="wp-badge {{ $badge }}">{{ $plan->typ }}</span>
            @if($aktuell)
                <span class="wp-badge wp-badge-blue">diese Woche</span>
            @endif
            @if($plan->ablage_status === 'fehler')
                <span class="wp-badge wp-badge-red" title="{{ $plan->ablage_fehler }}">Ablage fehlgeschlagen</span>
            @elseif($plan->ablage_status === 'abgelegt')
                <span class="wp-badge wp-badge-outline" title="Abgelegt {{ $plan->abgelegt_at?->format('d.m.Y H:i') }}">☁ Nextcloud</span>
            @endif
        </div>
        <h3 class="text-base font-bold text-gray-900 leading-snug">{{ $plan->name }}</h3>
        <p class="mt-1 text-sm text-gray-500">
            @if($plan->klasse) Klasse {{ $plan->klasse->name }} · @endif
            @if($plan->schueler) {{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }} · @endif
            {{ $plan->zeitraum }}
        </p>
        <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
            <span>{{ $plan->planFaecher->count() }} Fächer</span>
            <span>{{ $plan->aufgaben_count ?? 0 }} Aufgaben</span>
            @if($plan->dateien->count())<span>📎 {{ $plan->dateien->count() }}</span>@endif
            @if($plan->isKlassenplan() && $plan->kinderPlaene->count())<span>👤 {{ $plan->kinderPlaene->count() }} Kinderpläne</span>@endif
        </p>
    </a>
    <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-100 px-3 py-2">
        <a href="{{ route('wp.edit', $plan) }}" class="wp-btn wp-btn-secondary wp-btn-sm">Öffnen</a>
        <a href="{{ route('wp.export.pdf', $plan) }}" target="_blank" class="wp-btn wp-btn-ghost wp-btn-sm">PDF</a>
        @can('trackProgress', $plan)
            <a href="{{ route('wp.fortschritt', $plan) }}" class="wp-btn wp-btn-ghost wp-btn-sm">✓ Fortschritt</a>
        @endcan
    </div>
</article>
