{{--
    Angepasstes Arbeitsblatt – eine Vorlage für PDF (DomPDF) und HTML-Vorschau im Editor (iframe),
    damit beide übereinstimmen. Layout kommt ausschließlich aus dem Sehprofil ($l = AbProfil::layout()).

    Variablen: $titel, $struktur (AbStruktur), $l (Layout), $modus (pdf|html), $schriften (Familie → [normal, bold] URL),
               $bild (Closure media_id → URL/Data-URI|null), $fusszeile (?string)
--}}
@php
    $fett = $l['fett'] ? 'bold' : 'normal';
    $avoid = $l['aufgabe_nicht_teilen'];
    $mm = fn ($v) => $v.'mm';
    // Lücke: so breit wie die erwartete Antwort, aber nie breiter als ~70 % der Zeile (sonst über den Rand)
    $lueckeBreite = fn ($laenge) => min(round($l['nutzbreite_pt'] * 0.7, 1), max(min($l['luecke_pt'], $l['nutzbreite_pt'] * 0.5), round($laenge * $l['groesse'] * 0.62, 1)));
    // Abbildungen wirklich vergrößern: mindestens 75 % der Satzbreite (Bildfaktor des Profils darüber hinaus),
    // aber nie höher als ~80 % der Seite – sonst rutscht das Bild allein auf eine eigene Seite
    // Höhe/Breite einer Abbildung: echte Bildmaße (nach Feinschnitt), sonst aus der Box (Original A4 hoch)
    $bildMasse ??= fn (int $id) => null;
    $aspekt = function (array $b) use ($bildMasse) {
        $echt = ! empty($b['media_id']) ? $bildMasse((int) $b['media_id']) : null;
        if ($echt) {
            return $echt;
        }
        $box = $b['quelle']['box'] ?? null;
        return $box && ($box[2] - $box[0]) > 0 ? ($box[3] - $box[1]) / ($box[2] - $box[0]) * 1.414 : null;
    };
    $bildBreite = function (array $b) use ($l, $aspekt) {
        $box = $b['quelle']['box'] ?? null;
        $anteil = $box ? max(0.15, $box[2] - $box[0]) : 0.45;
        $breite = max(40, $anteil * 100 * $l['bildfaktor']);
        if ($seitenverhaeltnis = $aspekt($b)) {
            $breite = min($breite, 70 * $l['nutzhoehe_pt'] / max(0.01, $l['nutzbreite_pt'] * $seitenverhaeltnis));
        }
        return (int) round(max(25, min(100, $breite)));
    };
    // Breite eines Zeichens (pt) – mit Buchstabenabstand und fetter Schrift, damit Umbruch-Schätzungen stimmen
    $zeichenBreite = fn (float $groesse) => $groesse * (0.56 + $l['buchstabenabstand']) * ($l['fett'] ? 1.07 : 1);
    // Tabellen: Schrift so weit verkleinern (höchstens auf 55 % – lieber kleiner als abgeschnitten), dass das längste Wort jeder Spalte passt
    $tabellenSchrift = function (array $b) use ($l, $zeichenBreite) {
        $spalten = [];
        foreach ($b['zeilen'] ?? [] as $zeile) {
            foreach (array_values($zeile) as $i => $zelle) {
                $woerter = preg_split('/\s+/u', (string) $zelle) ?: [''];
                $spalten[$i] = max($spalten[$i] ?? 1, ...array_map('mb_strlen', $woerter));
            }
        }
        $polster = 2 * round($l['groesse'] * 0.4) + 2 * max(1, $l['linienstaerke']);
        $noetig = array_sum(array_map(fn ($n) => $n * $zeichenBreite($l['groesse']) + $polster, $spalten ?: [1]));
        $faktor = $noetig > $l['nutzbreite_pt'] ? max(0.55, ($l['nutzbreite_pt'] - count($spalten) * $polster) / max(1, $noetig - count($spalten) * $polster)) : 1;
        return round($l['groesse'] * $faktor, 1);
    };
    // Geschätzte Höhe (pt) von Bausteinen – „nicht teilen“ nur für Einheiten bis knapp der halben Seite,
    // größere würden als Block auf die nächste Seite rutschen und eine fast leere Seite hinterlassen
    // Lineatur: das Sehprofil kann sie für alle Schreiblinien festlegen, sonst gilt die des Originals
    $lineatur = fn (array $b) => ($l['lineatur'] ?? 'original') !== 'original' ? $l['lineatur'] : ($b['lineatur'] ?? 'einfach');
    // Mehrere Abbildungen hintereinander: Raster mit Nummern, maßstabsgleich zueinander (Zuordnen, Beschriften, Benennen)
    $rasterSpalten = $l['groesse'] >= 26 ? 2 : 3;
    $rasterZelle = $l['nutzbreite_pt'] / $rasterSpalten - $l['groesse'] * 0.4;
    // ein hohes Bild (Thermometer, Stativ) darf die Zeile nicht aufblähen
    $rasterBildHoehe = $l['nutzhoehe_pt'] * 0.28;
    $bildgruppeHoehe = function (array $g) use ($l, $rasterSpalten) {
        $summe = 0;
        foreach (array_chunk($g['bilder'], $rasterSpalten) as $reihe) {
            $summe += max(array_map(fn ($b) => $b['_breite_pt'] * $b['_hoch'], $reihe)) + $l['groesse'] * 2 + (! empty($g['_linie']) ? $l['linienabstand_pt'] : 0);
        }
        return $summe;
    };
    $nummer = 0;
    $gruppieren = function (array $liste) use (&$nummer, $rasterZelle, $rasterBildHoehe, $aspekt) {
        $ergebnis = [];
        // Steht davor „Beschrifte/Benenne …“ oder ein Wortspeicher, bekommt jedes Bild eine Schreiblinie
        $beschriften = false;
        foreach ($liste as $b) {
            if ($b['typ'] !== 'bild') {
                $text = mb_strtolower(($b['text'] ?? '').' '.\App\Support\AbStruktur::segmentText($b['inhalt'] ?? []));
                $beschriften = $b['typ'] === 'kasten' || (bool) preg_match('/beschrift|benenn|schreib\w* (den |die )?(namen|wörter|begriffe)|label|write the (name|word)/u', $text);
            }
            $letzte = $ergebnis ? count($ergebnis) - 1 : null;
            if ($b['typ'] === 'bild' && $letzte !== null && in_array($ergebnis[$letzte]['typ'], ['bild', 'bildgruppe'], true)) {
                if ($ergebnis[$letzte]['typ'] === 'bild') {
                    $ergebnis[$letzte] = ['typ' => 'bildgruppe', 'bilder' => [$ergebnis[$letzte]], '_linie' => $beschriften];
                }
                $ergebnis[$letzte]['bilder'][] = $b;
                continue;
            }
            $ergebnis[] = $b;
        }
        foreach ($ergebnis as $i => &$e) {
            if ($e['typ'] !== 'bildgruppe') {
                continue;
            }
            // Beschriftungsblatt ohne eigene Antwortstruktur (viele Bilder, keine Tabelle danach) → Linie je Bild
            $danach = [$ergebnis[$i + 1]['typ'] ?? null, $ergebnis[$i + 2]['typ'] ?? null];
            $e['_linie'] = ! in_array('tabelle', $danach, true) && (! empty($e['_linie']) || count($e['bilder']) >= 6);
            // Maßstab: ein gemeinsamer Faktor (pt je Seitenbreite des Originals), damit das Teelicht nicht größer
            // als das Becherglas wird – aber jedes Bild mindestens 35 % seiner Füllgröße, nichts wird winzig
            $passt = [];
            foreach ($e['bilder'] as $k => $b) {
                $box = $b['quelle']['box'] ?? [0, 0, 1, 1];
                $hoch = $aspekt($b) ?? 1;
                $passt[$k] = min($rasterZelle, $rasterBildHoehe / max(0.05, $hoch)) / max(0.01, $box[2] - $box[0]);
            }
            $sortiert = $passt;
            sort($sortiert);
            $gemeinsam = $sortiert[intdiv(count($sortiert), 2)];
            foreach ($e['bilder'] as $k => &$b) {
                $box = $b['quelle']['box'] ?? [0, 0, 1, 1];
                $b['_nr'] = ++$nummer;
                $b['_hoch'] = $aspekt($b) ?? 1;
                // gemeinsamer Maßstab, aber jedes Bild mindestens 60 % seiner Füllgröße (dünne Geräte keine Haarlinien)
                $faktor = max(min($gemeinsam, $passt[$k]), 0.6 * $passt[$k]);
                $b['_breite_pt'] = round($faktor * max(0.01, $box[2] - $box[0]), 1);
            }
            unset($b);
        }
        unset($e);
        return $ergebnis;
    };
    // Wortspeicher (viele kurze Zeilen ohne Lücken) in Spalten statt ein Wort je Zeile über Seiten
    $listenSpalten = $l['groesse'] >= 26 ? 2 : 3;
    $wortliste = function (array $b) {
        $text = '';
        foreach ($b['inhalt'] ?? [] as $s) {
            if (($s['typ'] ?? '') !== 'text') {
                return null;
            }
            $text .= $s['text'] ?? '';
        }
        $zeilen = array_values(array_filter(array_map('trim', explode("
", $text)), fn ($z) => $z !== ''));
        if (count($zeilen) < 6) {
            return null;
        }
        $laengen = array_map('mb_strlen', $zeilen);
        return max($laengen) <= 40 && array_sum($laengen) / count($laengen) <= 18 ? $zeilen : null;
    };
    $hoehe = function (array $bausteine) use (&$hoehe, $l, $bildBreite, $aspekt, $lineatur, $zeichenBreite, $bildgruppeHoehe, $wortliste, $listenSpalten) {
        $zeichenJeZeile = max(10, $l['nutzbreite_pt'] * 0.92 / $zeichenBreite($l['groesse']));
        $zeilen = fn (string $text) => max(1, (int) ceil(mb_strlen($text) / $zeichenJeZeile) + substr_count($text, "\n"));
        $summe = 0;
        foreach ($bausteine as $b) {
            $text = \App\Support\AbStruktur::segmentText($b['inhalt'] ?? []);
            $box = $b['quelle']['box'] ?? null;
            $summe += $l['groesse'] * 0.75 + match ($b['typ']) {
                'ueberschrift' => $l['ueberschrift_pt'] * 1.3 * $zeilen($b['text'] ?? ''),
                'anweisung' => $l['zeilenhoehe_pt'] * $zeilen($b['text'] ?? ''),
                'text', 'aufgabe' => $l['zeilenhoehe_pt'] * $zeilen($text) + $hoehe($b['kinder'] ?? []),
                'kasten' => $wortliste($b)
                    ? $l['zeilenhoehe_pt'] * (ceil(count($wortliste($b)) / $listenSpalten) + 1) + $l['groesse']
                    : $l['zeilenhoehe_pt'] * ($zeilen($text) + 1) + $l['groesse'],
                'ankreuzen' => count($b['optionen'] ?? []) * ($l['ankreuz_pt'] + $l['groesse']) + (($b['frage'] ?? '') !== '' ? $l['zeilenhoehe_pt'] : 0),
                'tabelle' => array_sum(array_map(function ($zeile) use ($l, $zeichenBreite) {
                    $breite = $l['nutzbreite_pt'] / max(1, count($zeile));
                    $zeilenJeZelle = max(array_map(fn ($z) => max(1, (int) ceil(mb_strlen((string) $z) * $zeichenBreite($l['groesse']) / max(20, $breite - $l['groesse']))), $zeile ?: ['']));
                    return max($l['zeilenhoehe_pt'] * 1.2, $zeilenJeZelle * $l['zeilenhoehe_pt']) + $l['groesse'] * 0.6;
                }, $b['zeilen'] ?? [])),
                'rechenfeld' => (int) ($b['reihen'] ?? 2) * $l['raster_pt'] + ($text !== '' ? $l['zeilenhoehe_pt'] : 0),
                'schreiblinien' => (int) ($b['anzahl'] ?? 3) * ($lineatur($b) === 'grundschule' ? $l['gs_band_pt'] * 5.5 : $l['linienabstand_pt']),
                'zeichenflaeche' => (int) ($b['hoehe'] ?? 6) * $l['linienabstand_pt'],
                'bildgruppe' => $bildgruppeHoehe($b),
                'bild' => ($a = $aspekt($b)) ? $l['nutzbreite_pt'] * $bildBreite($b) / 100 * $a : 150,
                default => $l['zeilenhoehe_pt'],
            };
        }
        return $summe;
    };
    // Gruppen (Aufgabe mit Teilen) nur bis knapp halbe Seite zusammenhalten – sonst große Lücken;
    // ein einzelner Block (Tabelle, Raster, Kasten, Bild, Linien) bleibt ganz, solange er auf eine Seite passt
    $zusammen = fn (array $bausteine) => $avoid && $hoehe($bausteine) <= $l['nutzhoehe_pt'] * 0.45;
    // Vorsicht: ein „nicht teilen“-Block, der in Wahrheit höher als die Seite ist, schneidet DomPDF ab –
    // die Schätzung ist grob, daher nur bis gut der halben Seite
    // Tabellen/Raster bis 80 %: fehlt danach Text, setzt pdf() ohne Sperre neu (Sicherheitsnetz)
    $einzeln = fn (array $b) => $avoid && $hoehe([$b]) <= $l['nutzhoehe_pt'] * (in_array($b['typ'], ['tabelle', 'rechenfeld', 'bildgruppe'], true) ? 0.8 : 0.55);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $titel }}</title>
    <style>
        @foreach($schriften as $familie => $dateien)
        @font-face { font-family: '{{ $familie }}'; font-style: normal; font-weight: normal; src: url('{{ $dateien[0] }}') format('truetype'); }
        @font-face { font-family: '{{ $familie }}'; font-style: normal; font-weight: bold; src: url('{{ $dateien[1] }}') format('truetype'); }
        @endforeach

        @page { size: {{ $l['format'] }} portrait; margin: {{ $mm($l['rand']) }}; }

        /* Kein margin auf html: DomPDF nimmt ihn sonst statt des @page-Rands */
        body { margin: 0; padding: 0; }
        body {
            font-family: '{{ $l['schrift_familie'] }}', {!! in_array($l['schrift'], ['arial', 'verdana'], true) ? "'".$l['schrift_name']."', " : '' !!}'DejaVu Sans', sans-serif;
            font-size: {{ $l['groesse'] }}pt;
            font-weight: {{ $fett }};
            line-height: {{ $l['zeilenabstand'] }};
            letter-spacing: {{ $l['buchstabenabstand'] }}em;
            color: {{ $l['farbe_text'] }};
            background: {{ $l['farbe_hintergrund'] }};
        }
        @if($modus === 'html')
        html { background: #cbd5e1; }
        body { background: transparent; padding: 16px 0; }
        .ab-seite {
            width: {{ $l['seite_breite_mm'] }}mm; min-height: {{ $l['seite_hoehe_mm'] }}mm; box-sizing: border-box;
            margin: 0 auto; padding: {{ $mm($l['rand']) }}; background: {{ $l['farbe_hintergrund'] }};
            box-shadow: 0 2px 10px rgba(15, 23, 42, .25);
        }
        @endif
        /* Seitenhintergrund im PDF: fester Rahmen wiederholt sich auf jeder Seite */
        .ab-hintergrund { position: fixed; top: -{{ $l['rand'] }}mm; left: -{{ $l['rand'] }}mm; right: -{{ $l['rand'] }}mm; bottom: -{{ $l['rand'] }}mm; background: {{ $l['farbe_hintergrund'] }}; z-index: -1; }

        h1 { font-size: {{ $l['ueberschrift_pt'] }}pt; font-weight: bold; margin: 0 0 {{ round($l['groesse'] * 0.6) }}pt; line-height: 1.25; }
        .ab-baustein { margin: 0 0 {{ round($l['groesse'] * 0.75) }}pt; }
        .ab-anweisung { font-weight: bold; }
        .ab-anweisung .ab-symbol { display: inline-block; margin-right: 4pt; }
        .ab-zusammen { page-break-inside: avoid; }
        /* Aufgabenkopf: Nummer in eigener Zelle (hängender Einzug per text-indent landet bei DomPDF am Papierrand) */
        table.ab-kopf { border-collapse: collapse; width: 100%; }
        table.ab-kopf td { padding: 0; vertical-align: top; }
        td.ab-nummer { font-weight: bold; width: {{ round($l['groesse'] * 1.8) }}pt; }
        .ab-kopf-rest { margin-left: {{ round($l['groesse'] * 1.8) }}pt; }
        p.ab-kopf-zeile { margin: {{ round($l['groesse'] * 0.35) }}pt 0 0 0; }
        /* Überschrift, Anweisung, Einleitung („… :“) und Aufgabenkopf nie allein am Seitenende */
        h1, .ab-anweisung, .ab-einleitung, .ab-kopf-bindet, .ab-kasten-titel { page-break-after: avoid; }
        p, .ab-kopf-ohne, td { orphans: 2; widows: 2; }
        .ab-null { font-family: 'DejaVu Sans', 'Liberation Sans', Arial, sans-serif; }
        .ab-term { }
        .ab-striche { white-space: nowrap; }
        .ab-strich { display: inline-block; width: {{ max(2, round($l['groesse'] * 0.12, 1)) }}pt; height: {{ round($l['groesse'] * 1.1, 1) }}pt; background: {{ $l['farbe_text'] }}; margin-right: {{ round($l['groesse'] * 0.28, 1) }}pt; vertical-align: middle; }
        table.ab-bildgruppe { border-collapse: collapse; width: 100%; }
        table.ab-bildgruppe tr { page-break-inside: avoid; }
        table.ab-bildgruppe td { width: {{ round(100 / $rasterSpalten, 2) }}%; vertical-align: top; padding: 0 {{ round($l['groesse'] * 0.4) }}pt {{ round($l['groesse'] * 0.6) }}pt 0; }
        table.ab-bildgruppe img { display: block; {{ $l['invertiert'] && ! $l['bild_kontrast'] ? 'background: #fff; padding: 3pt;' : '' }} }
        .ab-bild-nr { font-weight: bold; display: block; margin-bottom: 2pt; }
        .ab-bild-linie { height: {{ $l['linienabstand_pt'] }}pt; border-bottom: {{ $l['linienstaerke'] }}pt solid {{ $l['farbe_linie'] }}; margin-right: {{ round($l['groesse'] * 0.4) }}pt; }
        .ab-strich-luecke { display: inline-block; width: {{ round($l['groesse'] * 0.6, 1) }}pt; }
        .ab-kinder { margin: {{ round($l['groesse'] * 0.4) }}pt 0 0 {{ round($l['groesse'] * 1.6) }}pt; }
        .ab-luecke {
            display: inline-block; vertical-align: baseline;
            border-bottom: {{ $l['linienstaerke'] }}pt solid {{ $l['farbe_linie'] }};
            height: {{ round($l['groesse'] * 1.05, 1) }}pt; margin: 0 {{ round($l['groesse'] * 0.2, 1) }}pt; max-width: 100%;
        }
        .ab-gs { margin-top: {{ round($l['gs_band_pt'] * 2.5, 1) }}pt; }
        .ab-gs:first-child { margin-top: 0; }
        .ab-gs div { height: {{ $l['gs_band_pt'] }}pt; }
        .ab-gs .ab-gs-o { border-top: {{ max(1, round($l['linienstaerke'] * 0.8, 2)) }}pt solid {{ $l['farbe_linie'] }}; }
        .ab-gs .ab-gs-m { background: {{ $l['farbe_mittelband'] }}; border-top: {{ max(1, round($l['linienstaerke'] * 0.8, 2)) }}pt solid {{ $l['farbe_linie'] }}; border-bottom: {{ $l['linienstaerke'] }}pt solid {{ $l['farbe_linie'] }}; }
        .ab-gs .ab-gs-u { border-bottom: {{ max(1, round($l['linienstaerke'] * 0.8, 2)) }}pt solid {{ $l['farbe_linie'] }}; }
        .ab-zeichenflaeche { border: {{ max(1, $l['linienstaerke']) }}pt solid {{ $l['farbe_linie'] }}; }
        table.ab-option { width: 100%; border-collapse: separate; border-spacing: 0; margin: {{ round($l['groesse'] * 0.35) }}pt 0; page-break-inside: avoid; }
        td.ab-kaestchen-zelle { width: {{ $l['ankreuz_pt'] + 2 * max(1, $l['linienstaerke']) }}pt; padding: 0; vertical-align: middle; }
        img.ab-kaestchen { width: {{ $l['ankreuz_pt'] }}pt; height: {{ $l['ankreuz_pt'] }}pt; display: block; }
        td.ab-option-text { width: 100%; padding-left: 8pt; vertical-align: middle; }
        .ab-kasten { border: {{ max(1, $l['linienstaerke']) }}pt solid {{ $l['farbe_linie'] }}; padding: {{ round($l['groesse'] * 0.5) }}pt {{ round($l['groesse'] * 0.7) }}pt; }
        table.ab-wortliste { border-collapse: collapse; width: 100%; }
        table.ab-wortliste td { vertical-align: top; padding: 0 {{ round($l['groesse'] * 0.6) }}pt 0 0; }
        .ab-kasten-titel { font-weight: bold; margin-bottom: {{ round($l['groesse'] * 0.25) }}pt; }
        table.ab-raster { border-collapse: collapse; }
        table.ab-raster td { width: {{ $l['raster_pt'] }}pt; height: {{ $l['raster_pt'] }}pt; padding: 0; border: {{ max(0.5, round($l['linienstaerke'] * 0.5, 2)) }}pt solid {{ $l['farbe_linie'] }}; }
        .ab-schreiblinien.ab-geteilt { margin-bottom: 0; }
        .ab-linie { height: {{ $l['linienabstand_pt'] }}pt; border-bottom: {{ $l['linienstaerke'] }}pt solid {{ $l['farbe_linie'] }}; }
        table.ab-tabelle { border-collapse: collapse; width: 100%; }
        table.ab-tabelle tr { page-break-inside: avoid; }
        table.ab-tabelle td, table.ab-tabelle th {
            border: {{ max(1, $l['linienstaerke']) }}pt solid {{ $l['farbe_linie'] }};
            padding: {{ round($l['groesse'] * 0.3) }}pt {{ round($l['groesse'] * 0.4) }}pt;
            height: {{ round($l['zeilenhoehe_pt'] * 1.2) }}pt; text-align: left; vertical-align: middle;
        }
        table.ab-tabelle th { font-weight: bold; }
        table.ab-tabelle thead { display: table-header-group; }
        .ab-bild { page-break-inside: avoid; text-align: left; }
        /* Invertiert ohne Kontrast-Aufbereitung: Bilder auf weißem Grund; mit Aufbereitung sind sie selbst invertiert */
        .ab-bild img { max-width: 100%; {{ $l['invertiert'] && ! $l['bild_kontrast'] ? 'background: #fff; padding: 4pt;' : '' }} }
        .ab-bild-fehlt { border: {{ $l['linienstaerke'] }}pt dashed {{ $l['farbe_linie'] }}; padding: 8pt; font-size: {{ max(12, round($l['groesse'] * 0.7)) }}pt; }
        .ab-fuss { position: fixed; bottom: -{{ max(4, $l['rand'] - 4) }}mm; left: 0; right: 0; font-size: 9pt; font-weight: normal; letter-spacing: 0; opacity: .7; }
        @if($modus === 'html')
        .ab-fuss { position: static; margin-top: 24pt; }
        .ab-leer { font-family: sans-serif; font-size: 12pt; color: #64748b; font-weight: normal; letter-spacing: 0; }
        @endif
    </style>
</head>
<body>
@if($modus === 'pdf')
    <div class="ab-hintergrund"></div>
    @if($fusszeile)<div class="ab-fuss">{{ $fusszeile }}</div>@endif
@endif
<div class="ab-seite">
    @php
        $erste = $struktur['bausteine'][0] ?? null;
        $titelDoppelt = \App\Services\Arbeitsblaetter\AbSatzService::titelDoppelt($titel, $struktur);
        // Überschrift/Anweisung bleibt bei dem, was direkt darauf folgt (Bild, Tabelle, Raster …)
        $einheiten = [];
        $liste = $gruppieren(array_map(function ($b) use ($gruppieren) {
            if (! empty($b['kinder'])) {
                $b['kinder'] = $gruppieren($b['kinder']);
            }
            return $b;
        }, $struktur['bausteine']));
        // Überschriften/Anweisungen (auch mehrere hintereinander) nie allein am Seitenende: sie bleiben beim
        // nächsten Baustein – auch bei einer Aufgabe, solange sie keine Tabellen/Kästen enthält (verschachteltes
        // „nicht teilen“ verliert in DomPDF Rahmen)
        $bindbar = fn (array $b) => in_array($b['typ'], ['bild', 'rechenfeld', 'schreiblinien', 'zeichenflaeche', 'ankreuzen', 'kasten', 'text'], true)
            || ($b['typ'] === 'aufgabe' && ! collect($b['kinder'] ?? [])->contains(fn ($k) => in_array($k['typ'], ['tabelle', 'kasten', 'bildgruppe'], true)));
        for ($i = 0; $i < count($liste); $i++) {
            $b = $liste[$i];
            if (in_array($b['typ'], ['ueberschrift', 'anweisung'], true)) {
                $lauf = [$b];
                $j = $i + 1;
                while (isset($liste[$j]) && in_array($liste[$j]['typ'], ['ueberschrift', 'anweisung'], true)) {
                    $lauf[] = $liste[$j++];
                }
                if (isset($liste[$j]) && $bindbar($liste[$j]) && $zusammen(array_merge($lauf, [$liste[$j]]))) {
                    $lauf[] = $liste[$j++];
                }
                $einheiten[] = $lauf;
                $i = $j - 1;
                continue;
            }
            $naechster = $liste[$i + 1] ?? null;
            $frage = $b['typ'] === 'text' || ($b['typ'] === 'aufgabe' && empty($b['kinder']));
            if ($frage && $naechster
                && in_array($naechster['typ'], ['bild', 'rechenfeld', 'schreiblinien', 'zeichenflaeche', 'ankreuzen', 'kasten'], true)
                && $zusammen([$b, $naechster])) {
                $einheiten[] = [$b, $naechster];
                $i++;
            } elseif ($frage && ($naechster['typ'] ?? '') === 'schreiblinien' && (int) ($naechster['anzahl'] ?? 3) > 2
                && $zusammen([$b, array_merge($naechster, ['anzahl' => 2])])) {
                // viele Linien: die Frage bleibt wenigstens mit den ersten zwei zusammen, der Rest darf umbrechen
                $einheiten[] = [$b, array_merge($naechster, ['anzahl' => 2, '_geteilt' => true])];
                $einheiten[] = [array_merge($naechster, ['anzahl' => (int) $naechster['anzahl'] - 2, '_fortsetzung' => true])];
                $i++;
            } else {
                $einheiten[] = [$b];
            }
        }
    @endphp
    @if($titel !== '' && ! $titelDoppelt)
        <h1>{{ $titel }}</h1>
    @endif

    @forelse($einheiten as $einheit)
        @if(count($einheit) > 1)
            <div class="ab-zusammen">
                @foreach($einheit as $b)@include('pdf.arbeitsblatt-baustein', ['b' => $b, 'imPaar' => true])@endforeach
            </div>
        @else
            @include('pdf.arbeitsblatt-baustein', ['b' => $einheit[0]])
        @endif
    @empty
        @if($modus === 'html')
            <p class="ab-leer">Noch keine Bausteine – links im Editor hinzufügen.</p>
        @endif
    @endforelse

    @if($modus === 'html' && $fusszeile)<div class="ab-fuss">{{ $fusszeile }}</div>@endif
</div>
</body>
</html>
