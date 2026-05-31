<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erinnerung an ausstehende Prozess-SchritteS </title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }

        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .email-body {
            padding: 30px 20px;
        }

        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333333;
        }

        .intro-text {
            margin-bottom: 25px;
            color: #555555;
            font-size: 15px;
        }

        .task-card {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .task-card:hover {
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease;
        }

        .task-label {
            font-weight: 600;
            color: #667eea;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .task-title {
            font-size: 18px;
            font-weight: 600;
            color: #333333;
            margin-bottom: 12px;
        }

        .task-title a {
            color: #333333;
            text-decoration: none;
        }

        .task-title a:hover {
            color: #667eea;
        }

        .task-detail {
            margin: 8px 0;
            font-size: 14px;
            color: #555555;
        }

        .task-detail-label {
            font-weight: 600;
            color: #333333;
            display: inline-block;
            min-width: 100px;
        }

        .task-date {
            display: inline-flex;
            align-items: center;
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }

        .action-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            display: inline-block;
            padding: 10px 24px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #667eea;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #5568d3;
        }

        .btn-success {
            background-color: #28a745;
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .footer-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        .divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 20px 0;
        }

        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .email-body {
                padding: 20px 15px;
            }

            .task-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
@php
    use Illuminate\Support\Facades\Log;
    Log::info('remindStepMail view loaded');
@endphp
<div class="email-container">
    <!-- Header -->
    <div class="email-header">
        <h1>📋 Erinnerung an ausstehende Aufträge</h1>
    </div>

    <!-- Body -->
    <div class="email-body">
        <div class="greeting">
            Hallo {{$name}},
        </div>

        <div class="intro-text">
            im <strong>{{config('app.name')}}</strong> stehen folgende Aufträge zur Erledigung an.
            Bitte überprüfen Sie den Status und erledigen Sie die anstehenden Aufgaben rechtzeitig.
        </div>

        <!-- Tasks List -->
        @foreach($steps as $step)
            <div class="task-card">
                <div class="task-label">Prozess</div>
                <div class="task-title">
                    <a href="{{config('app.url')}}/procedure/{{$step['procedureId']}}/start">
                        {{$step['procedureName']}}
                    </a>
                </div>

                <div class="task-detail">
                    <span class="task-detail-label">Aufgabe:</span>
                    {{$step['stepName']}}
                </div>

                <div class="task-detail">
                    <span class="task-detail-label">Fälligkeitsdatum:</span>
                    <span class="task-date">⏰ {{$step['endDate']}}</span>
                </div>

                <div class="action-section">
                    <a href="{{config('app.url')}}/procedure/{{$step['procedureId']}}/start" class="btn btn-primary">
                        Prozess öffnen &amp; Schritt als erledigt markieren
                    </a>
                </div>
            </div>
        @endforeach

        <div class="divider"></div>

        <p style="color: #666666; font-size: 14px; margin-top: 20px;">
            Sie können alle Ihre Aufträge jederzeit im System einsehen und bearbeiten.
        </p>
    </div>

    <!-- Footer -->
    <div class="email-footer">
        <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
            Diese E-Mail wurde automatisch vom System generiert.
        </p>
        <a href="{{config('app.url')}}" class="footer-link">
            Zum {{config('app.name')}} →
        </a>
    </div>
</div>
</body>
</html>
