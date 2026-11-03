<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Protokoll {{ $meeting->title }} - {{ $meeting->date->format('d.m.Y') }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; margin: 0; padding: 0; }
        h1 { font-size: 18pt; margin: 0 0 10px; color: #333; }
        h2 { font-size: 14pt; margin: 15px 0 10px; color: #333; border-bottom: 2px solid #B0CFFE; padding-bottom: 5px; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.info-table td { padding: 5px; border: 1px solid #ddd; }
        table.info-table td:first-child { font-weight: bold; width: 30%; background-color: #f5f5f5; }
        .logo { float: right; max-height: 50px; }
        .clear { clear: both; }
        .protocol-item { border: 1px solid #333; margin-bottom: 15px; }
        .protocol-header { background-color: #B0CFFE; padding: 8px 10px; border-bottom: 1px solid #333; page-break-after: avoid; }
        .protocol-body { padding: 10px; }
        .protocol-label { font-weight: bold; margin-bottom: 5px; color: #555; }
        .protocol-entry { margin-bottom: 8px; word-wrap: break-word; }
        .protocol-entry p { margin: 5px 0; }
        .protocol-tasks { margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; }
        ul { margin: 5px 0; padding-left: 25px; }
    </style>
</head>
<body>
    <div>
        @if(file_exists(public_path('img/'.config('app.logo'))))
            <img src="{{ public_path('img/'.config('app.logo')) }}" alt="Logo" class="logo">
        @endif
        <h1>Protokoll</h1>
        <div class="clear"></div>
    </div>

    <table class="info-table">
        <tr><td>Meeting</td><td>{{ $meeting->title }} ({{ $meeting->contextLabel() }})</td></tr>
        <tr><td>Datum</td><td>{{ $meeting->date->format('d.m.Y') }}, {{ $meeting->start_time }} – {{ $meeting->end_time }} Uhr</td></tr>
        <tr><td>Anwesend</td><td>{{ $attendance['present']->implode(', ') }}</td></tr>
        @if($attendance['online']->isNotEmpty())
            <tr><td>Online</td><td>{{ $attendance['online']->implode(', ') }}</td></tr>
        @endif
        <tr><td>Entschuldigt</td><td>{{ $attendance['excused']->implode(', ') }}</td></tr>
        <tr><td>Gäste</td><td>{{ $attendance['guests']->implode(', ') }}</td></tr>
        @if($meeting->meetingTasks->isNotEmpty())
            <tr>
                <td>Rollen</td>
                <td>{{ $meeting->meetingTasks->map(fn ($t) => $t->role . ': ' . $t->user?->name)->implode(', ') }}</td>
            </tr>
        @endif
        @if($authors->isNotEmpty())
            <tr><td>Protokoll</td><td>{{ $authors->implode(', ') }}</td></tr>
        @endif
    </table>

    <h2>Protokollpunkte</h2>

    @foreach($themes as $theme)
        <div class="protocol-item">
            <div class="protocol-header">
                <strong>{{ $loop->iteration }}. {{ $theme->theme }}</strong>
            </div>
            <div class="protocol-body">
                @if(($includeInfo ?? false) && filled(strip_tags((string) $theme->information)))
                    <div class="protocol-tasks" style="border-top:0; margin-top:0; padding-top:0; margin-bottom:10px;">
                        <div class="protocol-label">Informationen:</div>
                        <div class="protocol-entry">
                            {!! strip_tags($theme->information, '<p><br><b><i><u><strong><em><ul><ol><li>') !!}
                        </div>
                    </div>
                @endif
                <div class="protocol-label">Protokoll:</div>
                @foreach($theme->protocols as $protocol)
                    <div class="protocol-entry">
                        {!! strip_tags($protocol->protocol, '<p><br><b><i><u><strong><em><ul><ol><li>') !!}
                    </div>
                @endforeach

                @if($theme->tasks->isNotEmpty())
                    <div class="protocol-tasks">
                        <div class="protocol-label">Aufgaben:</div>
                        <ul>
                            @foreach($theme->tasks as $task)
                                <li>{{ $task->taskable->name ?? '' }} - {{ $task->task }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</body>
</html>


