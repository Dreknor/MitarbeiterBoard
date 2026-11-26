{{-- Wochenpläne eines Kindes in der Schülerakte (beide Darstellungen, nur mit Tagebuch-Anbindung) --}}
<div class="wpt-akte">
    @if(empty($wochenplaene))
        <p class="wpt-leer">Für dieses Kind gibt es noch keine Wochenpläne.</p>
    @else
        <ul class="wpt-akte-liste">
            @foreach($wochenplaene as $wp)
                @php
                    $z = $wp['zusammenfassung'];
                    $art = ! $z['gesamt'] ? '' : ($z['erledigt'] === $z['gesamt'] ? 'is-fertig' : 'is-offen');
                @endphp
                <li class="wpt-akte-zeile">
                    <div class="wpt-akte-text">
                        <a href="{{ $wp['url'] }}" class="wpt-akte-name">{{ $wp['name'] }}</a>
                        <span class="wpt-meta">{{ $wp['typ'] }} · {{ $wp['zeitraum'] }}</span>
                    </div>
                    <span class="wpt-zaehler {{ $art }}">{{ $z['erledigt'] }} ✓ · {{ $z['teilweise'] }} ◐ von {{ $z['gesamt'] }}</span>
                    <a href="{{ $wp['pdf'] }}" target="_blank" class="wpt-akte-pdf">PDF</a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
