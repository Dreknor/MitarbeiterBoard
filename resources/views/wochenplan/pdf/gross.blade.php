@php
    $baseSizePt  = 16;
    $schriftartCss = $formatvorlage->schriftart ?? 'Arial, sans-serif';
    $margins     = $config['seitenraender'] ?? ['oben' => 20, 'rechts' => 20, 'unten' => 20, 'links' => 20];
    $titleSize   = '18pt';
    $smallSize   = '14pt';
    $tinySize    = '12pt';
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
            padding: {{ $margins['oben'] ?? 20 }}mm {{ $margins['rechts'] ?? 20 }}mm {{ $margins['unten'] ?? 20 }}mm {{ $margins['links'] ?? 20 }}mm;
            line-height: 1.6;
        }
        .header { border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 14px; }
        .header-title { font-size: {{ $titleSize }}; font-weight: bold; text-decoration: underline; }
        .header-sub   { font-size: {{ $baseSizePt }}pt; margin-top: 6px; }
        .name-feld    { margin-top: 6px; font-size: {{ $baseSizePt }}pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #ddd; border: 2px solid #444; padding: 6px 10px; font-size: {{ $baseSizePt }}pt; font-weight: bold; }
        td { border: 2px solid #666; padding: 8px 10px; vertical-align: top; }
        .td-fach     { font-weight: bold; width: {{ $config['spalten']['fach'] ?? '20%' }}; text-align: center; vertical-align: middle; font-size: {{ $baseSizePt }}pt; }
        .td-aufgaben { width: 80%; }
        .aufgabe-zeile { padding: 4px 0; border-bottom: 1px dotted #aaa; font-size: {{ $baseSizePt }}pt; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .footer { margin-top: 20px; border-top: 2px solid #aaa; padding-top: 12px; }
        .selbsteinschaetzung-label { font-weight: bold; font-size: {{ $baseSizePt }}pt; margin-bottom: 8px; }
        .smileys { display: table; margin-top: 6px; border-collapse: separate; }
        .smiley-item { display: table-cell; text-align: center; padding-right: 24px; vertical-align: top; }
        .smiley-face { width: 48px; height: 48px; margin: 0 auto 4px auto; }
        .smiley-label { font-size: {{ $smallSize }}; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-title">{{ $plan->name }}</div>
    @if(!($vorschau ?? false))
        <div class="header-sub">{{ $plan->zeitraum }}</div>
    @endif
    <div class="name-feld">
        @if(!($vorschau ?? false) && $plan->isSchuelerplan() && $plan->schueler)
            Name: <strong>{{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}</strong>
        @else
            Name: ......................................
        @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="td-fach">Fach</th>
            <th>Aufgaben</th>
        </tr>
    </thead>
    <tbody>
        @if($vorschau ?? false)
            <tr>
                <td class="td-fach">Deutsch</td>
                <td><div class="aufgabe-zeile">Beispiel-Aufgabe 1</div><div class="aufgabe-zeile">Beispiel-Aufgabe 2</div></td>
            </tr>
            <tr>
                <td class="td-fach">Mathe</td>
                <td><div class="aufgabe-zeile">S. 45, Nr. 1–5</div></td>
            </tr>
        @else
            @forelse($plan->planFaecher as $planFach)
                <tr>
                    <td class="td-fach">{{ $planFach->display_name }}</td>
                    <td>
                        @forelse($planFach->aufgaben as $aufgabe)
                            <div class="aufgabe-zeile">{{ $aufgabe->aufgabe }}</div>
                        @empty
                            &nbsp;
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" style="text-align:center;color:#888;padding:16px;">Keine Fächer vorhanden.</td></tr>
            @endforelse
        @endif
    </tbody>
</table>

@if(!($vorschau ?? false) && $plan->selbsteinschaetzung == 1)
    <div class="footer">
        <div class="selbsteinschaetzung-label">Selbsteinschätzung:</div>
        <div class="smileys">
            <div class="smiley-item">
                <svg class="smiley-face" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="24" cy="24" r="21" fill="#fff9c4" stroke="#f9a825" stroke-width="2.5"/>
                    <circle cx="17" cy="18" r="2.5" fill="#333"/>
                    <circle cx="31" cy="18" r="2.5" fill="#333"/>
                    <path d="M14 29 Q24 38 34 29" fill="none" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <div class="smiley-label">gut</div>
            </div>
            <div class="smiley-item">
                <svg class="smiley-face" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="24" cy="24" r="21" fill="#fff9c4" stroke="#f9a825" stroke-width="2.5"/>
                    <circle cx="17" cy="18" r="2.5" fill="#333"/>
                    <circle cx="31" cy="18" r="2.5" fill="#333"/>
                    <line x1="14" y1="32" x2="34" y2="32" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <div class="smiley-label">okay</div>
            </div>
            <div class="smiley-item">
                <svg class="smiley-face" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="24" cy="24" r="21" fill="#fff9c4" stroke="#f9a825" stroke-width="2.5"/>
                    <circle cx="17" cy="18" r="2.5" fill="#333"/>
                    <circle cx="31" cy="18" r="2.5" fill="#333"/>
                    <path d="M14 36 Q24 27 34 36" fill="none" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <div class="smiley-label">schwierig</div>
            </div>
        </div>
    </div>
@endif

</body>
</html>

