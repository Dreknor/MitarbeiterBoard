{{-- Ein Baustein des angepassten Arbeitsblatts (siehe pdf/arbeitsblatt.blade.php, App\Support\AbStruktur) --}}
@php
    // Ankreuzkästchen als SVG (DomPDF zentriert Text in Kästchen nicht zuverlässig); Maße/Farben aus dem Profil
    $kaestchen = function (bool $rund, bool $an) use ($l) {
        $g = 100;
        $w = max(4, round(max(1, $l['linienstaerke']) / $l['ankreuz_pt'] * $g, 1));
        $f = $l['farbe_linie'];
        $form = $rund
            ? '<circle cx="50" cy="50" r="'.(50 - $w / 2).'" fill="none" stroke="'.$f.'" stroke-width="'.$w.'"/>'
            : '<rect x="'.($w / 2).'" y="'.($w / 2).'" width="'.($g - $w).'" height="'.($g - $w).'" fill="none" stroke="'.$f.'" stroke-width="'.$w.'"/>';
        $kreuz = $an ? '<path d="M27 27 L73 73 M73 27 L27 73" stroke="'.$f.'" stroke-width="'.($w * 1.6).'" stroke-linecap="round"/>' : '';

        return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">'.$form.$kreuz.'</svg>');
    };
    $inhalt = function (array $segmente) use ($lueckeBreite) {
        $html = '';
        foreach ($segmente as $s) {
            if (($s['typ'] ?? '') === 'luecke') {
                $html .= '<span class="ab-luecke" style="width: '.$lueckeBreite((int) $s['laenge']).'pt"></span>';
            } else {
                $html .= nl2br(e($s['text'] ?? ''), false);
            }
        }
        return $html;
    };
