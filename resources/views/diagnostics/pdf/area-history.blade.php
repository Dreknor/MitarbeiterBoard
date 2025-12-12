<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnosebogen Verlauf - {{ $schueler->name }}</title>
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

        .progress-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 7pt;
        }

        .progress-table th,
        .progress-table td {
            border: 1px solid #333;
            padding: 3px;
            text-align: center;
        }

        .progress-table th {
            background-color: #cbd5e0;
            font-weight: bold;
            font-size: 7pt;
        }

        .progress-table td.code {
            width: 40px;
            font-weight: bold;
        }

        .progress-table td.description {
            text-align: left;
            max-width: 200px;
        }

        .progress-table td.date-column {
            width: 50px;
        }

        .progress-table td.empty-column {
            width: 40px;
            background-color: #f7fafc;
        }

        .rating-cell {
            width: 50px;
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

        .current-marker {
            color: #fbbf24;
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            border-top: 1px solid #333;
            padding-top: 8px;
            font-size: 7pt;
            color: #666;
        }

        .notes-section {
            background-color: #f7fafc;
            padding: 5px;
            border: 1px solid #cbd5e0;
            margin-top: 5px;
            font-size: 7pt;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>Diagnosebogen Verlauf - {{ $area->name }}</h1>
        <p><strong>Schüler/in:</strong> {{ $schueler->name }}</p>
        <p><strong>Anzahl Erfassungen:</strong> {{ $sessions->count() }}</p>
    </div>

    {{-- Bereichsziel --}}
    @if($area->description)
        <div class="info-box">
            <strong>Bereichsziel:</strong> {{ $area->description }}
        </div>
    @endif

    {{-- Stufen und Fortschrittstabelle --}}
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

            {{-- Fortschritts-Tabelle --}}
            <table class="progress-table">
                <thead>
                    <tr>
                        <th class="code">Code</th>
                        <th class="description">Beschreibung</th>
                        @foreach($sessions as $session)
                            <th class="date-column">
                                {{ $session->session_date->format('d.m.y') }}
                            </th>
                        @endforeach
                        @for($i = 0; $i < $emptyColumns; $i++)
                            <th class="empty-column">___</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach($stage->goals as $goal)
                        <tr>
                            <td class="code">{{ $goal->code }}</td>
                            <td class="description">{{ $goal->description }}</td>

                            @foreach($sessions as $session)
                                @php
                                    $assessment = $session->assessments
                                        ->where('diagnostic_goal_id', $goal->id)
                                        ->first();
                                @endphp
                                <td class="rating-cell">
                                    @if($assessment && $assessment->rating)
                                        @if($assessment->rating === 'white')
                                            <span class="rating-white"></span>
                                        @elseif($assessment->rating === 'gray')
                                            <span class="rating-gray"></span>
                                        @elseif($assessment->rating === 'dark_gray')
                                            <span class="rating-dark"></span>
                                        @endif
                                        @if($assessment->is_current_goal)
                                            <span class="current-marker">★</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach

                            @for($i = 0; $i < $emptyColumns; $i++)
                                <td class="empty-column"></td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Stufen-Notizen aus Sessions --}}
            @foreach($sessions as $session)
                @php
                    $stageNote = $session->stageNotes->where('diagnostic_stage_id', $stage->id)->first();
                @endphp
                @if($stageNote && $stageNote->notes)
                    <div class="notes-section">
                        <strong>{{ $session->session_date->format('d.m.Y') }}:</strong> {{ $stageNote->notes }}
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach

    {{-- Footer --}}
    <div class="footer">
        <p>Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr</p>
        <p><strong>Legende:</strong>
            <span class="rating-white"></span> = Beherrscht |
            <span class="rating-gray"></span> = Aktuell |
            <span class="rating-dark"></span> = Noch nicht |
            <span class="current-marker">★</span> = Aktuelles Ziel
        </p>
        <p><strong>Zeitlicher Verlauf:</strong> Die Erfassungen sind aufsteigend nach Datum sortiert (älteste links, neueste rechts)</p>
    </div>
</body>
</html>

