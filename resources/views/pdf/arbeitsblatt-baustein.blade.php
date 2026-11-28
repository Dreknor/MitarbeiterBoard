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
    $txt = fn (?string $text) => \App\Services\Arbeitsblaetter\AbSatzService::textHtml((string) $text, $l);
    $inhalt = function (array $segmente) use ($lueckeBreite, $txt) {
        $html = '';
        foreach ($segmente as $s) {
            if (($s['typ'] ?? '') === 'luecke') {
                $html .= '<span class="ab-luecke" style="width: '.$lueckeBreite((int) $s['laenge']).'pt"></span>';
            } else {
                $html .= $txt($s['text'] ?? '');
            }
        }
        return $html;
    };
    // Aufgabenkopf: Nummer in eigener Zelle (negativer Einzug landet bei DomPDF am Papierrand)
    // Nur die erste Zeile steht in der Tabellenzelle: DomPDF teilt eine Tabellenzeile nie über Seiten –
    // eine lange Aufgabe (z. B. zehn Lückensätze) würde sonst abgeschnitten. Jede weitere Zeile wird ein eigener Absatz.
    $kopfHtml = function (array $b) use ($inhalt) {
        $zeilen = [[]];
        foreach ($b['inhalt'] ?? [] as $s) {
            if (($s['typ'] ?? '') !== 'text' || ! str_contains((string) ($s['text'] ?? ''), "\n")) {
                $zeilen[count($zeilen) - 1][] = $s;
                continue;
            }
            foreach (explode("\n", (string) $s['text']) as $i => $teil) {
                if ($i > 0) {
                    $zeilen[] = [];
                }
                if ($teil !== '') {
                    $zeilen[count($zeilen) - 1][] = ['typ' => 'text', 'text' => $teil];
                }
            }
        }
        $zeilen = array_values(array_filter($zeilen));
        $erste = array_shift($zeilen) ?? [];
        $rest = implode('', array_map(fn ($z) => '<p class="ab-kopf-zeile">'.$inhalt($z).'</p>', $zeilen));
        if (($b['nummer'] ?? '') === '') {
            return '<div class="ab-kopf-ohne">'.$inhalt($erste).'</div>'.($rest !== '' ? '<div class="ab-kopf-rest-ohne">'.$rest.'</div>' : '');
        }

        return '<table class="ab-kopf"><tr><td class="ab-nummer">'.e($b['nummer']).'</td><td class="ab-kopf-text">'.$inhalt($erste).'</td></tr></table>'
            .($rest !== '' ? '<div class="ab-kopf-rest">'.$rest.'</div>' : '');
    };
    // ein einzelner Block, der auf eine Seite passt, wird nie geteilt (Tabelle, Raster, Kasten, Bild …)
    $ganz = fn (array $b) => empty($tief) && empty($imPaar) && $einzeln($b);
