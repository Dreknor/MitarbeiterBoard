@php
    // Schriftgröße: custom pt-Wert hat Vorrang vor Enum
    $customPt = $config['typografie']['schriftgroesse_pt'] ?? null;
    $baseSizePt = $customPt ?: match($formatvorlage->schriftgroesse ?? 'normal') {
        'gross'      => 14,
        'sehr_gross' => 18,
        default      => 11,
    };
    $schriftgroesse = $baseSizePt . 'pt';
    $schriftartCss = $formatvorlage->schriftart ?? 'Arial, sans-serif';
    $margins = $config['seitenraender'] ?? ['oben' => 15, 'rechts' => 15, 'unten' => 15, 'links' => 15];
    $titleSize = ($baseSizePt + 2) . 'pt';
    $klasseSize = ($baseSizePt + 1) . 'pt';
    $smallSize = max(8, $baseSizePt - 1) . 'pt';
    $tinySize = max(7, $baseSizePt - 2) . 'pt';
    $zeilenabstand = $config['typografie']['zeilenabstand'] ?? 1.4;
    $abstandFaecher = ($config['abstände']['zwischen_fächern'] ?? 5) . 'mm';
    $abstandAufgaben = ($config['abstände']['zwischen_aufgaben'] ?? 2) . 'mm';
    $minZeilenhoehe = ($config['abstände']['min_fach_zeilenhoehe'] ?? 0);
    $minZeilenhoeheStr = $minZeilenhoehe > 0 ? $minZeilenhoehe . 'mm' : 'auto';
    $zeigeCheck = $config['spalten']['zeige_check_spalte'] ?? true;
    $zeigeKontrolliert = $config['spalten']['zeige_kontrolliert_spalte'] ?? false;
    $zeigeUnterschrift = $config['spalten']['zeige_unterschrift_spalte'] ?? true;
    $zeigeDauer = $config['spalten']['zeige_dauer'] ?? false;
    $colFach = $config['spalten']['fach'] ?? '15%';
    $colAufgaben = $config['spalten']['aufgaben'] ?? '55%';
    $colCheck = $config['spalten']['check'] ?? '5%';
    $colUnterschrift = $config['spalten']['unterschrift'] ?? '25%';
    $namenszeileHoehe = $config['header']['namenszeile_zeilenhoehe'] ?? 0;
    $namenszeileStyle = $namenszeileHoehe > 0 ? "min-height:{$namenszeileHoehe}mm;" : '';
    // Smileys als base64-kodierte SVG-Data-URIs (DomPDF rendert kein inline SVG in Tabellen)
    $smileyGut  = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36"><circle cx="18" cy="18" r="16" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/><circle cx="13" cy="14" r="2" fill="#333"/><circle cx="23" cy="14" r="2" fill="#333"/><path d="M11 22 Q18 29 25 22" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/></svg>');
    $smileyOkay = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36"><circle cx="18" cy="18" r="16" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/><circle cx="13" cy="14" r="2" fill="#333"/><circle cx="23" cy="14" r="2" fill="#333"/><line x1="11" y1="24" x2="25" y2="24" stroke="#333" stroke-width="2" stroke-linecap="round"/></svg>');
    $smileySchw = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36"><circle cx="18" cy="18" r="16" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/><circle cx="13" cy="14" r="2" fill="#333"/><circle cx="23" cy="14" r="2" fill="#333"/><path d="M11 27 Q18 20 25 27" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/></svg>');
    // Spaltenanzahl dynamisch berechnen für colspan
    $colCount = 2
        + ($zeigeDauer ? 1 : 0)
        + ($zeigeCheck ? 1 : 0)
        + ($zeigeUnterschrift ? 1 : 0)
        + ($zeigeKontrolliert ? 1 : 0);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $plan->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: {{ $schriftartCss }};
            font-size: {{ $schriftgroesse }};
            line-height: {{ $zeilenabstand }};
            color: #000;
            padding: {{ $margins['oben'] ?? 15 }}mm {{ $margins['rechts'] ?? 15 }}mm {{ $margins['unten'] ?? 15 }}mm {{ $margins['links'] ?? 15 }}mm;
        }

        .header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #000; padding-bottom: 6px; }
        .header-title { font-size: {{ $titleSize }}; font-weight: bold; text-decoration: underline; }
        .header-meta { margin-top: 4px; }
        .header-row { display: table; width: 100%; }
        .header-left { display: table-cell; }
        .header-right { display: table-cell; text-align: right; vertical-align: top; }
        .header-klasse { font-weight: bold; font-size: {{ $klasseSize }}; }
        .name-feld { margin-top: 4px; font-size: {{ $schriftgroesse }}; }
        .header-freitext { margin-top: 2px; font-size: {{ $smallSize }}; color: #555; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f0f0f0; border: 1px solid #666; padding: 4px 6px; text-align: left; font-weight: bold; font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }}; word-wrap: break-word; overflow-wrap: break-word; white-space: normal; }
        td { border: 1px solid #888; padding: 4px 6px; vertical-align: top; font-family: {{ $schriftartCss }}; word-wrap: break-word; overflow-wrap: break-word; }
        .td-fach { font-weight: bold; vertical-align: middle; width: {{ $colFach }}; max-width: {{ $colFach }}; text-align: center; }
        .td-aufgaben { width: {{ $colAufgaben }}; max-width: {{ $colAufgaben }}; }
        .td-check { width: {{ $colCheck }}; max-width: {{ $colCheck }}; text-align: center; }
        .td-unterschrift { width: {{ $colUnterschrift }}; max-width: {{ $colUnterschrift }}; }
        .td-kontrolliert { width: 12%; max-width: 12%; }

        /* NotoSansSymbols2 für einfache Unicode-Symbole (☺ ☹ ✓ etc.) */
        @font-face {
            font-family: 'NotoSymbols';
            src: url("{{ storage_path('fonts/NotoSansSymbols2-Regular.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        /* OpenDyslexic für Legasthenie-Unterstützung */
        @font-face {
            font-family: 'OpenDyslexic';
            src: url("{{ storage_path('fonts/OpenDyslexic-Regular.otf') }}") format('opentype');
            font-weight: normal;
            font-style: normal;
        }

        /* Fach-Symbol */
        .wp-fach-symbol { margin-right: 3px; font-family: 'NotoSymbols', Arial, sans-serif; }
        .wp-fach-symbol--emoji { font-size: {{ $schriftgroesse }}; font-family: 'NotoSymbols', Arial, sans-serif; }

        /* Abstände */
        .fach-row-gap { margin-bottom: {{ $abstandFaecher }}; }
        .aufgabe-zeile { padding-bottom: {{ $abstandAufgaben }}; border-bottom: 1px dotted #ccc; min-height: {{ $minZeilenhoeheStr }}; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .dauer { color: #555; font-size: {{ $smallSize }}; margin-left: 4px; }

        .footer { margin-top: 12px; border-top: 1px solid #ccc; padding-top: 8px; }
        .selbsteinschaetzung { margin-bottom: 8px; }
        .selbsteinschaetzung-label { font-weight: bold; margin-bottom: 6px; }
        .smiley-face { width: 36px; height: 36px; margin: 0 auto 3px auto; }
        .footer-freitext { margin-top: 8px; font-size: {{ $smallSize }}; color: #555; }

        /* Tägliche Übungen */
        .taegl-uebungen { margin-bottom: 10px; }
        .taegl-uebungen-title { font-weight: bold; font-size: {{ $schriftgroesse }}; border-bottom: 1px solid #666; padding-bottom: 3px; margin-bottom: 5px; }
        .taegl-table { width: 100%; border-collapse: collapse; }
        .taegl-table th, .taegl-table td { border: 1px solid #888; padding: 3px 5px; text-align: center; font-size: {{ $smallSize }}; }
        .taegl-table th:first-child, .taegl-table td:first-child { text-align: left; font-weight: bold; }
        .taegl-check-cell { width: 30px; min-width: 28px; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-row">
        <div class="header-left">
            <div class="header-title">{{ $plan->name }} vom {{ $plan->zeitraum }}</div>
            <div class="name-feld" style="{{ $namenszeileStyle }}">
                @if($plan->isSchuelerplan() && $plan->schueler)
                    Name: {{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}
                @elseif($config['header']['zeige_name_feld'] ?? true)
                    Name: ..............................
                @endif
            </div>
            @if(!empty($config['header']['freitext']))
                <div class="header-freitext">{{ $config['header']['freitext'] }}</div>
            @endif
        </div>
        @if($plan->klasse && ($config['header']['zeige_klasse'] ?? true))
            <div class="header-right">
                <div class="header-klasse">{{ $plan->klasse->name }}</div>
            </div>
        @endif
    </div>
</div>

@if($plan->taegliche_uebungen_aktiv && $plan->taeglicheUebungen->isNotEmpty())
@php
    // Wochentage im Planungszeitraum berechnen
    $pdfWochentage = [];
    if ($plan->gueltig_von && $plan->gueltig_bis) {
        $cur = $plan->gueltig_von->copy();
        while ($cur->lte($plan->gueltig_bis)) {
            if ($cur->isWeekday()) {
                $pdfWochentage[] = $cur->copy();
            }
            $cur->addDay();
        }
    }
    $pdfTagNamen = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];
@endphp
<div class="taegl-uebungen">
    <div class="taegl-uebungen-title">&#x270F; Tägliche Übungen</div>
    <table class="taegl-table">
        <thead>
            <tr>
                <th>Übung</th>
                @foreach($pdfWochentage as $pdfTag)
                    <th class="taegl-check-cell">
                        {{ $pdfTagNamen[($pdfTag->dayOfWeek + 6) % 7] ?? '' }}<br>
                        <span style="font-weight:normal;font-size:smaller;">{{ $pdfTag->format('d.m.') }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($plan->taeglicheUebungen as $uebung)
                <tr>
                    <td>{{ $uebung->aufgabe }}</td>
                    @foreach($pdfWochentage as $pdfTag)
                        <td class="taegl-check-cell">&nbsp;</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<table>
    <thead>
        <tr>
            <th class="td-fach">Fach</th>
            <th class="td-aufgaben">Aufgaben</th>
            @if($zeigeDauer)
                <th style="width:10%; max-width:10%; word-wrap:break-word; white-space:normal;">Dauer</th>
            @endif
            @if($zeigeCheck)
                <th class="td-check">&#10003;</th>
            @endif
            @if($zeigeKontrolliert)
                <th class="td-kontrolliert">Kontrolliert</th>
            @endif
            @if($zeigeUnterschrift)
                <th class="td-unterschrift">Unterschrift</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse($plan->planFaecher as $planFach)
            <tr class="fach-row-gap">
                <td class="td-fach">
                    @if($planFach->fach && $planFach->fach->pdf_symbol_html)
                        {!! $planFach->fach->pdf_symbol_html !!}<br>
                    @endif
                    {{ $planFach->display_name }}
                </td>
                <td class="td-aufgaben">
                    @forelse($planFach->aufgaben as $aufgabe)
                        <div class="aufgabe-zeile">
                            {{ $aufgabe->aufgabe }}
                            @if($aufgabe->dauer && !$zeigeDauer)
                                <span class="dauer">({{ $aufgabe->dauer }})</span>
                            @endif
                        </div>
                    @empty
                        &nbsp;
                    @endforelse
                </td>
                @if($zeigeDauer)
                    <td>
                        @foreach($planFach->aufgaben as $aufgabe)
                            <div class="aufgabe-zeile">{{ $aufgabe->dauer }}</div>
                        @endforeach
                    </td>
                @endif
                @if($zeigeCheck)
                    <td class="td-check">&nbsp;</td>
                @endif
                @if($zeigeKontrolliert)
                    <td class="td-kontrolliert">&nbsp;</td>
                @endif
                @if($zeigeUnterschrift)
                    <td class="td-unterschrift">&nbsp;</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colCount }}" style="text-align:center;color:#888;padding:12px;">Keine Fächer vorhanden.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">

    @if($plan->selbsteinschaetzung > 0 && ($config['footer']['zeige_selbsteinschaetzung'] ?? true))
        <div class="selbsteinschaetzung">
            <div class="selbsteinschaetzung-label">Wie hast du gearbeitet?</div>
            @if($plan->selbsteinschaetzung == 1)
            {{-- Smiley-Variante: 3 Spalten über volle Breite --}}
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-top: 6px;">
                <tr>
                    <td style="width: 33.33%; text-align: center; padding: 8px 4px; border: 1px solid #ccc; vertical-align: middle;">
                        <img src="{{ $smileyGut }}" width="36" height="36" alt=""><br>
                        <span style="font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }};">gut</span>
                    </td>
                    <td style="width: 33.33%; text-align: center; padding: 8px 4px; border: 1px solid #ccc; vertical-align: middle;">
                        <img src="{{ $smileyOkay }}" width="36" height="36" alt=""><br>
                        <span style="font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }};">okay</span>
                    </td>
                    <td style="width: 33.33%; text-align: center; padding: 8px 4px; border: 1px solid #ccc; vertical-align: middle;">
                        <img src="{{ $smileySchw }}" width="36" height="36" alt=""><br>
                        <span style="font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }};">schwierig</span>
                    </td>
                </tr>
            </table>
            @elseif($plan->selbsteinschaetzung == 2)
            {{-- Skala-Variante: 10 Felder über volle Breite --}}
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-top: 6px;">
                <tr>
                    @for($i = 1; $i <= 10; $i++)
                    <td style="width: 10%; text-align: center; padding: 10px 2px; border: 1px solid #ccc; font-size: {{ $smallSize }};">
                        {{ $i }}
                    </td>
                    @endfor
                </tr>
            </table>
            @endif
        </div>
    @endif

    @if(!empty($config['footer']['freitext']))
        <div class="footer-freitext">{{ $config['footer']['freitext'] }}</div>
    @endif

</div>

</body>
</html>

