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
            src: url('{{ public_path('fonts/NotoSansSymbols2-Regular.ttf') }}') format('truetype');
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
             text-align: left; font-weight: bold; font-size: {{ $smallSize }}; }
        td { border: 1px solid #888; padding: 5px 8px; vertical-align: top; }
        .td-fach     { font-weight: bold; width: {{ $config['spalten']['fach'] ?? '18%' }}; vertical-align: middle; }
        .td-aufgaben { width: 55%; }
        .td-notizen  { width: 27%; color: #aaa; font-size: {{ $smallSize }}; font-style: italic; }
        .aufgabe-zeile { padding: 2px 0; border-bottom: 1px dotted #ccc; font-size: {{ $baseSizePt }}pt; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .dauer { color: #666; font-size: {{ $smallSize }}; margin-left: 4px; }
        .footer { margin-top: 14px; }
        .selbsteinschaetzung-label { font-weight: bold; margin-bottom: 6px; }
        .smileys { display: table; margin-top: 4px; border-collapse: separate; }
        .smiley-item { display: table-cell; text-align: center; padding-right: 16px; vertical-align: top; }
        .smiley-face { width: 40px; height: 40px; margin: 0 auto 3px auto; }
        .smiley-label { font-size: {{ $tinySize }}; text-align: center; }
        .unterschrift-zeilen { display: table; width: 100%; margin-top: 14px; }
        .unterschrift-zeile { display: table-cell; padding-right: 20px; }
        .unterschrift-linie { border-bottom: 1px solid #000; height: 20px; }
        .unterschrift-text  { font-size: {{ $tinySize }}; color: #666; }
        .kommentar-bereich  { margin-top: 16px; border: 1px dashed #aaa; padding: 8px; min-height: 40px; }
        .kommentar-label    { font-size: {{ $smallSize }}; color: #777; margin-bottom: 4px; }
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
            <div style="font-size:{{ $baseSizePt }}pt; margin-top:6px;">Name: ......................................</div>
        @endif
        <div class="header-zeitraum">{{ $plan->zeitraum }}</div>
    @endif
</div>

<table>
    <thead>
        <tr>
            <th class="td-fach">Fach</th>
            <th class="td-aufgaben">Meine Aufgaben</th>
            <th class="td-notizen">Notizen / Erledigt?</th>
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
                <td class="td-notizen">Hier Notizen ...</td>
            </tr>
            <tr>
                <td class="td-fach">Mathe</td>
                <td class="td-aufgaben"><div class="aufgabe-zeile">Heft S. 22</div></td>
                <td class="td-notizen">&nbsp;</td>
            </tr>
        @else
            @forelse($plan->planFaecher as $planFach)
                <tr>
                    <td class="td-fach">{{ $planFach->display_name }}</td>
                    <td class="td-aufgaben">
                        @forelse($planFach->aufgaben as $aufgabe)
                            <div class="aufgabe-zeile">
                                {{ $aufgabe->aufgabe }}
                                @if($aufgabe->dauer)
                                    <span class="dauer">({{ $aufgabe->dauer }})</span>
                                @endif
                            </div>
                        @empty
                            &nbsp;
                        @endforelse
                    </td>
                    <td class="td-notizen">&nbsp;</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center;color:#888;padding:12px;">
                        Keine Fächer vorhanden.
                    </td>
                </tr>
            @endforelse
        @endif
    </tbody>
</table>

@if(!($vorschau ?? false))
    <div class="footer">
        @if($plan->selbsteinschaetzung == 1)
            <div class="selbsteinschaetzung-label">Wie war meine Woche?</div>
            <div class="smileys">
                <div class="smiley-item">
                    <svg class="smiley-face" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="20" cy="20" r="18" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/>
                        <circle cx="14" cy="15" r="2" fill="#333"/>
                        <circle cx="26" cy="15" r="2" fill="#333"/>
                        <path d="M12 25 Q20 33 28 25" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="smiley-label">super!</div>
                </div>
                <div class="smiley-item">
                    <svg class="smiley-face" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="20" cy="20" r="18" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/>
                        <circle cx="14" cy="15" r="2" fill="#333"/>
                        <circle cx="26" cy="15" r="2" fill="#333"/>
                        <line x1="12" y1="28" x2="28" y2="28" stroke="#333" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="smiley-label">okay</div>
                </div>
                <div class="smiley-item">
                    <svg class="smiley-face" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="20" cy="20" r="18" fill="#fff9c4" stroke="#f9a825" stroke-width="2"/>
                        <circle cx="14" cy="15" r="2" fill="#333"/>
                        <circle cx="26" cy="15" r="2" fill="#333"/>
                        <path d="M12 32 Q20 24 28 32" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="smiley-label">schwer</div>
                </div>
            </div>
        @endif
        @if($config['footer']['zeige_unterschrift'] ?? true)
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
@endif

</body>
</html>

