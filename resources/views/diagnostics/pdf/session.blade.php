<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnosebogen - {{ $schueler->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 18pt;
        }

        .header p {
            margin: 5px 0;
            font-size: 11pt;
        }

        .info-box {
            background-color: #f5f5f5;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }

        .info-box p {
            margin: 3px 0;
        }

        .stage-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .stage-header {
            background-color: #4a5568;
            color: white;
            padding: 8px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stage-goal {
            background-color: #e2e8f0;
            padding: 6px;
            margin-bottom: 10px;
            font-style: italic;
        }

        .goals-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .goals-table th,
        .goals-table td {
            border: 1px solid #333;
            padding: 5px;
            text-align: left;
        }

        .goals-table th {
            background-color: #cbd5e0;
            font-weight: bold;
        }

        .goals-table td.code {
            width: 60px;
            font-weight: bold;
            text-align: center;
        }

        .goals-table td.rating {
            width: 80px;
            text-align: center;
        }

        .rating-white {
            background-color: #ffffff;
            border: 1px solid #333;
            display: inline-block;
            width: 20px;
            height: 20px;
            vertical-align: middle;
        }

        .rating-gray {
            background-color: #a0aec0;
            border: 1px solid #333;
            display: inline-block;
            width: 20px;
            height: 20px;
            vertical-align: middle;
        }

        .rating-dark {
            background-color: #2d3748;
            border: 1px solid #333;
            display: inline-block;
            width: 20px;
            height: 20px;
            vertical-align: middle;
        }

        .notes-section {
            background-color: #f7fafc;
            padding: 8px;
            border: 1px solid #cbd5e0;
            min-height: 50px;
            margin-top: 5px;
        }

        .notes-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .footer {
            margin-top: 30px;
            border-top: 1px solid #333;
            padding-top: 10px;
            font-size: 9pt;
            color: #666;
        }

        .current-goal-badge {
            background-color: #fbbf24;
            color: #000;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <h1>Diagnosebogen - {{ $area->name }}</h1>
        <p><strong>Schüler/in:</strong> {{ $schueler->name }}</p>
        @if($singleSession)
            <p><strong>Datum der Erfassung:</strong> {{ $session->session_date->format('d.m.Y') }}</p>
            <p><strong>Durchgeführt von:</strong> {{ $session->user->name }}</p>
        @endif
    </div>

    {{-- Bereichsziel --}}
    @if($area->description)
        <div class="info-box">
            <strong>Bereichsziel:</strong> {{ $area->description }}
        </div>
    @endif

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

            {{-- Ziele-Tabelle --}}
            <table class="goals-table">
                <thead>
                    <tr>
                        <th class="code">Code</th>
                        <th>Beschreibung</th>
                        @if($singleSession)
                            <th class="rating">Bewertung</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($stage->goals as $goal)
                        @php
                            $assessment = $singleSession
                                ? $session->assessments->where('diagnostic_goal_id', $goal->id)->first()
                                : null;
                        @endphp
                        <tr>
                            <td class="code">{{ $goal->code }}</td>
                            <td>
                                {{ $goal->description }}
                                @if($assessment && $assessment->is_current_goal)
                                    <span class="current-goal-badge">AKTUELLES ZIEL</span>
                                @endif
                            </td>
                            @if($singleSession)
                                <td class="rating">
                                    @if($assessment && $assessment->rating)
                                        @if($assessment->rating === 'white')
                                            <span class="rating-white"></span> Beherrscht
                                        @elseif($assessment->rating === 'gray')
                                            <span class="rating-gray"></span> Aktuell
                                        @elseif($assessment->rating === 'dark_gray')
                                            <span class="rating-dark"></span> Noch nicht
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Stufen-Notizen --}}
            @if($singleSession)
                @php
                    $stageNote = $session->stageNotes->where('diagnostic_stage_id', $stage->id)->first();
                @endphp
                @if($stageNote && $stageNote->notes)
                    <div class="notes-label">Notizen zu {{ $stage->name }}:</div>
                    <div class="notes-section">
                        {{ $stageNote->notes }}
                    </div>
                @endif
            @endif
        </div>
    @endforeach

    {{-- Allgemeine Notizen --}}
    @if($singleSession && $session->notes)
        <div class="stage-section">
            <div class="notes-label">Allgemeine Notizen zur Session:</div>
            <div class="notes-section">
                {{ $session->notes }}
            </div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <p>Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr</p>
        <p><strong>Legende:</strong>
            <span class="rating-white"></span> = Kind beherrscht es |
            <span class="rating-gray"></span> = Aktuelles Ziel |
            <span class="rating-dark"></span> = Kind kann es noch nicht
        </p>
    </div>
</body>
</html>

