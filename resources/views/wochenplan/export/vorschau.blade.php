<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorschau: {{ $plan->name }}</title>
    <style>
        @php
            $customPt = $config['typografie']['schriftgroesse_pt'] ?? null;
            $basePx = $customPt ? ($customPt * 1.33) : match($formatvorlage->schriftgroesse ?? 'normal') {
                'gross'      => 18,
                'sehr_gross' => 24,
                default      => 16,
            };
            $schriftgroesse = $basePx . 'px';
            $schriftGroessePlusDrei = ($basePx + 3) . 'px';
            $schriftGroessePlusEins = ($basePx + 1) . 'px';
            $schriftGroesseMinusEins = max(10, $basePx - 1) . 'px';
            $schriftGroesseMinusZwei = max(9, $basePx - 2) . 'px';
            $schriftart = $formatvorlage->schriftart ?? 'Arial, sans-serif';
            $zeilenabstand = $config['typografie']['zeilenabstand'] ?? 1.4;
            $abstandFaecher = ($config['abstände']['zwischen_fächern'] ?? 5) . 'mm';
            $abstandAufgaben = ($config['abstände']['zwischen_aufgaben'] ?? 2) . 'mm';
            $zeigeCheck = $config['spalten']['zeige_check_spalte'] ?? true;
            $zeigeKontrolliert = $config['spalten']['zeige_kontrolliert_spalte'] ?? false;
            $zeigeUnterschrift = $config['spalten']['zeige_unterschrift_spalte'] ?? true;
            $zeigeDauer = $config['spalten']['zeige_dauer'] ?? false;
            $colFach = $config['spalten']['fach'] ?? '15%';
            $colAufgaben = $config['spalten']['aufgaben'] ?? '55%';
            $colCheck = $config['spalten']['check'] ?? '5%';
            $colUnterschrift = $config['spalten']['unterschrift'] ?? '25%';
        @endphp

        * { box-sizing: border-box; }
        body {
            font-family: {{ $schriftart }};
            font-size: {{ $schriftgroesse }};
            line-height: {{ $zeilenabstand }};
            color: #000;
            background: #f5f5f5;
            padding: 20px;
        }
        .page {
            background: white;
            max-width: 210mm;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .no-print { margin-bottom: 16px; }
        .no-print button {
            padding: 8px 20px; background: #2563eb; color: white; border: none;
            border-radius: 6px; cursor: pointer; font-size: 14px; margin-right: 8px;
        }
        .no-print a {
            padding: 8px 16px; background: #f3f4f6; color: #374151;
            border: 1px solid #d1d5db; border-radius: 6px; text-decoration: none; font-size: 14px;
        }
        .header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-start; }
        .plan-title { font-size: {{ $schriftGroessePlusDrei }}; font-weight: bold; text-decoration: underline; }
        .plan-klasse { font-weight: bold; font-size: {{ $schriftGroessePlusEins }}; }
        .name-feld { margin-top: 4px; font-size: {{ $schriftgroesse }}; }
        .header-freitext { margin-top: 2px; font-size: {{ $schriftGroesseMinusZwei }}; color: #555; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f0f0f0; border: 1px solid #666; padding: 5px 7px; text-align: left; font-weight: bold; font-size: {{ $schriftGroesseMinusEins }}; }
        td { border: 1px solid #888; padding: 5px 7px; vertical-align: top; }
        .td-fach { font-weight: bold; vertical-align: middle; width: {{ $colFach }}; text-align: center; }
        .td-aufgaben { width: {{ $colAufgaben }}; }
        .td-check { width: {{ $colCheck }}; text-align: center; }
        .td-unterschrift { width: {{ $colUnterschrift }}; }
        .td-kontrolliert { width: 12%; }

        .wp-fach-symbol { display: inline-block; vertical-align: middle; margin-right: 0.2em; }
        .fach-row-gap td { padding-bottom: {{ $abstandFaecher }}; }
        .aufgabe-zeile { padding-bottom: {{ $abstandAufgaben }}; border-bottom: 1px dotted #ccc; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .dauer { color: #555; font-size: {{ $schriftGroesseMinusEins }}; margin-left: 4px; }

        .footer { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 10px; }
        .selbsteinschaetzung { margin-bottom: 10px; }
        .smileys { display: flex; gap: 16px; }
        .smiley-item { text-align: center; }
        .smiley-item .emoji { font-size: 24px; }
        .smiley-item .label { font-size: {{ $schriftGroesseMinusEins }}; color: #666; }
        .skala-container { display: flex; gap: 4px; }
        .skala-item { width: 22px; height: 22px; border: 1px solid #666; text-align: center; line-height: 20px; font-size: {{ $schriftGroesseMinusZwei }}; }
        .footer-freitext { margin-top: 8px; font-size: {{ $schriftGroesseMinusZwei }}; color: #555; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .page { box-shadow: none; padding: 15mm; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()">🖨️ Drucken</button>
        <a href="{{ route('wp.export.pdf', $plan) }}" target="_blank">📄 Als PDF</a>
        <a href="{{ route('wp.edit', $plan) }}" style="margin-left:8px">← Zurück zum Plan</a>
    </div>

    <div class="page">

        {{-- Header --}}
        <div class="header">
            <div class="header-row">
                <div>
                    <div class="plan-title">{{ $plan->name }} vom {{ $plan->zeitraum }}</div>
                    <div class="name-feld">
                        @if($plan->isSchuelerplan() && $plan->schueler)
                            Name: {{ $plan->schueler->vorname }} {{ $plan->schueler->nachname }}
                        @else
                            Name: ..............................
                        @endif
                    </div>
                    @if(!empty($config['header']['freitext']))
                        <div class="header-freitext">{{ $config['header']['freitext'] }}</div>
                    @endif
                </div>
                @if($plan->klasse)
                    <div class="plan-klasse">{{ $plan->klasse->name }}</div>
                @endif
            </div>
        </div>

        {{-- Fächer-Tabelle --}}
        <table>
            <thead>
                <tr>
                    <th class="td-fach">Fach</th>
                    <th class="td-aufgaben">Aufgaben</th>
                    @if($zeigeDauer) <th style="width:10%">Dauer</th> @endif
                    @if($zeigeCheck) <th class="td-check">✓</th> @endif
                    @if($zeigeUnterschrift) <th class="td-unterschrift">Unterschrift</th> @endif
                    @if($zeigeKontrolliert) <th class="td-kontrolliert">Kontrolliert</th> @endif
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
                                    @if($aufgabe->dauer)
                                        <span class="dauer">({{ $aufgabe->dauer }})</span>
                                    @endif
                                </div>
                            @empty
                                &nbsp;
                            @endforelse
                        </td>
                        @if($zeigeDauer) <td>&nbsp;</td> @endif
                        @if($zeigeCheck) <td class="td-check">&nbsp;</td> @endif
                        @if($zeigeUnterschrift) <td class="td-unterschrift">&nbsp;</td> @endif
                        @if($zeigeKontrolliert) <td class="td-kontrolliert">&nbsp;</td> @endif
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:#888;padding:14px;">Keine Fächer vorhanden.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Footer --}}
        <div class="footer">
            @if($plan->selbsteinschaetzung > 0 && ($config['footer']['zeige_selbsteinschaetzung'] ?? true))
                <div class="selbsteinschaetzung">
                    <strong>Selbsteinschätzung:</strong>
                    @if($plan->selbsteinschaetzung == 1)
                        <div class="smileys" style="margin-top:6px">
                            <div class="smiley-item"><div class="emoji">😊</div><div class="label">gut</div></div>
                            <div class="smiley-item"><div class="emoji">😐</div><div class="label">okay</div></div>
                            <div class="smiley-item"><div class="emoji">😕</div><div class="label">schwierig</div></div>
                        </div>
                    @elseif($plan->selbsteinschaetzung == 2)
                        <div class="skala-container" style="margin-top:6px">
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

    </div>

</body>
</html>

