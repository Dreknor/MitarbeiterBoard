@extends('layouts.app')

@section('title')
    Nextcloud Talk Konfiguration
@endsection

@section('site-title')
    Nextcloud Talk Konfiguration
@endsection

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Nextcloud Talk Integration für Dienstpläne</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Die Konfiguration erfolgt über Umgebungsvariablen in der <code>.env</code>-Datei.
                            Siehe <a href="{{ asset('docs/NEXTCLOUD_TALK_INTEGRATION.md') }}" target="_blank">Dokumentation</a> für Details.
                        </div>

                        <h6>Aktueller Status</h6>
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th style="width: 40%">Nextcloud Talk aktiviert:</th>
                                    <td>
                                        @if(config('nextcloud.enabled'))
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> Aktiviert
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-times"></i> Deaktiviert
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Nextcloud URL:</th>
                                    <td>
                                        @if(config('nextcloud.url'))
                                            {{ config('nextcloud.url') }}
                                        @else
                                            <em class="text-muted">Nicht konfiguriert</em>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Benutzername:</th>
                                    <td>
                                        @if(config('nextcloud.username'))
                                            {{ config('nextcloud.username') }}
                                        @else
                                            <em class="text-muted">Nicht konfiguriert</em>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Passwort:</th>
                                    <td>
                                        @if(config('nextcloud.password'))
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> Konfiguriert
                                            </span>
                                        @else
                                            <span class="badge badge-warning">
                                                <i class="fas fa-exclamation-triangle"></i> Nicht konfiguriert
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Chat-Token:</th>
                                    <td>
                                        @if(config('nextcloud.roster_chat_token'))
                                            <code>{{ substr(config('nextcloud.roster_chat_token'), 0, 8) }}...</code>
                                        @else
                                            <em class="text-muted">Nicht konfiguriert</em>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <h6 class="mt-4">Konfiguration in .env</h6>
                        <p>Fügen Sie folgende Zeilen zu Ihrer <code>.env</code>-Datei hinzu:</p>
                        <pre class="bg-light p-3 rounded"><code>NEXTCLOUD_ENABLED=true
NEXTCLOUD_URL=https://nextcloud.example.com
NEXTCLOUD_USERNAME=ihr_username
NEXTCLOUD_PASSWORD=ihr_passwort_oder_app_token
NEXTCLOUD_ROSTER_CHAT_TOKEN=chat_token_hier</code></pre>

                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Wichtig:</strong> Nach Änderungen an der <code>.env</code>-Datei muss der Konfigurationscache geleert werden:
                            <br><code>php artisan config:clear</code>
                        </div>

                        <h6 class="mt-4">Wie finde ich den Chat-Token?</h6>
                        <ol>
                            <li>Öffnen Sie Nextcloud Talk im Browser</li>
                            <li>Wählen Sie den gewünschten Chat/Raum aus</li>
                            <li>Kopieren Sie den Token aus der URL: <code>https://nextcloud.example.com/call/<strong>TOKEN_HIER</strong></code></li>
                        </ol>

                        @if(config('nextcloud.enabled') && config('nextcloud.url') && config('nextcloud.username') && config('nextcloud.password') && config('nextcloud.roster_chat_token'))
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle"></i>
                                <strong>Konfiguration vollständig!</strong> Sie können nun Dienstpläne an Nextcloud Talk senden.
                            </div>
                        @else
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-times-circle"></i>
                                <strong>Konfiguration unvollständig.</strong> Bitte konfigurieren Sie alle erforderlichen Parameter.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6>Weitere Informationen</h6>
                    </div>
                    <div class="card-body">
                        <p>Für detaillierte Anweisungen zur Einrichtung und Fehlerbehebung, lesen Sie die vollständige Dokumentation:</p>
                        <a href="{{ url('docs/NEXTCLOUD_TALK_INTEGRATION.md') }}" class="btn btn-primary" target="_blank">
                            <i class="fas fa-book"></i> Dokumentation öffnen
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
