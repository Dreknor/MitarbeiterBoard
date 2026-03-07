<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorschau: {{ $plan->name }}</title>
    <style>
        @php
            $schriftgroesse = match($formatvorlage->schriftgroesse ?? 'normal') {
                'gross'      => '14px',
                'sehr_gross' => '18px',
                default      => '12px',
            };
            $schriftart = $formatvorlage->schriftart ?? 'Arial, sans-serif';
        @endphp

        * { box-sizing: border-box; }
        body {
            font-family: {{ $schriftart }};
            font-size: {{ $schriftgroesse }};
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
            padding: 8px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 8px;
        }
        .no-print a {
            padding: 8px 16px;
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
        }
        /* Header */
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .plan-title {
            font-size: calc({{ $schriftgroesse }} + 3px);
            font-weight: bold;
            text-decoration: underline;
        }
        .plan-klasse { font-weight: bold; font-size: calc({{ $schriftgroesse }} + 1px); }
        .name-feld { margin-top: 4px; font-size: {{ $schriftgroesse }}; }
        /* Tabelle */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f0f0f0; border: 1px solid #666; padding: 5px 7px; text-align: left; font-weight: bold; font-size: calc({{ $schriftgroesse }} - 1px); }
        td { border: 1px solid #888; padding: 5px 7px; vertical-align: top; }
        .td-fach { font-weight: bold; vertical-align: middle; width: {{ $config['spalten']['fach'] ?? '15%' }}; text-align: center; }
        .td-aufgaben { width: {{ $config['spalten']['aufgaben'] ?? '55%' }}; }
        .td-check { width: {{ $config['spalten']['check'] ?? '5%' }}; text-align: center; }
        .td-unterschrift { width: {{ $config['spalten']['unterschrift'] ?? '25%' }}; }
        .aufgabe-zeile { padding: 2px 0; border-bottom: 1px dotted #ccc; }
        .aufgabe-zeile:last-child { border-bottom: none; }
        .dauer { color: #555; font-size: calc({{ $schriftgroesse }} - 1px); margin-left: 4px; }
        /* Footer */
        .footer { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 10px; }
        .selbsteinschaetzung { margin-bottom: 10px; }
        .smileys { display: flex; gap: 16px; }
        .smiley-item { text-align: center; }
        .smiley-item .emoji { font-size: 24px; }
        .smiley-item .label { font-size: calc({{ $schriftgroesse }} - 1px); color: #666; }
        .skala-container { display: flex; gap: 4px; }
        .skala-item { width: 22px; height: 22px; border: 1px solid #666; text-align: center; line-height: 20px; font-size: calc({{ $schriftgroesse }} - 2px); }
        .unterschrift-zeilen { display: flex; gap: 24px; margin-top: 10px; }
        .unterschrift-zeile { flex: 1; }
        .unterschrift-linie { border-bottom: 1px solid #000; height: 24px; margin-bottom: 3px; }
        .unterschrift-text { font-size: calc({{ $schriftgroesse }} - 2px); color: #666; }
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
                    <th class="td-check">✓</th>
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
                        <td class="td-check">&nbsp;</td>
                        <td class="td-unterschrift">&nbsp;</td>
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

            @if($config['footer']['zeige_unterschrift'] ?? true)
                <div class="unterschrift-zeilen">
                    <div class="unterschrift-zeile">
                        <div class="unterschrift-linie"></div>
                        <div class="unterschrift-text">Unterschrift Lehrkraft</div>
                    </div>
                    <div class="unterschrift-zeile">
                        <div class="unterschrift-linie"></div>
                        <div class="unterschrift-text">Unterschrift Eltern</div>
                    </div>
                </div>
            @endif
        </div>

    </div>

</body>
</html>

