<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protokoll {{ $group->name }} - {{ $date->format('d.m.Y') }}</title>
    <style>
        @page {
            margin: 20mm 15mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 18pt;
            margin-bottom: 10px;
            margin-top: 0;
            color: #333;
            page-break-after: avoid;
        }

        h2 {
            font-size: 14pt;
            margin-top: 15px;
            margin-bottom: 10px;
            color: #333;
            page-break-after: avoid;
            border-bottom: 2px solid #B0CFFE;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table.info-table {
            margin-bottom: 20px;
            page-break-inside: avoid;
            page-break-after: avoid;
        }

        table.info-table td {
            padding: 5px;
            border: 1px solid #ddd;
        }

        table.info-table td:first-child {
            font-weight: bold;
            width: 30%;
            background-color: #f5f5f5;
        }

        .header {
            margin-bottom: 20px;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        .logo {
            float: right;
            max-height: 50px;
        }

        .clear {
            clear: both;
        }

        /* Protokoll-Item Styling */
        .protocol-item {
            border: 1px solid #333;
            margin-bottom: 15px;
            page-break-inside: auto;
            background: white;
        }

        .protocol-header {
            background-color: #B0CFFE;
            padding: 8px 10px;
            border-bottom: 1px solid #333;
            page-break-after: avoid;
        }

        .protocol-number {
            font-weight: bold;
            margin-right: 10px;
        }

        .protocol-theme {
            font-size: 11pt;
        }

        .protocol-body {
            padding: 10px;
        }

        .protocol-content-wrapper {
            margin-bottom: 10px;
        }

        .protocol-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }

        .protocol-content {
            padding-left: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .protocol-entry {
            margin-bottom: 8px;
        }

        .protocol-entry p {
            margin: 5px 0;
        }

        .protocol-tasks {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        ul {
            margin: 5px 0;
            padding-left: 25px;
        }

        ul li {
            margin: 3px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(file_exists(public_path('img/'.config('app.logo'))))
            <img src="{{ public_path('img/'.config('app.logo')) }}" alt="Logo" class="logo">
        @endif
        <h1>Protokoll</h1>
        <div class="clear"></div>
    </div>

    <table class="info-table">
        <tr>
            <td>Gremium</td>
            <td>{{ $group->name }}</td>
        </tr>
        <tr>
            <td>Datum</td>
            <td>{{ $date->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <td>Teilnehmer</td>
            <td>
                @php
                    $teilnehmer = [];
                    if (isset($presences)) {
                        foreach ($presences->where('presence', true) as $presence) {
                            if ($presence->user_id != null && $presence->user) {
                                $name = $presence->user->name;
                                if ($presence->online) {
                                    $name .= ' (online)';
                                }
                                $teilnehmer[] = $name;
                            }
                        }
                    }
                    echo implode(', ', $teilnehmer);
                @endphp
            </td>
        </tr>
        <tr>
            <td>Entschuldigt</td>
            <td>
                @php
                    $entschuldigt = [];
                    if (isset($presences)) {
                        foreach ($presences->where('excused', true) as $presence) {
                            if ($presence->user_id != null && $presence->user) {
                                $entschuldigt[] = $presence->user->name;
                            }
                        }
                    }
                    echo implode(', ', $entschuldigt);
                @endphp
            </td>
        </tr>
        <tr>
            <td>Gäste</td>
            <td>
                @php
                    $gaeste = [];
                    if (isset($presences)) {
                        foreach ($presences as $presence) {
                            if ($presence->user_id == null) {
                                $gaeste[] = $presence->guest_name;
                            }
                        }
                    }
                    echo implode(', ', $gaeste);
                @endphp
            </td>
        </tr>
        @if($protocolCreator)
        <tr>
            <td>Protokoll</td>
            <td>{{ $protocolCreator->name }}</td>
        </tr>
        @endif
        <tr>
            <td>Nächstes Treffen</td>
            <td></td>
        </tr>
    </table>

    <h2>Protokollpunkte</h2>

    @php
        // Prüfen, ob es überhaupt Aufgaben gibt
        $hasAnyTasks = false;
        foreach($themes as $theme) {
            $tasks = $theme->tasks->filter(function ($task) use ($date) {
                return $task->created_at->format('Y-m-d') == $date->format('Y-m-d');
            });
            if ($tasks->count() > 0) {
                $hasAnyTasks = true;
                break;
            }
        }
    @endphp

    @foreach($themes as $theme)
        @if($theme->protocols->count() > 0)
            <div class="protocol-item">
                <div class="protocol-header">
                    <span class="protocol-number">{{ $loop->iteration }}.</span>
                    <strong class="protocol-theme">{{ $theme->theme }}</strong>
                </div>

                <div class="protocol-body">
                    <div class="protocol-content-wrapper">
                        <div class="protocol-label">Protokoll:</div>
                        <div class="protocol-content">
                            @foreach($theme->protocols as $protocol)
                                <div class="protocol-entry">
                                    {!! strip_tags($protocol->protocol, '<p><br><b><i><u><strong><em><ul><ol><li>') !!}
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($hasAnyTasks)
                        @php
                            $tasks = $theme->tasks->filter(function ($task) use ($date) {
                                return $task->created_at->format('Y-m-d') == $date->format('Y-m-d');
                            });
                        @endphp
                        @if($tasks->count() > 0)
                            <div class="protocol-tasks">
                                <div class="protocol-label">Aufgaben:</div>
                                <ul>
                                    @foreach($tasks as $task)
                                        <li>{{ $task->taskable->name ?? '' }} - {{ $task->task }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</body>
</html>