@endphp
@switch($b['typ'])
    @case('ueberschrift')
        <h1 class="ab-baustein">{!! $txt($b['text']) !!}</h1>
        @break

    @case('anweisung')
        <p class="ab-baustein ab-anweisung">{!! $txt($b['text']) !!}</p>
        @break

    @case('text')
        <p class="ab-baustein {{ str_ends_with(trim(\App\Support\AbStruktur::segmentText($b['inhalt'] ?? [])), ':') ? 'ab-einleitung' : '' }}">{!! $inhalt($b['inhalt'] ?? []) !!}</p>
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
            // Teilaufgabe mit vielen Linien, die nicht zusammen passt: Frage + erste zwei Linien bleiben beisammen
            $geteilt = [];
            foreach ($gruppen as $gi => $gruppe) {
                $letzte = end($gruppe);
                $teile = $gi === 0 ? array_merge([['typ' => 'text', 'inhalt' => $b['inhalt'] ?? []]], $gruppe) : $gruppe;
                if (count($gruppe) >= 2 && $letzte['typ'] === 'schreiblinien' && (int) ($letzte['anzahl'] ?? 3) > 2 && ! $zusammen($teile)) {
                    $gruppe[count($gruppe) - 1] = array_merge($letzte, ['anzahl' => 2, '_geteilt' => true]);
                    $geteilt[] = $gruppe;
                    $geteilt[] = [array_merge($letzte, ['anzahl' => (int) $letzte['anzahl'] - 2, '_fortsetzung' => true])];
                } else {
                    $geteilt[] = $gruppe;
                }
            }
            $gruppen = $geteilt;
        @endphp
        @if(! $gruppen)
            <div class="ab-baustein ab-aufgabe {{ $zusammen([$b]) ? 'ab-zusammen' : '' }}">{!! $kopfHtml($b) !!}</div>
        @else
            <div class="ab-baustein">
                @foreach($gruppen as $gi => $gruppe)
                    @php
                        // eine einzelne Tabelle/Raster/Bildgruppe (Suchsel, Vokabeltabelle) bleibt bis 80 % Seitenhöhe ganz,
                        // samt Aufgabenkopf – abgeschnitten wird nichts: pdf() prüft das Ergebnis und setzt sonst ohne Sperre neu
                        $teile = $gi === 0 ? array_merge([['typ' => 'text', 'inhalt' => $b['inhalt'] ?? []]], $gruppe) : $gruppe;
                        $block = count($gruppe) === 1 && in_array($gruppe[0]['typ'], ['rechenfeld', 'ankreuzen'], true);
                        // Tabellen/Bildraster nie in einen „nicht teilen“-Block: DomPDF schiebt solche Blöcke auch dann
                        // auf die nächste Seite, wenn sie noch passen (halbleere Seiten). Sie halten zeilenweise
                        // zusammen und wiederholen den Kopf.
                        $tabelleDrin = collect($gruppe)->contains(fn ($k) => in_array($k['typ'], ['tabelle', 'bildgruppe'], true));
                        $halten = ! $tabelleDrin && ($block ? $avoid && $hoehe($teile) <= $l['nutzhoehe_pt'] * 0.8 : $zusammen($teile));
                    @endphp
                    <div class="{{ $halten ? 'ab-zusammen' : '' }}">
                        @if($gi === 0 && $kopf)
                            <div class="ab-kopf-bindet">{!! $kopfHtml($b) !!}</div>
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
        <div class="ab-baustein {{ $ganz($b) ? 'ab-zusammen' : '' }}">
            @if(($b['frage'] ?? '') !== '')<p class="ab-einleitung" style="margin: 0">{!! $txt($b['frage']) !!}</p>@endif
            @foreach($b['optionen'] ?? [] as $oi => $option)
                <table class="ab-option"><tr>
                    <td class="ab-kaestchen-zelle"><img class="ab-kaestchen" src="{{ $kaestchen(empty($b['mehrfach']), in_array($oi, $b['angekreuzt'] ?? [], true)) }}" alt="{{ in_array($oi, $b['angekreuzt'] ?? [], true) ? 'angekreuzt' : 'Kästchen' }}"></td>
                    <td class="ab-option-text">{!! $txt($option) !!}</td>
                </tr></table>
            @endforeach
        </div>
        @break

    @case('tabelle')
        @php
            // Antwortspalten (im Rumpf überall leer) bekommen Platz zum Schreiben statt der schmalsten Breite
            $zeilenT = array_values($b['zeilen'] ?? []);
            $kopfT = !empty($b['kopfzeile']) && count($zeilenT) > 1 ? array_shift($zeilenT) : null;
            $spaltenT = max(array_map('count', $kopfT ? array_merge([$kopfT], $zeilenT) : ($zeilenT ?: [[]])) ?: [0]);
            $leerT = [];
            for ($s = 0; $s < $spaltenT; $s++) {
                $leerT[$s] = $zeilenT && collect($zeilenT)->every(fn ($z) => trim((string) ($z[$s] ?? '')) === '');
            }
            $leerAnzahl = count(array_filter($leerT));
            // mehrere leere Spalten mit kurzem Kopf (T/F, richtig/falsch, A/B/C) sind Ankreuzspalten: schmal,
            // damit die Aussage Platz hat; sonst sind es Schreibspalten
            $ankreuzT = $leerAnzahl >= 2 && $kopfT
                && collect(array_keys(array_filter($leerT)))->every(fn ($s) => mb_strlen(trim((string) ($kopfT[$s] ?? ''))) <= 8);
            $leerBreite = $leerAnzahl && $leerAnzahl < $spaltenT
                ? ($ankreuzT ? round(min(14, 40 / $leerAnzahl), 1) : round(min(50, 70 / $leerAnzahl), 1))
                : null;
            $breiteT = fn ($s) => $leerBreite && ($leerT[$s] ?? false) ? ' style="width: '.$leerBreite.'%"' : '';
        @endphp
        <table class="ab-baustein ab-tabelle" style="font-size: {{ $tabellenSchrift($b) }}pt">
            @if($kopfT)
                <thead><tr>@foreach($kopfT as $s => $zelle)<th{!! $breiteT($s) !!}>{!! $txt($zelle) !!}</th>@endforeach</tr></thead>
            @endif
            <tbody>
            @foreach($zeilenT as $i => $zeile)
                <tr>
                    @foreach($zeile as $s => $zelle)<td{!! ! $kopfT && $i === 0 ? $breiteT($s) : '' !!}>{!! $txt($zelle) !!}</td>@endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        @break

    @case('kasten')
        {{-- nur auf oberster Ebene „nicht trennen“: verschachtelt verliert DomPDF sonst Rahmen/Tabellen --}}
        <div class="ab-baustein ab-kasten {{ $ganz($b) ? 'ab-zusammen' : '' }}">
            @if(($b['titel'] ?? '') !== '')<div class="ab-kasten-titel">{!! $txt($b['titel']) !!}</div>@endif
            @if($liste = $wortliste($b))
                @php
                    // lieber weniger Spalten als kleinere Schrift (höchstens 85 % der Profilgröße)
                    for ($sp = $listenSpalten; $sp >= 1; $sp--) {
                        $proSpalte = (int) ceil(count($liste) / $sp);
                        $schriftListe = $tabellenSchrift(['zeilen' => array_map(null, ...array_pad(array_chunk($liste, $proSpalte), max(2, $sp), []))]);
                        if ($schriftListe >= $l['groesse'] * 0.85) {
                            break;
                        }
                    }
                    $spaltenListe = max(1, $sp);
                    $proSpalte = (int) ceil(count($liste) / $spaltenListe);
                @endphp
                {{-- spaltenweise gefüllt, aber eine Tabellenzeile je Zeile: DomPDF teilt Zeilen nie über Seiten --}}
                <table class="ab-wortliste" style="font-size: {{ $spaltenListe > 1 ? $schriftListe : $l['groesse'] }}pt">
                    @for($z = 0; $z < $proSpalte; $z++)
                        <tr>@for($s2 = 0; $s2 < $spaltenListe; $s2++)<td style="width: {{ round(100 / $spaltenListe, 2) }}%">{!! $txt($liste[$s2 * $proSpalte + $z] ?? '') !!}</td>@endfor</tr>
                    @endfor
                </table>
            @else
                <div>{!! $inhalt($b['inhalt'] ?? []) !!}</div>
            @endif
        </div>
        @break

    @case('rechenfeld')
        <div class="ab-baustein {{ $ganz($b) ? 'ab-zusammen' : '' }}">
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
        <div class="ab-baustein ab-schreiblinien {{ ! empty($b['_geteilt']) ? 'ab-geteilt' : '' }} {{ empty($imPaar) && $einzeln($b) ? 'ab-zusammen' : '' }}">
            @if($lineatur($b) === 'grundschule')
                @for($i = 0; $i < (int) ($b['anzahl'] ?? 3); $i++)<div class="ab-gs"><div class="ab-gs-o"></div><div class="ab-gs-m"></div><div class="ab-gs-u"></div></div>@endfor
            @else
                @for($i = 0; $i < (int) ($b['anzahl'] ?? 3); $i++)<div class="ab-linie"></div>@endfor
            @endif
        </div>
        @break

    @case('bildgruppe')
        <table class="ab-baustein ab-bildgruppe">
            @foreach(array_chunk($b['bilder'], $rasterSpalten) as $reihe)
                <tr>
                    @foreach($reihe as $bild1)
                        @php $quelle = !empty($bild1['media_id']) ? $bild((int) $bild1['media_id']) : null; @endphp
                        <td>
                            <span class="ab-bild-nr">{!! $txt((string) $bild1['_nr']) !!}</span>
                            @if($quelle)
                                <img src="{{ $quelle }}" alt="{{ $bild1['beschreibung'] ?? '' }}" style="width: {{ $bild1['_breite_pt'] }}pt">
                            @else
                                <div class="ab-bild-fehlt">{{ $bild1['beschreibung'] ?? 'Abbildung' }}</div>
                            @endif
                            @if(!empty($b['_linie']))<div class="ab-bild-linie"></div>@endif
                        </td>
                    @endforeach
                    @for($leer = count($reihe); $leer < $rasterSpalten; $leer++)<td></td>@endfor
                </tr>
            @endforeach
        </table>
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
