{{--
    Ergebnisboard als PDF (DomPDF, A4 quer). Kein CSS-Grid/Flex – das Raster kommt als
    Tabelle mit colspan/rowspan aus ErgebnisboardController::pdfTabelle().
--}}
@php
    $palette = [
        'gruen'   => ['flaeche' => '#f0fdf7', 'stark' => '#d1fae5', 'rand' => '#a7f3d0', 'text' => '#022c22', 'hinweis' => '#065f46', 'badge' => '#a7f3d0', 'punkt' => '#10b981'],
        'gelb'    => ['flaeche' => '#fffbeb', 'stark' => '#fef3c7', 'rand' => '#fde68a', 'text' => '#451a03', 'hinweis' => '#78350f', 'badge' => '#fde68a', 'punkt' => '#f59e0b'],
        'rot'     => ['flaeche' => '#fff5f6', 'stark' => '#ffe4e6', 'rand' => '#fecdd3', 'text' => '#4c0519', 'hinweis' => '#9f1239', 'badge' => '#fecdd3', 'punkt' => '#f43f5e'],
        'blau'    => ['flaeche' => '#f0f9ff', 'stark' => '#e0f2fe', 'rand' => '#bae6fd', 'text' => '#082f49', 'hinweis' => '#0c4a6e', 'badge' => '#bae6fd', 'punkt' => '#0ea5e9'],
        'lila'    => ['flaeche' => '#f3f5ff', 'stark' => '#e0e7ff', 'rand' => '#c7d2fe', 'text' => '#1e1b4b', 'hinweis' => '#3730a3', 'badge' => '#c7d2fe', 'punkt' => '#6366f1'],
        'grau'    => ['flaeche' => '#f1f5f9', 'stark' => '#e2e8f0', 'rand' => '#cbd5e1', 'text' => '#0f172a', 'hinweis' => '#475569', 'badge' => '#e2e8f0', 'punkt' => '#94a3b8'],
        'orange'  => ['flaeche' => '#fff7ed', 'stark' => '#ffedd5', 'rand' => '#fed7aa', 'text' => '#431407', 'hinweis' => '#9a3412', 'badge' => '#fed7aa', 'punkt' => '#f97316'],
        'tuerkis' => ['flaeche' => '#f0fdfa', 'stark' => '#ccfbf1', 'rand' => '#99f6e4', 'text' => '#042f2e', 'hinweis' => '#115e59', 'badge' => '#99f6e4', 'punkt' => '#14b8a6'],
    ];
    $farbe = fn ($name) => $palette[$name] ?? $palette['grau'];

    // Spaltenbreiten aus dem Grid-Template (fr/px/% → Prozent)
    $teile = preg_split('/\s+/', trim($daten['spalten'] ?: '1fr'));
    $gewichte = array_map(fn ($t) => max(0.1, (float) $t), $teile);
    $summe = array_sum($gewichte) ?: 1;
    $breiten = array_map(fn ($g) => round($g / $summe * 100, 2), $gewichte);

    $karten = collect($daten['karten'])->sortBy([['sort', 'asc'], ['id', 'asc']])->groupBy('bereich');
    $unter = collect($daten['bereiche'])->filter(fn ($b) => ($b['eltern'] ?? null) !== null)->groupBy('eltern');
    $achsen = $daten['achsen'] ?? null;
    $legende = $daten['legende'] ?? null;
    $papier = ($daten['farbschema'] ?? 'klar') === 'papier';
    $hatFokus = collect($daten['karten'])->contains('fokus', true);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $daten['titel'] }}</title>
    <style>
        @page { margin: 8mm 9mm 11mm 9mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 6pt; color: #1e293b; line-height: 1.22; @if($papier) background: #faf7f2; @endif }
        .kopf { width: 100%; border-collapse: collapse; margin-bottom: 1.5mm; }
        .kopf td { vertical-align: middle; padding: 0; }
        .kopf img { height: 6mm; }
        .schulname { font-size: 7.5pt; color: #334155; }
        .kopf .schule { text-align: right; font-size: 6.8pt; color: #64748b; line-height: 1.35; }
        .kopf .schule b { font-size: 8pt; color: #334155; }
        .kopf-linie { border: 0; border-top: 1pt solid #cbd5e1; margin: 0 0 2mm 0; }
        .titelzeile { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
        .titelzeile td { vertical-align: bottom; padding: 0; }
        .badges { font-size: 6.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: .4pt; }
        .titel { font-size: 12pt; font-weight: bold; color: #0f172a; margin: .6mm 0 0 0; }
        .untertitel { font-size: 7.5pt; color: #64748b; margin: .6mm 0 0 0; }
        .stand { border: .6pt solid #e2e8f0; background: #f8fafc; padding: 1.5mm 3mm; text-align: right; }
        .stand .label { font-size: 6pt; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: .4pt; }
        .stand .wert { font-size: 8.5pt; font-weight: bold; color: #334155; }
        .achse { font-size: 6.4pt; color: #475569; background: #f8fafc; border: .6pt solid #e2e8f0; padding: .8mm 2.5mm; margin: 0 0 1.5mm 0; }
        .achse b { color: #0f172a; text-transform: uppercase; letter-spacing: .6pt; }
        table.raster { width: 100%; border-collapse: collapse; }
        table.raster td.spalte { vertical-align: top; padding: 0 1mm; }
        table.raster td.spalte:first-child { padding-left: 0; }
        table.raster td.spalte:last-child { padding-right: 0; }
        .feld { padding: 1.6mm 1.8mm; border: .7pt solid; margin-bottom: 1.5mm; }
        .feld-badge { font-size: 5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .3pt; padding: .3mm 1.4mm; }
        .feld-titel { font-size: 7.8pt; font-weight: bold; margin: .5mm 0 0 0; }
        .feld-hinweis { font-size: 5.6pt; margin: .2mm 0 .8mm 0; }
        .feld-kopf { border-bottom: .6pt solid; padding-bottom: .6mm; margin-bottom: .8mm; }
        table.karten { width: 100%; border-collapse: separate; border-spacing: 0 .8mm; table-layout: fixed; }
        table.karten.zwei { border-spacing: .8mm .8mm; margin: 0 -.8mm; }
        table.karten td { vertical-align: top; padding: 0; }
        .karte { background: #ffffff; border: .6pt solid; padding: .8mm 1.2mm; }
        .karte.fokus { border: 1.3pt solid #fbbf24; }
        .karte-titel { font-weight: bold; font-size: 6.1pt; color: #1e293b; }
        .karte-zusatz { font-size: 5.3pt; color: #64748b; margin-top: .3mm; }
        .karte-meta { font-size: 5.4pt; color: #64748b; margin-top: .5mm; }
        .etikett { font-size: 5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .3pt; padding: .2mm 1.2mm; }
        .stern { color: #b45309; font-weight: bold; }
        .ueberfaellig { color: #b91c1c; font-weight: bold; }
        .erledigt { color: #047857; font-weight: bold; }
        .unter { margin-top: 1mm; padding: 1.2mm 1.4mm; border: .6pt solid; }
        .unter-titel { font-size: 5.8pt; font-weight: bold; text-transform: uppercase; letter-spacing: .4pt; }
        .leer { font-size: 6.4pt; color: #94a3b8; font-style: italic; padding: 1mm 0; }
        .legende { margin-top: 1.5mm; font-size: 6.2pt; color: #475569; }
        .legende.seitlich { background: #ffffff; border: .6pt solid #e2e8f0; padding: 1.4mm 1.8mm; margin-top: 0; line-height: 1.6; }
        .legende.seitlich b.titel { display: block; }
        .legende b.titel { font-size: 6.4pt; text-transform: uppercase; letter-spacing: .4pt; color: #1e293b; margin-right: 3mm; }
        .punkt { display: inline-block; width: 2mm; height: 2mm; border-radius: 1mm; margin: 0 1mm 0 3mm; }
        .fuss { margin-top: 1.5mm; font-size: 6.4pt; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="kopf">
        <tr>
            <td>@if($kopf['logo'])<img src="{{ $kopf['logo'] }}" alt="" style="vertical-align: middle; margin-right: 2mm;">@endif<b class="schulname">{{ $kopf['name'] }}</b></td>
            <td class="schule">{{ implode(' · ', $kopf['adresse']) }}@if($kopf['kontakt']) · {{ $kopf['kontakt'] }}@endif</td>
        </tr>
    </table>
    <hr class="kopf-linie">

    <table class="titelzeile">
        <tr>
            <td>
                <div class="badges">{{ $daten['vorlage_titel'] }}@if($daten['meeting']) &nbsp;•&nbsp; {{ $daten['meeting']['titel'] }}@endif</div>
                <p class="titel">{{ $daten['titel'] }}</p>
                @if($daten['untertitel'])<p class="untertitel">{{ $daten['untertitel'] }}</p>@endif
            </td>
            @if($daten['kopfzeile'])
                <td style="width: 80mm;">
                    <div class="stand">
                        <div class="label">Stand / Gremium</div>
                        <div class="wert">{{ $daten['kopfzeile'] }}</div>
                    </div>
                </td>
            @endif
        </tr>
    </table>

    @if($achsen && !empty($achsen['y']))
        <div class="achse">
            <b>{{ $achsen['y']['titel'] ?? '' }}</b>@if(!empty($achsen['y']['zusatz'])) ({{ $achsen['y']['zusatz'] }})@endif:
            oben {{ $achsen['y']['oben'] ?? '' }} &nbsp;↓&nbsp; unten {{ $achsen['y']['unten'] ?? '' }}
        </div>
    @endif

    @php
        $haupt = collect($daten['bereiche'])->filter(fn ($b) => ($b['eltern'] ?? null) === null)
            ->sortBy([['spalte', 'asc'], ['zeile', 'asc']])->values();
        // Ohne Felder über mehrere Spalten: spaltenweise stapeln (DomPDF kommt mit rowspan schlecht zurecht)
        $spaltenweise = $haupt->every(fn ($b) => (int) $b['spalten'] === 1);
        // Wie im Original: Legende unten in der Seitenspalte, wenn die letzte Spalte genau ein Feld hat
        $letzte = count($breiten);
        $legendeSeitlich = $spaltenweise && $letzte > 1 && empty($daten['bereiche_frei'])
            && $haupt->where('spalte', $letzte)->count() === 1 && (($legende && !empty($legende['eintraege'])) || $hatFokus);
    @endphp
    @if($spaltenweise)
        <table class="raster">
            <tr>
                @foreach($breiten as $i => $breite)
                    <td class="spalte" style="width: {{ $breite }}%;">
                        @foreach($haupt->where('spalte', $i + 1) as $b)
                            @include('pdf.partials.ergebnisboard_feld', ['b' => $b, 'f' => $farbe($b['farbe'] ?? 'grau')])
                        @endforeach
                        @if($legendeSeitlich && $i + 1 === $letzte)
                            <div class="legende seitlich">
                                @if($legende)
                                    <b class="titel">{{ $legende['titel'] ?? 'Legende' }}</b>
                                    @foreach($legende['eintraege'] ?? [] as $e)
                                        <div><span class="punkt" style="background: {{ $farbe($e['farbe'] ?? 'grau')['punkt'] }}; margin-left: 0;"></span>{{ $e['text'] }}</div>
                                    @endforeach
                                @endif
                                @if($hatFokus)
                                    <div><span class="stern">★</span> Markierter Arbeitsschwerpunkt</div>
                                @endif
                            </div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @else
        <table class="raster">
            @foreach($tabelle as $zeile)
                @continue(collect($zeile)->filter()->isEmpty())
                <tr>
                    @foreach($zeile as $zelle)
                        @if($zelle === null)
                            <td></td>
                        @else
                            <td class="spalte" colspan="{{ $zelle['colspan'] }}">
                                @include('pdf.partials.ergebnisboard_feld', ['b' => $zelle['bereich'], 'f' => $farbe($zelle['bereich']['farbe'] ?? 'grau')])
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    @php
        $fusstext = ($daten['fusszeile'] ? $daten['fusszeile'].' · ' : '').'Stand '.$stand->format('d.m.Y H:i').' Uhr'
            .($daten['ersteller'] ? ' · erstellt von '.$daten['ersteller'] : '');
    @endphp
    @if($achsen && !empty($achsen['x']))
        <table style="width: 100%; border-collapse: collapse; margin-top: .5mm;">
            <tr>
                <td style="padding: 0; vertical-align: middle;">
                    <div class="achse" style="margin: 0;">
                        <b>{{ $achsen['x']['titel'] ?? '' }}</b>@if(!empty($achsen['x']['ziel'])) → {{ $achsen['x']['ziel'] }}@endif:
                        links {{ $achsen['x']['links'] ?? '' }} &nbsp;→&nbsp; rechts {{ $achsen['x']['rechts'] ?? '' }}
                    </div>
                </td>
                <td class="fuss" style="padding: 0 0 0 3mm; margin: 0; vertical-align: middle; text-align: right; width: 45%;">{{ $fusstext }}</td>
            </tr>
        </table>
    @endif

    @if((($legende && !empty($legende['eintraege'])) || $hatFokus) && ! $legendeSeitlich)
        <div class="legende">
            @if($legende)
                <b class="titel">{{ $legende['titel'] ?? 'Legende' }}</b>
                @foreach($legende['eintraege'] ?? [] as $e)
                    <span class="punkt" style="background: {{ $farbe($e['farbe'] ?? 'grau')['punkt'] }};"></span>{{ $e['text'] }}
                @endforeach
            @endif
            @if($hatFokus)
                <span class="stern" style="margin-left: 4mm;">★</span> Markierter Arbeitsschwerpunkt
            @endif
        </div>
    @endif

    @if(!($achsen && !empty($achsen['x'])))
        <div class="fuss">{{ $fusstext }}</div>
    @endif
</body>
</html>
