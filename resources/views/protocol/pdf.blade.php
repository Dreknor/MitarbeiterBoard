<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protokoll {{ $group->name }} - {{ $date->format('d.m.Y') }}</title>
    <style>
        @page {
            margin: 2cm 1.5cm 2.5cm 1.5cm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            margin: 0;
        }

        h1 {
            font-size: 18pt;
            margin-bottom: 10px;
            color: #333;
        }

        h2 {
            font-size: 14pt;
            margin-top: 15px;
            margin-bottom: 10px;
            color: #333;
            page-break-after: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table.info-table {
            margin-bottom: 20px;
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

        table.protocol-table {
            page-break-inside: auto;
        }

        table.protocol-table thead {
            display: table-header-group;
        }

        table.protocol-table tfoot {
            display: table-footer-group;
        }

        table.protocol-table th {
            background-color: #B0CFFE;
            padding: 8px;
            border: 1px solid #333;
            font-weight: bold;
            text-align: left;
        }

        table.protocol-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        table.protocol-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        table.protocol-table td.number {
            width: 5%;
            text-align: center;
        }

        table.protocol-table td.theme {
            width: 30%;
        }

        table.protocol-table td.protocol {
            width: 45%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.protocol-table td.task {
            width: 20%;
        }

        .header {
            margin-bottom: 20px;
            page-break-after: avoid;
        }

        .logo {
            float: right;
            max-height: 50px;
        }

        .clear {
            clear: both;
        }

        .footer {
            position: fixed;
            bottom: 1cm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }

        ul {
            margin: 0;
            padding-left: 20px;
        }

        .page-break {
            page-break-after: always;
        }

        .protocol-content {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .protocol-content p {
            margin: 5px 0;
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

    <table class="protocol-table">
        <thead>
            <tr>
                <th class="number">#</th>
                <th class="theme">Thema</th>
                <th class="protocol">Protokoll</th>
                <th class="task">Aufgaben</th>
            </tr>
        </thead>
        <tbody>
            @foreach($themes as $theme)
                @if($theme->protocols->count() > 0)
                <tr>
                    <td class="number">{{ $loop->iteration }}</td>
                    <td class="theme">
                        <strong>{{ $theme->theme }}</strong>
                    </td>
                    <td class="protocol">
                        <div class="protocol-content">
                            @foreach($theme->protocols as $protocol)
                                <div style="margin-bottom: 5px;">
                                    {!! strip_tags($protocol->protocol, '<p><br><b><i><u><strong><em><ul><ol><li>') !!}
                                </div>
                            @endforeach
                        </div>
                    </td>
                    <td class="task">
                        @php
                            $tasks = $theme->tasks->filter(function ($task) use ($date) {
                                return $task->created_at->format('Y-m-d') == $date->format('Y-m-d');
                            });
                        @endphp
                        @if($tasks->count() > 0)
                            <ul>
                                @foreach($tasks as $task)
                                    <li>{{ $task->taskable->name ?? '' }} - {{ $task->task }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>

