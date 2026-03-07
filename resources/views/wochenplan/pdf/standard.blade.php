@php
    $baseSizePt = match($formatvorlage->schriftgroesse ?? 'normal') {
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

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f0f0f0; border: 1px solid #666; padding: 4px 6px; text-align: left; font-weight: bold; font-size: {{ $smallSize }}; }
        td { border: 1px solid #888; padding: 4px 6px; vertical-align: top; }
        .td-fach { font-weight: bold; vertical-align: middle; width: {{ $config['spalten']['fach'] ?? '15%' }}; text-align: center; }
        .td-aufgaben { width: {{ $config['spalten']['aufgaben'] ?? '55%' }}; }
        .td-check { width: {{ $config['spalten']['check'] ?? '5%' }}; text-align: center; }
        .td-unterschrift { width: {{ $config['spalten']['unterschrift'] ?? '25%' }}; }

        .aufgabe-zeile { padding: 2px 0; border-bottom: 1px dotted #ccc; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .dauer { color: #555; font-size: {{ $smallSize }}; margin-left: 4px; }

        .footer { margin-top: 12px; border-top: 1px solid #ccc; padding-top: 8px; }
        .selbsteinschaetzung { margin-bottom: 8px; }
        .selbsteinschaetzung-label { font-weight: bold; margin-bottom: 4px; }
        .smileys { display: table; margin-top: 4px; }
        .smiley-item { display: table-cell; text-align: center; padding-right: 12px; font-size: 18pt; }
        .smiley-label { font-size: {{ $smallSize }}; }
        .skala-container { display: table; margin-top: 4px; }
        .skala-item { display: table-cell; width: 20px; height: 20px; border: 1px solid #666; text-align: center; font-size: {{ $tinySize }}; }
        .unterschrift-zeilen { display: table; width: 100%; margin-top: 10px; }
        .unterschrift-zeile { display: table-cell; padding-right: 20px; }
        .unterschrift-linie { border-bottom: 1px solid #000; margin-bottom: 2px; height: 20px; }
        .unterschrift-text { font-size: {{ $tinySize }}; color: #555; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-row">
        <div class="header-left">
            <div class="header-title">{{ $plan->name }} vom {{ $plan->zeitraum }}</div>
            <div class="name-feld">
                @if($plan->isSchuelerplan() && $plan->schueler)
                    Name: {{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}
                @elseif($config['header']['zeige_name_feld'] ?? true)
                    Name: ..............................
                @endif
            </div>
        </div>
        @if($plan->klasse)
            <div class="header-right">
                <div class="header-klasse">{{ $plan->klasse->name }}</div>
            </div>
        @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="td-fach">Fach</th>
            <th class="td-aufgaben">Aufgaben</th>
            @if($config['spalten']['zeige_dauer'] ?? false)
                <th style="width:10%">Dauer</th>
            @endif
            <th class="td-check">&#10003;</th>
            <th class="td-unterschrift">Unterschrift</th>
        </tr>
    </thead>
    <tbody>
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
                @if($config['spalten']['zeige_dauer'] ?? false)
                    <td>&nbsp;</td>
                @endif
                <td class="td-check">&nbsp;</td>
                <td class="td-unterschrift">&nbsp;</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align:center;color:#888;padding:12px;">Keine Fächer vorhanden.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">

    @if($plan->selbsteinschaetzung > 0 && ($config['footer']['zeige_selbsteinschaetzung'] ?? true))
        <div class="selbsteinschaetzung">
            <div class="selbsteinschaetzung-label">Selbsteinschätzung:</div>
            @if($plan->selbsteinschaetzung == 1)
                <div class="smileys">
                    <div class="smiley-item">&#9786; <div class="smiley-label">gut</div></div>
                    <div class="smiley-item">&#128528; <div class="smiley-label">okay</div></div>
                    <div class="smiley-item">&#128533; <div class="smiley-label">schwierig</div></div>
                </div>
            @elseif($plan->selbsteinschaetzung == 2)
                <div class="skala-container">
                    @for($i = 1; $i <= 10; $i++)
                        <div class="skala-item">{{ $i }}</div>
                    @endfor
                </div>
            @endif
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

</div>

</body>
</html>


