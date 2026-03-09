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
        th { background-color: #f0f0f0; border: 1px solid #666; padding: 4px 6px; text-align: left; font-weight: bold; font-size: {{ $smallSize }}; }
        td { border: 1px solid #888; padding: 4px 6px; vertical-align: top; }
        .td-fach { font-weight: bold; vertical-align: middle; width: {{ $colFach }}; text-align: center; }
        .td-aufgaben { width: {{ $colAufgaben }}; }
        .td-check { width: {{ $colCheck }}; text-align: center; }
        .td-unterschrift { width: {{ $colUnterschrift }}; }
        .td-kontrolliert { width: 12%; }

        /* Fach-Symbol */
        .wp-fach-symbol { margin-right: 3px; }
        .wp-fach-symbol--emoji { font-size: {{ $schriftgroesse }}; }

        /* Abstände */
        .fach-row-gap { margin-bottom: {{ $abstandFaecher }}; }
        .aufgabe-zeile { padding-bottom: {{ $abstandAufgaben }}; border-bottom: 1px dotted #ccc; min-height: {{ $minZeilenhoeheStr }}; }
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
        .footer-freitext { margin-top: 8px; font-size: {{ $smallSize }}; color: #555; }
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

<table>
    <thead>
        <tr>
            <th class="td-fach">Fach</th>
            <th class="td-aufgaben">Aufgaben</th>
            @if($zeigeDauer)
                <th style="width:10%">Dauer</th>
            @endif
            @if($zeigeCheck)
                <th class="td-check">&#10003;</th>
            @endif
            @if($zeigeUnterschrift)
                <th class="td-unterschrift">Unterschrift</th>
            @endif
            @if($zeigeKontrolliert)
                <th class="td-kontrolliert">Kontrolliert</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse($plan->planFaecher as $planFach)
            <tr class="fach-row-gap">
                <td class="td-fach">
                    @if($planFach->fach && $planFach->fach->symbol_html)
                        {!! $planFach->fach->symbol_html !!}<br>
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
                @if($zeigeUnterschrift)
                    <td class="td-unterschrift">&nbsp;</td>
                @endif
                @if($zeigeKontrolliert)
                    <td class="td-kontrolliert">&nbsp;</td>
                @endif
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

    @if(!empty($config['footer']['freitext']))
        <div class="footer-freitext">{{ $config['footer']['freitext'] }}</div>
    @endif

</div>

</body>
</html>

