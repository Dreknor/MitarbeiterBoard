@php
    $baseSizePt  = 16;
    $schriftartCss = $formatvorlage->schriftart ?? 'Arial, sans-serif';
    $margins     = $config['seitenraender'] ?? ['oben' => 20, 'rechts' => 20, 'unten' => 20, 'links' => 20];
    $titleSize   = '18pt';
    $smallSize   = '14pt';
    $tinySize    = '12pt';
    $namenszeileHoehe = $config['header']['namenszeile_zeilenhoehe'] ?? 0;
    $namenszeileAbstandOben = $config['header']['namenszeile_abstand_oben'] ?? 4;
    $namenszeileStyle = ($namenszeileHoehe > 0 ? "min-height:{$namenszeileHoehe}mm;" : '') . "margin-top:{$namenszeileAbstandOben}px;";
    $zeigeCheck        = $config['spalten']['zeige_check_spalte'] ?? true;
    $zeigeKontrolliert = $config['spalten']['zeige_kontrolliert_spalte'] ?? false;
    $zeigeUnterschrift = $config['spalten']['zeige_unterschrift_spalte'] ?? true;
    $zeigeDauer        = $config['spalten']['zeige_dauer'] ?? false;
    $colFach        = $config['spalten']['fach'] ?? '20%';
    $colAufgaben    = $config['spalten']['aufgaben'] ?? '55%';
    $colCheck       = $config['spalten']['check'] ?? '5%';
    $colUnterschrift = $config['spalten']['unterschrift'] ?? '20%';
    $colCount = 2
        + ($zeigeDauer ? 1 : 0)
        + ($zeigeCheck ? 1 : 0)
        + ($zeigeUnterschrift ? 1 : 0)
        + ($zeigeKontrolliert ? 1 : 0);
    $colKontrolliert = $config['spalten']['kontrolliert'] ?? '12%';
    $labelUnterschrift = ($config['spalten']['label_trennung_unterschrift'] ?? false) ? 'Unter-schrift' : 'Unterschrift';
    $labelKontrolliert = ($config['spalten']['label_trennung_kontrolliert'] ?? false) ? 'Kon-trolliert' : 'Kontrolliert';
    $checkSvg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14"><polyline points="2,7 6,11 12,3" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');
    $bleistiftSvg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14"><path d="M2 10 L9 3 L11 5 L4 12 Z" fill="#333"/><path d="M9 3 L11 1 L13 3 L11 5 Z" fill="#555"/><path d="M2 10 L1 13 L4 12 Z" fill="#222"/></svg>');
    // Smileys als base64-kodierte SVG-Data-URIs
    $smileyGut  = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21" fill="#fff9c4" stroke="#f9a825" stroke-width="2.5"/><circle cx="17" cy="18" r="2.5" fill="#333"/><circle cx="31" cy="18" r="2.5" fill="#333"/><path d="M14 29 Q24 38 34 29" fill="none" stroke="#333" stroke-width="2.5" stroke-linecap="round"/></svg>');
    $smileyOkay = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21" fill="#fff9c4" stroke="#f9a825" stroke-width="2.5"/><circle cx="17" cy="18" r="2.5" fill="#333"/><circle cx="31" cy="18" r="2.5" fill="#333"/><line x1="14" y1="32" x2="34" y2="32" stroke="#333" stroke-width="2.5" stroke-linecap="round"/></svg>');
    $smileySchw = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21" fill="#fff9c4" stroke="#f9a825" stroke-width="2.5"/><circle cx="17" cy="18" r="2.5" fill="#333"/><circle cx="31" cy="18" r="2.5" fill="#333"/><path d="M14 36 Q24 27 34 36" fill="none" stroke="#333" stroke-width="2.5" stroke-linecap="round"/></svg>');
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $plan->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        /* NotoSansSymbols2 für Unicode-Symbole */
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
        body {
            font-family: {{ $schriftartCss }};
            font-size: {{ $baseSizePt }}pt;
            color: #000;
            padding: {{ $margins['oben'] ?? 20 }}mm {{ $margins['rechts'] ?? 20 }}mm {{ $margins['unten'] ?? 20 }}mm {{ $margins['links'] ?? 20 }}mm;
            line-height: 1.6;
        }
        .header { border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 14px; }
        .header-title { font-size: {{ $titleSize }}; font-weight: bold; text-decoration: underline; }
        .header-sub   { font-size: {{ $baseSizePt }}pt; margin-top: 6px; }
        .name-feld    { margin-top: 6px; font-size: {{ $baseSizePt }}pt; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #ddd; border: 2px solid #444; padding: 6px 10px; font-size: {{ $baseSizePt }}pt; font-weight: bold; font-family: {{ $schriftartCss }}; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; white-space: normal; overflow: hidden; }
        td { border: 2px solid #666; padding: 8px 10px; vertical-align: top; font-family: {{ $schriftartCss }}; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; white-space: normal; overflow: hidden; }
        .td-fach     { font-weight: bold; width: {{ $colFach }}; text-align: center; vertical-align: middle; font-size: {{ $baseSizePt }}pt; }
        .td-aufgaben { width: {{ $colAufgaben }}; }
        .td-check    { width: {{ $colCheck }}; text-align: center; }
        .td-unterschrift { width: {{ $colUnterschrift }}; }
        .td-kontrolliert { width: {{ $colKontrolliert }}; }
        .aufgabe-zeile { padding: 4px 0; border-bottom: 1px dotted #aaa; font-size: {{ $baseSizePt }}pt; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .footer { margin-top: 20px; border-top: 2px solid #aaa; padding-top: 12px; }
        .selbsteinschaetzung-label { font-weight: bold; font-size: {{ $baseSizePt }}pt; margin-bottom: 8px; }
        .smiley-face { width: 48px; height: 48px; margin: 0 auto 4px auto; }
        /* Tägliche Übungen */
        .taegl-uebungen { margin-bottom: 12px; }
        .taegl-uebungen-title { font-weight: bold; font-size: {{ $baseSizePt }}pt; border-bottom: 2px solid #666; padding-bottom: 4px; margin-bottom: 6px; }
        .taegl-table { width: 100%; table-layout: auto; border-collapse: collapse; }
        .taegl-table th, .taegl-table td { border: 2px solid #666; padding: 5px 8px; text-align: center; font-size: {{ $smallSize }}; }
        .taegl-table th:first-child, .taegl-table td:first-child { text-align: left; font-weight: bold; }
        .taegl-check-cell { width: 38px; min-width: 34px; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-title">{{ $plan->name }}</div>
    @if(!($vorschau ?? false))
        <div class="header-sub">{{ $plan->zeitraum }}</div>
    @endif
    <div class="name-feld" style="{{ $namenszeileStyle }}">
        @if(!($vorschau ?? false) && $plan->isSchuelerplan() && $plan->schueler)
            Name: <strong>{{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}</strong>
        @else
            Name: ......................................
        @endif
    </div>
</div>

{{-- Tägliche Übungen --}}
@if(!($vorschau ?? false) && ($plan->taegliche_uebungen_aktiv ?? false) && isset($plan->taeglicheUebungen) && $plan->taeglicheUebungen->isNotEmpty())
@php
    $grossWochentage = [];
    if ($plan->gueltig_von && $plan->gueltig_bis) {
        $grossCur = $plan->gueltig_von->copy();
        while ($grossCur->lte($plan->gueltig_bis)) {
            if ($grossCur->isWeekday()) { $grossWochentage[] = $grossCur->copy(); }
            $grossCur->addDay();
        }
    }
    $grossTagNamen = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];
@endphp
<div class="taegl-uebungen">
    <div class="taegl-uebungen-title"><img src="{{ $bleistiftSvg }}" width="13" height="13" alt="" style="vertical-align:middle;margin-right:3px;"> Tägliche Übungen</div>
    <table class="taegl-table">
        <thead>
            <tr>
                <th>Übung</th>
                @foreach($grossWochentage as $grossTag)
                    <th class="taegl-check-cell">
                        {{ $grossTagNamen[($grossTag->dayOfWeek + 6) % 7] ?? '' }}<br>
                        <span style="font-weight:normal;font-size:smaller;">{{ $grossTag->format('d.m.') }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($plan->taeglicheUebungen as $grossUebung)
                <tr>
                    <td>{{ $grossUebung->aufgabe }}</td>
                    @foreach($grossWochentage as $grossTag)
                        <td class="taegl-check-cell">&nbsp;</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<table>
    <colgroup>
        <col style="width: {{ $colFach }}">
        <col style="width: {{ $colAufgaben }}">
        @if($zeigeKontrolliert) <col style="width: {{ $colKontrolliert }}"> @endif
        @if($zeigeCheck) <col style="width: {{ $colCheck }}"> @endif
        @if($zeigeKontrolliert) <col style="width: {{ $colKontrolliert }}"> @endif
        @if($zeigeUnterschrift) <col style="width: {{ $colUnterschrift }}"> @endif
    </colgroup>
    <thead>
        <tr>
            <th class="td-fach">Fach</th>
            <th class="td-aufgaben">Aufgaben</th>
            @if($zeigeDauer)
                <th>Dauer</th>
            @endif
            @if($zeigeCheck)
                <th class="td-check"><img src="{{ $checkSvg }}" width="14" height="14" alt="ok"></th>
            @endif
            @if($zeigeKontrolliert)
                <th class="td-kontrolliert">{{ $labelKontrolliert }}</th>
            @endif
            @if($zeigeUnterschrift)
                <th class="td-unterschrift">{{ $labelUnterschrift }}</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @if($vorschau ?? false)
            <tr>
                <td class="td-fach">Deutsch</td>
                <td class="td-aufgaben"><div class="aufgabe-zeile">Beispiel-Aufgabe 1</div><div class="aufgabe-zeile">Beispiel-Aufgabe 2</div></td>
                @if($zeigeDauer) <td>&nbsp;</td> @endif
                @if($zeigeCheck) <td class="td-check">&nbsp;</td> @endif
                @if($zeigeKontrolliert) <td class="td-kontrolliert">&nbsp;</td> @endif
                @if($zeigeUnterschrift) <td class="td-unterschrift">&nbsp;</td> @endif
            </tr>
            <tr>
                <td class="td-fach">Mathe</td>
                <td class="td-aufgaben"><div class="aufgabe-zeile">S. 45, Nr. 1–5</div></td>
                @if($zeigeDauer) <td>&nbsp;</td> @endif
                @if($zeigeCheck) <td class="td-check">&nbsp;</td> @endif
                @if($zeigeKontrolliert) <td class="td-kontrolliert">&nbsp;</td> @endif
                @if($zeigeUnterschrift) <td class="td-unterschrift">&nbsp;</td> @endif
            </tr>
        @else
            @forelse($plan->planFaecher as $planFach)
                <tr>
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
                                    <span style="color:#666;font-size:{{ $smallSize }};">({{ $aufgabe->dauer }})</span>
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
                    @if($zeigeCheck) <td class="td-check">&nbsp;</td> @endif
                    @if($zeigeKontrolliert) <td class="td-kontrolliert">&nbsp;</td> @endif
                    @if($zeigeUnterschrift) <td class="td-unterschrift">&nbsp;</td> @endif
                </tr>
            @empty
                <tr><td colspan="{{ $colCount }}" style="text-align:center;color:#888;padding:16px;">Keine Fächer vorhanden.</td></tr>
            @endforelse
        @endif
    </tbody>
</table>

@if($plan->selbsteinschaetzung > 0 && ($config['footer']['zeige_selbsteinschaetzung'] ?? true))
    <div class="footer">
        <div class="selbsteinschaetzung-label">Wie hast du gearbeitet?</div>
        @if($plan->selbsteinschaetzung == 1)
        {{-- Smiley-Variante: 3 Spalten über volle Breite --}}
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-top: 8px;">
            <tr>
                <td style="width: 33.33%; text-align: center; padding: 10px 4px; border: 1px solid #ccc; vertical-align: middle;">
                    <img src="{{ $smileyGut }}" width="48" height="48" alt=""><br>
                    <span style="font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }};">gut</span>
                </td>
                <td style="width: 33.33%; text-align: center; padding: 10px 4px; border: 1px solid #ccc; vertical-align: middle;">
                    <img src="{{ $smileyOkay }}" width="48" height="48" alt=""><br>
                    <span style="font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }};">okay</span>
                </td>
                <td style="width: 33.33%; text-align: center; padding: 10px 4px; border: 1px solid #ccc; vertical-align: middle;">
                    <img src="{{ $smileySchw }}" width="48" height="48" alt=""><br>
                    <span style="font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }};">schwierig</span>
                </td>
            </tr>
        </table>
        @elseif($plan->selbsteinschaetzung == 2)
        {{-- Skala-Variante: 10 Felder über volle Breite --}}
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-top: 8px;">
            <tr>
                @for($i = 1; $i <= 10; $i++)
                <td style="width: 10%; text-align: center; padding: 12px 2px; border: 1px solid #ccc; font-size: {{ $baseSizePt }}pt;">
                    {{ $i }}
                </td>
                @endfor
            </tr>
        </table>
        @endif
    </div>
@endif

</body>
</html>

