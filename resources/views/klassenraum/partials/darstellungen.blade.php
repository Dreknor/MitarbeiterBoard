{{-- Auswahl der Zeit-Darstellung (Timer, Arbeitsphasen) – Schlüssel wie in resources/js/klassenraum/shared/zeitanzeige.js --}}
<div class="tmr-darstellungen" role="radiogroup" aria-label="Darstellung der Restzeit">
    @foreach(['scheibe' => ['Zeitscheibe', 'fas fa-chart-pie'], 'sanduhr' => ['Sanduhr', 'fas fa-hourglass-half'], 'kerze' => ['Kerze', 'fas fa-fire'], 'raupe' => ['Raupe', 'fas fa-bug']] as $art => [$bezeichnung, $icon])
        <button type="button" class="tmr-chip tmr-darstellung" role="radio" aria-checked="false" data-darstellung="{{ $art }}">
            <i class="{{ $icon }}"></i>&nbsp;{{ $bezeichnung }}
        </button>
    @endforeach
</div>
