{{-- Mini-Rastervorschau einer Vorlage ($v = normalisierte Definition, nur Hauptfelder) --}}
@php
    $miniHaupt = array_values(array_filter($v['bereiche'] ?? [], fn ($b) => ($b['eltern'] ?? null) === null));
    $miniSpalten = \App\Support\ErgebnisboardVorlagen::spaltenAnzahl($v['spalten'] ?? '1fr');
    $miniTemplate = \App\Support\ErgebnisboardVorlagen::spaltenGueltig($v['spalten'] ?? '') ? $v['spalten'] : '1fr';
    $miniFrei = ! empty($v['bereiche_frei']);
@endphp
<div class="erg-mini" style="grid-template-columns: {{ $miniTemplate }}" aria-hidden="true">
    @foreach($miniHaupt as $b)
        <span class="erg-farbe-{{ $b['farbe'] }}"
              @if($miniFrei)
                  style="grid-column: {{ min($b['spalte'], $miniSpalten) }} / span 1; grid-row: 1 / span 1"
              @else
                  style="grid-column: {{ min($b['spalte'], $miniSpalten) }} / span {{ max(1, min($b['spalten'], $miniSpalten - min($b['spalte'], $miniSpalten) + 1)) }}; grid-row: {{ $b['zeile'] }} / span {{ $b['zeilen'] }}"
              @endif></span>
    @endforeach
</div>
