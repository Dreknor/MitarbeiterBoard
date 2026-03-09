@php
    $baseSizePt    = match($formatvorlage->schriftgroesse ?? 'normal') {
        'gross'      => 13,
        'sehr_gross' => 16,
        default      => 11,
    };
    $schriftartCss = $formatvorlage->schriftart ?? 'Arial, sans-serif';
    $margins       = $config['seitenraender'] ?? ['oben' => 15, 'rechts' => 15, 'unten' => 15, 'links' => 15];
    $titleSize     = ($baseSizePt + 3) . 'pt';
    $nameSize      = ($baseSizePt + 4) . 'pt';
    $smallSize     = max(8, $baseSizePt - 1) . 'pt';
    $tinySize      = max(7, $baseSizePt - 2) . 'pt';
    $namenszeileHoehe = $config['header']['namenszeile_zeilenhoehe'] ?? 0;
    $namenszeileStyle = $namenszeileHoehe > 0 ? "min-height:{$namenszeileHoehe}mm;" : '';
    $zeigeCheck        = $config['spalten']['zeige_check_spalte'] ?? true;
    $zeigeKontrolliert = $config['spalten']['zeige_kontrolliert_spalte'] ?? false;
    $zeigeUnterschrift = $config['spalten']['zeige_unterschrift_spalte'] ?? true;
    $zeigeDauer        = $config['spalten']['zeige_dauer'] ?? false;
    $colFach        = $config['spalten']['fach'] ?? '18%';
    $colAufgaben    = $config['spalten']['aufgaben'] ?? '55%';
    $colCheck       = $config['spalten']['check'] ?? '5%';
    $colUnterschrift = $config['spalten']['unterschrift'] ?? '20%';
    $colCount = 2
        + ($zeigeDauer ? 1 : 0)
        + ($zeigeCheck ? 1 : 0)
        + ($zeigeUnterschrift ? 1 : 0)
        + ($zeigeKontrolliert ? 1 : 0);
    // Smileys als base64-kodierte SVG-Data-URIs
    $smileyGut  = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/><circle cx="14" cy="15" r="2" fill="#333"/><circle cx="26" cy="15" r="2" fill="#333"/><path d="M12 25 Q20 33 28 25" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/></svg>');
    $smileyOkay = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/><circle cx="14" cy="15" r="2" fill="#333"/><circle cx="26" cy="15" r="2" fill="#333"/><line x1="12" y1="28" x2="28" y2="28" stroke="#333" stroke-width="2" stroke-linecap="round"/></svg>');
    $smileySchw = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/><circle cx="14" cy="15" r="2" fill="#333"/><circle cx="26" cy="15" r="2" fill="#333"/><path d="M12 32 Q20 24 28 32" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/></svg>');
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
            padding: {{ $margins['oben'] }}mm {{ $margins['rechts'] }}mm {{ $margins['unten'] }}mm {{ $margins['links'] }}mm;
        }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
            text-align: center;
        }
        .header-title  { font-size: {{ $titleSize }}; font-weight: bold; }
        .header-name   { font-size: {{ $nameSize }}; font-weight: bold; margin-top: 6px; color: #222; }
        .header-zeitraum { font-size: {{ $baseSizePt }}pt; color: #555; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background-color: #e8e8e8; border: 1px solid #555; padding: 4px 8px;
             text-align: left; font-weight: bold; font-size: {{ $smallSize }}; font-family: {{ $schriftartCss }}; }
        td { border: 1px solid #888; padding: 5px 8px; vertical-align: top; font-family: {{ $schriftartCss }}; }
        .td-fach     { font-weight: bold; width: {{ $colFach }}; vertical-align: middle; text-align: center; }
        .td-aufgaben { width: {{ $colAufgaben }}; }
        .td-check    { width: {{ $colCheck }}; text-align: center; }
        .td-unterschrift { width: {{ $colUnterschrift }}; }
        .td-kontrolliert { width: 12%; }
        .aufgabe-zeile { padding: 2px 0; border-bottom: 1px dotted #ccc; font-size: {{ $baseSizePt }}pt; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .dauer { color: #666; font-size: {{ $smallSize }}; margin-left: 4px; }
        .footer { margin-top: 14px; }
        .selbsteinschaetzung-label { font-weight: bold; margin-bottom: 6px; }
        .smiley-face { width: 40px; height: 40px; margin: 0 auto 3px auto; }
        .unterschrift-zeilen { display: table; width: 100%; margin-top: 14px; }
        .unterschrift-zeile { display: table-cell; padding-right: 20px; }
        .unterschrift-linie { border-bottom: 1px solid #000; height: 20px; }
        .unterschrift-text  { font-size: {{ $tinySize }}; color: #666; }
        .kommentar-bereich  { margin-top: 16px; border: 1px dashed #aaa; padding: 8px; min-height: 40px; }
        .kommentar-label    { font-size: {{ $smallSize }}; color: #777; margin-bottom: 4px; }
        /* Tägliche Übungen */
        .taegl-uebungen { margin-bottom: 10px; }
        .taegl-uebungen-title { font-weight: bold; font-size: {{ $baseSizePt }}pt; border-bottom: 1px solid #666; padding-bottom: 3px; margin-bottom: 5px; }
        .taegl-table { width: 100%; border-collapse: collapse; }
        .taegl-table th, .taegl-table td { border: 1px solid #888; padding: 3px 5px; text-align: center; font-size: {{ $smallSize }}; }
        .taegl-table th:first-child, .taegl-table td:first-child { text-align: left; font-weight: bold; }
        .taegl-check-cell { width: 30px; min-width: 28px; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-title">Mein Wochenplan</div>
    @if(($vorschau ?? false))
        <div class="header-name">Max Mustermann</div>
        <div class="header-zeitraum">03.03. – 07.03.2025</div>
    @else
        @if($plan->isSchuelerplan() && $plan->schueler)
            <div class="header-name">{{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}</div>
        @else
            <div style="font-size:{{ $baseSizePt }}pt; margin-top:6px; {{ $namenszeileStyle }}">Name: ......................................</div>
        @endif
        <div class="header-zeitraum">{{ $plan->zeitraum }}</div>
    @endif
</div>

{{-- Tägliche Übungen --}}
@if(!($vorschau ?? false) && ($plan->taegliche_uebungen_aktiv ?? false) && isset($plan->taeglicheUebungen) && $plan->taeglicheUebungen->isNotEmpty())
@php
    $indivWochentage = [];
    if ($plan->gueltig_von && $plan->gueltig_bis) {
        $indivCur = $plan->gueltig_von->copy();
        while ($indivCur->lte($plan->gueltig_bis)) {
            if ($indivCur->isWeekday()) { $indivWochentage[] = $indivCur->copy(); }
            $indivCur->addDay();
        }
    }
    $indivTagNamen = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];
@endphp
<div class="taegl-uebungen">
    <div class="taegl-uebungen-title">&#x270F; Tägliche Übungen</div>
    <table class="taegl-table">
        <thead>
            <tr>
                <th>Übung</th>
                @foreach($indivWochentage as $indivTag)
                    <th class="taegl-check-cell">
                        {{ $indivTagNamen[($indivTag->dayOfWeek + 6) % 7] ?? '' }}<br>
                        <span style="font-weight:normal;font-size:smaller;">{{ $indivTag->format('d.m.') }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($plan->taeglicheUebungen as $indivUebung)
                <tr>
                    <td>{{ $indivUebung->aufgabe }}</td>
                    @foreach($indivWochentage as $indivTag)
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
            <th class="td-aufgaben">Meine Aufgaben</th>
            @if($zeigeDauer)
                <th style="width:10%;">Dauer</th>
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
        @if($vorschau ?? false)
            <tr>
                <td class="td-fach">Deutsch</td>
                <td class="td-aufgaben">
                    <div class="aufgabe-zeile">S. 45, Nr. 1–3</div>
                    <div class="aufgabe-zeile">Lernwörter 3x schreiben</div>
                </td>
                @if($zeigeDauer) <td>&nbsp;</td> @endif
                @if($zeigeCheck) <td class="td-check">&nbsp;</td> @endif
                @if($zeigeKontrolliert) <td class="td-kontrolliert">&nbsp;</td> @endif
                @if($zeigeUnterschrift) <td class="td-unterschrift">&nbsp;</td> @endif
            </tr>
            <tr>
                <td class="td-fach">Mathe</td>
                <td class="td-aufgaben"><div class="aufgabe-zeile">Heft S. 22</div></td>
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
                    @if($zeigeCheck) <td class="td-check">&nbsp;</td> @endif
                    @if($zeigeKontrolliert) <td class="td-kontrolliert">&nbsp;</td> @endif
                    @if($zeigeUnterschrift) <td class="td-unterschrift">&nbsp;</td> @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colCount }}" style="text-align:center;color:#888;padding:12px;">
                        Keine Fächer vorhanden.
                    </td>
                </tr>
            @endforelse
        @endif
    </tbody>
</table>

<div class="footer">
    @if($plan->selbsteinschaetzung > 0 && ($config['footer']['zeige_selbsteinschaetzung'] ?? true))
        <div class="selbsteinschaetzung-label">Wie war meine Woche?</div>
        @if($plan->selbsteinschaetzung == 1)
            {{-- Smiley-Variante: 3 Spalten über volle Breite --}}
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-top: 6px;">
                <tr>
                    <td style="width: 33.33%; text-align: center; padding: 8px 4px; border: 1px solid #ccc; vertical-align: middle;">
                        <img src="{{ $smileyGut }}" width="40" height="40" alt=""><br>
                        <span style="font-size: {{ $tinySize }}; font-family: {{ $schriftartCss }};">super!</span>
                    </td>
                    <td style="width: 33.33%; text-align: center; padding: 8px 4px; border: 1px solid #ccc; vertical-align: middle;">
                        <img src="{{ $smileyOkay }}" width="40" height="40" alt=""><br>
                        <span style="font-size: {{ $tinySize }}; font-family: {{ $schriftartCss }};">okay</span>
                    </td>
                    <td style="width: 33.33%; text-align: center; padding: 8px 4px; border: 1px solid #ccc; vertical-align: middle;">
                        <img src="{{ $smileySchw }}" width="40" height="40" alt=""><br>
                        <span style="font-size: {{ $tinySize }}; font-family: {{ $schriftartCss }};">schwer</span>
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
    @endif
    @if($zeigeUnterschrift && !($vorschau ?? false))
        <div class="unterschrift-zeilen">
            <div class="unterschrift-zeile">
                <div class="unterschrift-linie">&nbsp;</div>
                <div class="unterschrift-text">Unterschrift Lehrkraft</div>
            </div>
            <div class="unterschrift-zeile">
                <div class="unterschrift-linie">&nbsp;</div>
                <div class="unterschrift-text">Unterschrift Eltern</div>
            </div>
        </div>
    @endif
    <div class="kommentar-bereich">
        <div class="kommentar-label">Kommentar / Bemerkungen:</div>
    </div>
</div>

</body>
</html>

