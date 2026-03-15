<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kalender KW{{ $kw }}</title>
    <style>
        @page { margin: 12mm 15mm; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 6px;
        }
        .header h1 {
            font-size: 15pt;
            margin: 0 0 3px 0;
            color: #1e40af;
        }
        .header .subtitle {
            font-size: 10pt;
            color: #6b7280;
        }
        .week-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .week-grid th {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 5px 6px;
            text-align: left;
            font-size: 9pt;
            color: #1e40af;
            font-weight: bold;
        }
        .week-grid td {
            border: 1px solid #e5e7eb;
            padding: 4px 5px;
            vertical-align: top;
            min-height: 70px;
        }
        .termin {
            margin-bottom: 4px;
            padding: 3px 5px;
            border-radius: 2px;
            border-left: 3px solid #3b82f6;
            background: #f9fafb;
            font-size: 8pt;
            page-break-inside: avoid;
        }
        .termin .zeit {
            font-weight: bold;
            color: #374151;
            font-size: 7.5pt;
        }
        .termin .titel {
            font-weight: 600;
            word-break: break-word;
        }
        .termin .ort {
            color: #6b7280;
            font-size: 7pt;
        }
        .no-termine {
            color: #9ca3af;
            font-style: italic;
            font-size: 7.5pt;
        }
        .footer {
            margin-top: 8px;
            text-align: right;
            font-size: 7pt;
            color: #9ca3af;
        }
        .legende {
            margin-top: 6px;
            font-size: 7pt;
        }
        .legende-item {
            display: inline-block;
            margin-right: 12px;
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
        <h1>Kalender &ndash; KW {{ $kw }}</h1>
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
                            <div class="termin"
                                 style="border-left-color: {{ $termin->kalender->farbe ?? '#3b82f6' }}">
                                <div class="zeit">
                                    @if($termin->ganztaegig)
                                        Ganzt&auml;gig
                                    @else
                                        {{ $termin->beginn->format('H:i') }}&ndash;{{ $termin->ende->format('H:i') }}
                                    @endif
                                </div>
                                <div class="titel">{{ $termin->titel }}</div>
                                @if($termin->ort)
                                    <div class="ort">&#x1F4CD; {{ $termin->ort }}</div>
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
    @if($kalender->isNotEmpty())
        <div class="legende">
            @foreach($kalender as $cal)
                <span class="legende-item">
                    <span class="legende-punkt"
                          style="background-color: {{ $cal->farbe }}; border: 1px solid #ccc;"></span>
                    {{ $cal->name }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="footer">
        Erstellt am {{ now()->format('d.m.Y H:i') }} &ndash; MitarbeiterBoard ESZ Radebeul
    </div>
</body>
</html>

