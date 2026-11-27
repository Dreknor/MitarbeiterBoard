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
    $lueckeBreite = fn ($laenge) => max($l['luecke_pt'], round($laenge * $l['groesse'] * 0.62, 1));
    $bildBreite = function (array $b) use ($l) {
        $box = $b['quelle']['box'] ?? null;
        $anteil = $box ? max(0.15, $box[2] - $box[0]) : 0.45;
        return min(100, round($anteil * 100 * $l['bildfaktor']));
    };
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
        .ab-zusammen { {{ $avoid ? 'page-break-inside: avoid;' : '' }} }
        .ab-nummer { font-weight: bold; display: inline-block; width: {{ round($l['groesse'] * 1.6) }}pt; }
        /* hängender Einzug: Folgezeilen beginnen unter dem Text, nicht unter der Nummer */
        .ab-haengend { padding-left: {{ round($l['groesse'] * 1.6) }}pt; text-indent: -{{ round($l['groesse'] * 1.6) }}pt; }
        .ab-kinder { margin: {{ round($l['groesse'] * 0.4) }}pt 0 0 {{ round($l['groesse'] * 1.6) }}pt; }
        .ab-luecke {
            display: inline-block; vertical-align: baseline;
            border-bottom: {{ $l['linienstaerke'] }}pt solid {{ $l['farbe_linie'] }};
            height: {{ round($l['groesse'] * 1.05, 1) }}pt; margin: 0 2pt;
        }
        table.ab-option { width: 100%; border-collapse: separate; border-spacing: 0; margin: {{ round($l['groesse'] * 0.35) }}pt 0; {{ $avoid ? 'page-break-inside: avoid;' : '' }} }
        td.ab-kaestchen-zelle { width: {{ $l['ankreuz_pt'] + 2 * max(1, $l['linienstaerke']) }}pt; padding: 0; vertical-align: middle; }
        img.ab-kaestchen { width: {{ $l['ankreuz_pt'] }}pt; height: {{ $l['ankreuz_pt'] }}pt; display: block; }
        td.ab-option-text { width: 100%; padding-left: 8pt; vertical-align: middle; }
        .ab-kasten { border: {{ max(1, $l['linienstaerke']) }}pt solid {{ $l['farbe_linie'] }}; padding: {{ round($l['groesse'] * 0.5) }}pt {{ round($l['groesse'] * 0.7) }}pt; }
        .ab-kasten-titel { font-weight: bold; margin-bottom: {{ round($l['groesse'] * 0.25) }}pt; }
        table.ab-raster { border-collapse: collapse; }
        table.ab-raster td { width: {{ $l['raster_pt'] }}pt; height: {{ $l['raster_pt'] }}pt; padding: 0; border: {{ max(0.5, round($l['linienstaerke'] * 0.5, 2)) }}pt solid {{ $l['farbe_linie'] }}; }
        .ab-linie { height: {{ $l['linienabstand_pt'] }}pt; border-bottom: {{ $l['linienstaerke'] }}pt solid {{ $l['farbe_linie'] }}; }
        .ab-schreiblinien { {{ $avoid ? 'page-break-inside: avoid;' : '' }} }
        table.ab-tabelle { border-collapse: collapse; width: 100%; {{ $avoid ? 'page-break-inside: avoid;' : '' }} }
        table.ab-tabelle td, table.ab-tabelle th {
            border: {{ max(1, $l['linienstaerke']) }}pt solid {{ $l['farbe_linie'] }};
            padding: {{ round($l['groesse'] * 0.3) }}pt {{ round($l['groesse'] * 0.4) }}pt;
            height: {{ round($l['zeilenhoehe_pt'] * 1.2) }}pt; text-align: left; vertical-align: top;
        }
        table.ab-tabelle th { font-weight: bold; }
        .ab-bild { {{ $avoid ? 'page-break-inside: avoid;' : '' }} text-align: left; }
        .ab-bild img { max-width: 100%; {{ $l['invertiert'] ? 'background: #fff; padding: 4pt;' : '' }} }
        .ab-bild-fehlt { border: {{ $l['linienstaerke'] }}pt dashed {{ $l['farbe_linie'] }}; padding: 8pt; font-size: {{ max(12, round($l['groesse'] * 0.7)) }}pt; }
        @if($modus === 'html' && $l['bild_kontrast'])
        .ab-bild img { filter: grayscale(1) contrast(2.2); }
        @endif
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
        $titelDoppelt = $erste && $erste['typ'] === 'ueberschrift' && mb_strtolower(trim($erste['text'])) === mb_strtolower(trim($titel));
    @endphp
    @if($titel !== '' && ! $titelDoppelt)
        <h1>{{ $titel }}</h1>
    @endif

    @forelse($struktur['bausteine'] as $b)
        @include('pdf.arbeitsblatt-baustein', ['b' => $b])
    @empty
        @if($modus === 'html')
            <p class="ab-leer">Noch keine Bausteine – links im Editor hinzufügen.</p>
        @endif
    @endforelse

    @if($modus === 'html' && $fusszeile)<div class="ab-fuss">{{ $fusszeile }}</div>@endif
</div>
</body>
</html>