@endphp
@switch($b['typ'])
    @case('ueberschrift')
        <h1 class="ab-baustein">{{ $b['text'] }}</h1>
        @break

    @case('anweisung')
        <p class="ab-baustein ab-anweisung">{!! nl2br(e($b['text']), false) !!}</p>
        @break

    @case('text')
        <p class="ab-baustein">{!! $inhalt($b['inhalt'] ?? []) !!}</p>
        @break

    @case('aufgabe')
        @php
            $kopf = (($b['nummer'] ?? '') !== '' || !empty($b['inhalt']));
            // Teilaufgaben bilden Gruppen: jede Teilaufgabe mit den folgenden Bausteinen (Ankreuzen, Antwort …).
            // Jede Gruppe bleibt zusammen – nicht die ganze Aufgabe, die sonst als Block auf die nächste Seite rutscht.
            // Bausteine vor der ersten Teilaufgabe (z. B. Schilder) bilden je eine eigene Gruppe, damit
            // keine übergroße Gruppe entsteht, die DomPDF nicht mehr sauber umbrechen kann.
            $gruppen = [];
            $inTeilaufgabe = false;
            foreach ($b['kinder'] ?? [] as $kind) {
                if (! $gruppen || $kind['typ'] === 'aufgabe' || ! $inTeilaufgabe) {
                    $gruppen[] = [];
                }
                $inTeilaufgabe = $inTeilaufgabe || $kind['typ'] === 'aufgabe';
                $gruppen[count($gruppen) - 1][] = $kind;
            }
        @endphp
        @if(! $gruppen)
            <div class="ab-baustein ab-aufgabe {{ $zusammen([$b]) ? 'ab-zusammen' : '' }}">
                <div class="{{ ($b['nummer'] ?? '') !== '' ? 'ab-haengend' : '' }}">@if(($b['nummer'] ?? '') !== '')<span class="ab-nummer">{{ $b['nummer'] }}</span>@endif{!! $inhalt($b['inhalt'] ?? []) !!}</div>
            </div>
        @else
            <div class="ab-baustein">
                @foreach($gruppen as $gi => $gruppe)
                    <div class="{{ $zusammen($gi === 0 ? array_merge([['typ' => 'text', 'inhalt' => $b['inhalt'] ?? []]], $gruppe) : $gruppe) ? 'ab-zusammen' : '' }}">
                        @if($gi === 0 && $kopf)
                            <div class="{{ ($b['nummer'] ?? '') !== '' ? 'ab-haengend' : '' }}">@if(($b['nummer'] ?? '') !== '')<span class="ab-nummer">{{ $b['nummer'] }}</span>@endif{!! $inhalt($b['inhalt'] ?? []) !!}</div>
                        @endif
                        <div class="ab-kinder">
                            @foreach($gruppe as $kind)
                                @include('pdf.arbeitsblatt-baustein', ['b' => $kind, 'tief' => 1])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @break

    @case('ankreuzen')
        <div class="ab-baustein">
            @if(($b['frage'] ?? '') !== '')<p style="margin: 0">{!! nl2br(e($b['frage']), false) !!}</p>@endif
            @foreach($b['optionen'] ?? [] as $oi => $option)
                <table class="ab-option"><tr>
                    <td class="ab-kaestchen-zelle"><img class="ab-kaestchen" src="{{ $kaestchen(empty($b['mehrfach']), in_array($oi, $b['angekreuzt'] ?? [], true)) }}" alt="{{ in_array($oi, $b['angekreuzt'] ?? [], true) ? 'angekreuzt' : 'Kästchen' }}"></td>
                    <td class="ab-option-text">{{ $option }}</td>
                </tr></table>
            @endforeach
        </div>
        @break

    @case('tabelle')
        <table class="ab-baustein ab-tabelle">
            @foreach($b['zeilen'] ?? [] as $i => $zeile)
                <tr>
                    @foreach($zeile as $zelle)
                        @if($i === 0 && !empty($b['kopfzeile']))<th>{{ $zelle }}</th>@else<td>{{ $zelle }}</td>@endif
                    @endforeach
                </tr>
            @endforeach
        </table>
        @break

    @case('kasten')
        {{-- nur auf oberster Ebene „nicht trennen“: verschachtelt verliert DomPDF sonst Rahmen/Tabellen --}}
        <div class="ab-baustein ab-kasten {{ empty($tief) && empty($imPaar) && $zusammen([$b]) ? 'ab-zusammen' : '' }}">
            @if(($b['titel'] ?? '') !== '')<div class="ab-kasten-titel">{{ $b['titel'] }}</div>@endif
            <div>{!! $inhalt($b['inhalt'] ?? []) !!}</div>
        </div>
        @break

    @case('rechenfeld')
        <div class="ab-baustein {{ empty($tief) && empty($imPaar) && $zusammen([$b]) ? 'ab-zusammen' : '' }}">
            @if(!empty($b['inhalt']))<div style="margin-bottom: 4pt">{!! $inhalt($b['inhalt']) !!}</div>@endif
            @php $spalten = empty($tief) ? $l['raster_spalten'] : max(4, $l['raster_spalten'] - 2); @endphp
            <table class="ab-raster" style="width: {{ round($spalten * $l['raster_pt'], 1) }}pt">
                @for($r = 0; $r < (int) ($b['reihen'] ?? 2); $r++)
                    <tr>@for($c = 0; $c < $spalten; $c++)<td></td>@endfor</tr>
                @endfor
            </table>
        </div>
        @break

    @case('zeichenflaeche')
        <div class="ab-baustein ab-zeichenflaeche" style="height: {{ round((int) ($b['hoehe'] ?? 6) * $l['linienabstand_pt'], 1) }}pt"></div>
        @break

    @case('schreiblinien')
        <div class="ab-baustein ab-schreiblinien {{ empty($imPaar) && $zusammen([$b]) ? 'ab-zusammen' : '' }}">
            @if($lineatur($b) === 'grundschule')
                @for($i = 0; $i < (int) ($b['anzahl'] ?? 3); $i++)<div class="ab-gs"><div class="ab-gs-o"></div><div class="ab-gs-m"></div><div class="ab-gs-u"></div></div>@endfor
            @else
                @for($i = 0; $i < (int) ($b['anzahl'] ?? 3); $i++)<div class="ab-linie"></div>@endfor
            @endif
        </div>
        @break

    @case('bild')
        @php $quelle = !empty($b['media_id']) ? $bild((int) $b['media_id']) : null; @endphp
        <div class="ab-baustein ab-bild">
            @if($quelle)
                <img src="{{ $quelle }}" alt="{{ $b['beschreibung'] ?? '' }}" style="width: {{ $bildBreite($b) }}%">
            @else
                <div class="ab-bild-fehlt">Abbildung{{ ($b['beschreibung'] ?? '') !== '' ? ': '.$b['beschreibung'] : '' }}</div>
            @endif
        </div>
        @break
@endswitch
