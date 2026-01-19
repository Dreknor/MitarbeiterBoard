<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erinnerung ausstehende Aufgaben</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f7fa;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px 25px;
        }
        .greeting {
            font-size: 18px;
            color: #333333;
            margin-bottom: 20px;
        }
        .intro-text {
            color: #555555;
            margin-bottom: 25px;
            font-size: 15px;
        }
        .task-card {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .task-card.overdue {
            border-left-color: #e74c3c;
            background-color: #ffebee;
        }
        .task-card.today {
            border-left-color: #f39c12;
            background-color: #fff8e1;
        }
        .task-card.upcoming {
            border-left-color: #27ae60;
            background-color: #e8f5e9;
        }
        .task-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        .meta-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #666666;
        }
        .meta-icon {
            display: inline-block;
            width: 20px;
            margin-right: 6px;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .badge-overdue {
            background-color: #e74c3c;
            color: #ffffff;
        }
        .badge-today {
            background-color: #f39c12;
            color: #ffffff;
        }
        .badge-upcoming {
            background-color: #27ae60;
            color: #ffffff;
        }
        .btn-complete {
            display: inline-block;
            padding: 10px 20px;
            background-color: #667eea;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .btn-complete:hover {
            background-color: #5568d3;
        }
        .divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 25px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 25px;
            text-align: center;
            color: #777777;
            font-size: 14px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .task-count {
            background-color: #667eea;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 600;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            .content {
                padding: 20px 15px;
            }
            .task-meta {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>📋 Aufgaben-Erinnerung</h1>
        </div>

        <div class="content">
            <div class="greeting">
                Hallo {{$name}},
            </div>

            <div class="intro-text">
                Du hast <span class="task-count">{{ count($tasks) }} {{ count($tasks) === 1 ? 'ausstehende Aufgabe' : 'ausstehende Aufgaben' }}</span> in <a href="{{config('app.url')}}" style="color: #667eea; text-decoration: none; font-weight: 600;">{{config('app.name')}}</a>, die deine Aufmerksamkeit benötigen:
            </div>

            @foreach($tasks as $index => $task)
                @php
                    $today = now()->startOfDay();
                    $taskDate = $task->date ? \Carbon\Carbon::parse($task->date)->startOfDay() : null;
                    $status = 'upcoming';
                    $statusText = 'Demnächst';

                    if ($taskDate) {
                        if ($taskDate->lt($today)) {
                            $status = 'overdue';
                            $statusText = 'Überfällig';
                        } elseif ($taskDate->eq($today)) {
                            $status = 'today';
                            $statusText = 'Heute fällig';
                        } else {
                            $daysUntil = $today->diffInDays($taskDate);
                            if ($daysUntil <= 3) {
                                $statusText = 'In ' . $daysUntil . ' ' . ($daysUntil === 1 ? 'Tag' : 'Tagen');
                            } else {
                                $statusText = 'Fällig in ' . $daysUntil . ' Tagen';
                            }
                        }
                    }
                @endphp

                <div class="task-card {{ $status }}">
                    <span class="status-badge badge-{{ $status }}">{{ $statusText }}</span>

                    <div class="task-title">
                        {{ $task->task }}
                    </div>

                    <div class="task-meta">
                        @if($task->date)
                            <div class="meta-item">
                                <span class="meta-icon">📅</span>
                                <span><strong>Fälligkeitsdatum:</strong> {{ $task->date->format('d.m.Y') }}</span>
                            </div>
                        @endif

                        @if($task->theme)
                            <div class="meta-item">
                                <span class="meta-icon">🏷️</span>
                                <span><strong>Thema:</strong> {{ $task->theme->name ?? 'Kein Thema' }}</span>
                            </div>
                        @endif
                    </div>

                    <a href="{{config('app.url').'/tasks/'.$task->id.'/complete'}}" class="btn-complete">
                        ✓ Als erledigt markieren
                    </a>
                </div>

                @if($index < count($tasks) - 1)
                    <div class="divider"></div>
                @endif
            @endforeach
        </div>

        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                Diese E-Mail wurde automatisch von <a href="{{config('app.url')}}">{{config('app.name')}}</a> gesendet.
            </p>
            <p style="margin: 0;">
                <a href="{{config('app.url')}}">Zum Dashboard</a>
            </p>
        </div>
    </div>
</body>
</html>
