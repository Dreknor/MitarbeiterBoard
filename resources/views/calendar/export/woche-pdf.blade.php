<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kalender KW{{ $kw }}</title>
    <style>
        @page { margin: 15mm; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0 0 4px 0;
            color: #1e40af;
        }
        .header .subtitle {
            font-size: 10pt;
            color: #6b7280;
        }
        .week-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .week-grid th {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 6px 8px;
            text-align: left;
            font-size: 10pt;
            color: #1e40af;
            width: 14.28%;
            vertical-align: top;
        }
        .week-grid td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
            vertical-align: top;
            min-height: 80px;
        }
        .termin {
            margin-bottom: 4px;
            padding: 3px 5px;
            border-radius: 3px;
            border-left: 3px solid;
            background: #f9fafb;
            font-size: 8pt;
        }
        .termin .zeit {
            font-weight: bold;
            color: #374151;
        }
        .termin .titel {
            font-weight: 600;
        }
        .termin .ort {
            color: #6b7280;
            font-size: 7pt;
        }
        .no-termine {
            color: #9ca3af;
            font-style: italic;
            font-size: 8pt;
        }
        .footer {
            margin-top: 10px;
            text-align: right;
            font-size: 7pt;
            color: #9ca3af;
        }
        .kalender-legende {
            margin-top: 8px;
        }
        .legende-item {
            display: inline-block;
            font-size: 7pt;
            margin-right: 10px;
        }
        .legende-punkt {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 3px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Kalender &#8211; KW {{ $kw }}</h1>
        <div class="subtitle">{{ $woche }}</div>
    </div>

    <table class="week-grid">
        <thead>
            <tr>
                @foreach($tage as $tag)
                    <th>{{ $tag['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($tage as $tag)
                    <td>
                        @forelse($tag['termine'] as $termin)
                            <div class="termin" style="border-left-color: {{ $termin->kalender->farbe ?? '#3b82f6' }}">
                                <div class="zeit">
                                    @if($termin->ganztaegig)
                                        Ganzt&auml;gig
                                    @else
                                        {{ $termin->beginn->format('H:i') }}&ndash;{{ $termin->ende->format('H:i') }}
                                    @endif
                                </div>
                                <div class="titel">{{ $termin->titel }}</div>
                                @if($termin->ort)
                                    <div class="ort">{{ $termin->ort }}</div>
                                @endif
                            </div>
                        @empty
                            <span class="no-termine">Keine Termine</span>
                        @endforelse
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    {{-- Kalender-Legende --}}
    <div class="kalender-legende">
        @foreach($kalender as $cal)
            <span class="legende-item">
                <span class="legende-punkt" style="background-color: {{ $cal->farbe }}"></span>
                {{ $cal->name }}
            </span>
        @endforeach
    </div>

    <div class="footer">
        Erstellt am {{ now()->format('d.m.Y H:i') }} &ndash; MitarbeiterBoard ESZ Radebeul
    </div>
</body>
</html>

