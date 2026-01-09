<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnosebogen (Leerformular) - {{ $area->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            margin: 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }

        .header h1 {
            margin: 0;
            font-size: 14pt;
        }

        .header p {
            margin: 3px 0;
            font-size: 9pt;
        }

        .student-info {
            background-color: #f5f5f5;
            padding: 8px;
            margin-bottom: 12px;
            border: 1px solid #ddd;
        }

        .student-info table {
            width: 100%;
        }

        .student-info td {
            padding: 3px;
        }

        .info-box {
            background-color: #f5f5f5;
            padding: 8px;
            margin-bottom: 12px;
            border: 1px solid #ddd;
        }

        .stage-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .stage-header {
            background-color: #4a5568;
            color: white;
            padding: 6px;
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 9pt;
        }

        .stage-goal {
            background-color: #e2e8f0;
            padding: 4px;
            margin-bottom: 8px;
            font-style: italic;
            font-size: 8pt;
        }

        .goals-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 7pt;
        }

        .goals-table th,
        .goals-table td {
            border: 1px solid #333;
            padding: 3px;
            text-align: center;
        }

        .goals-table th {
            background-color: #cbd5e0;
            font-weight: bold;
            font-size: 7pt;
        }

        .goals-table td.code {
            width: 40px;
            font-weight: bold;
        }

        .goals-table td.description {
            text-align: left;
            max-width: 200px;
        }

        .goals-table td.empty-column {
            width: 50px;
            background-color: #ffffff;
        }

        .notes-box {
            border: 1px solid #cbd5e0;
            min-height: 40px;
            margin-top: 5px;
            padding: 5px;
            background-color: #f7fafc;
        }

        .notes-label {
            font-weight: bold;
            font-size: 8pt;
            margin-bottom: 3px;
        }

        .footer {
            margin-top: 20px;
            border-top: 1px solid #333;
            padding-top: 8px;
            font-size: 7pt;
            color: #666;
        }

        .legend-box {
            background-color: #f7fafc;
            padding: 8px;
            border: 1px solid #cbd5e0;
            margin-top: 10px;
        }

        .rating-white {
            background-color: #ffffff;
            border: 1px solid #333;
            display: inline-block;
            width: 15px;
            height: 15px;
        }

        .rating-gray {
            background-color: #a0aec0;
            border: 1px solid #333;
            display: inline-block;
            width: 15px;
            height: 15px;
        }

        .rating-dark {
            background-color: #2d3748;
            border: 1px solid #333;
            display: inline-block;
            width: 15px;
            height: 15px;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>Diagnosebogen - {{ $area->name }}</h1>
        <p>Leerformular zum manuellen Ausfüllen</p>
    </div>

    {{-- Schüler-Informationen (leer zum Ausfüllen) --}}
    <div class="student-info">
        <table>
            <tr>
                <td style="width: 150px;"><strong>Schüler/in:</strong></td>
                <td style="border-bottom: 1px solid #333;">____________________________________</td>
                <td style="width: 100px;"><strong>Klasse:</strong></td>
                <td style="width: 150px; border-bottom: 1px solid #333;">________________</td>
            </tr>
        </table>
    </div>

    {{-- Bereichsziel --}}
    @if($area->description)
        <div class="info-box">
            <strong>Bereichsziel:</strong> {{ $area->description }}
        </div>
    @endif

    {{-- Legende --}}
    <div class="legend-box">
        <strong>Anleitung zur Bewertung:</strong><br>
        <span class="rating-white"></span> = Kind beherrscht es |
        <span class="rating-gray"></span> = Aktuelles Ziel |
        <span class="rating-dark"></span> = Kind kann es noch nicht<br>
        <small>Tragen Sie in die Spalten das Datum der Erfassung ein und markieren Sie die entsprechenden Felder.</small>
    </div>

    {{-- Stufen und Ziele --}}
    @foreach($area->stages as $stage)
        <div class="stage-section">
            <div class="stage-header">
                {{ $stage->name }}: {{ $stage->code }}
            </div>

            @if($stage->goal_description)
                <div class="stage-goal">
                    <strong>Stufenziel:</strong> {{ $stage->goal_description }}
                </div>
            @endif

            {{-- Ziele-Tabelle mit Leer-Spalten --}}
            <table class="goals-table">
                <thead>
                    <tr>
                        <th class="code">Code</th>
                        <th class="description">Beschreibung</th>
                        @for($i = 0; $i < $emptyColumns; $i++)
                            <th class="empty-column">Datum:<br>_______</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach($stage->goals as $goal)
                        <tr>
                            <td class="code">{{ $goal->code }}</td>
                            <td class="description">{{ $goal->description }}</td>
                            @for($i = 0; $i < $emptyColumns; $i++)
                                <td class="empty-column"></td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Notizen-Bereich --}}
            <div class="notes-label">Notizen zu {{ $stage->name }}:</div>
            <div class="notes-box"></div>
        </div>
    @endforeach

    {{-- Allgemeine Notizen --}}
    <div class="stage-section">
        <div class="notes-label">Allgemeine Beobachtungen / Notizen:</div>
        <div class="notes-box" style="min-height: 80px;"></div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr</p>
        <p>Dieses Formular kann für mehrere Erfassungen verwendet werden.
           Tragen Sie jeweils das Datum in die Spaltenüberschrift ein und markieren Sie die Bewertungen.</p>
    </div>
</body>
</html>

