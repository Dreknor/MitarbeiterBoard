@extends('layouts.app')

@php
    $stateLabels = [
        'idle' => ['secondary', 'Kein Update gelaufen'],
        'queued' => ['info', 'Wird gestartet'],
        'running' => ['primary', 'Läuft'],
        'success' => ['success', 'Erfolgreich'],
        'failed' => ['danger', 'Fehlgeschlagen'],
    ];
    $passed = collect($checks)->every(fn ($c) => $c['ok'] || ! $c['blocking']);
@endphp

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-6">
                {{-- Installierter Stand & verfügbare Änderungen --}}
                <div class="card">
                    <div class="card-header bg-gradient-y2-info">
                        <h5 class="mb-0">Anwendungsupdater</h5>
                    </div>
                    <div class="card-body">
                        <h6>Installierter Stand</h6>
                        @if($version)
                            <p>
                                <code>{{ $version['short'] }}</code>
                                @if($version['branch']) auf Branch <strong>{{ $version['branch'] }}</strong>@endif
                                <br>
                                <small class="text-muted">
                                    {{ $version['date']?->format('d.m.Y H:i') }} – {{ $version['subject'] }}
                                </small>
                            </p>
                        @else
                            <p class="text-danger">Der Stand konnte nicht ermittelt werden (kein Git-Checkout?).</p>
                        @endif

                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">
                                Verfügbare Änderungen
                                @if($remoteRef)<small class="text-muted">({{ $remoteRef }})</small>@endif
                            </h6>
                            <form action="{{ route('updater.check') }}" method="post">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-info" @disabled($active)>
                                    <i class="fas fa-sync"></i> Nach Updates suchen
                                </button>
                            </form>
                        </div>

                        @if(count($commits))
                            <ul class="list-group list-group-flush mt-2" style="max-height: 320px; overflow-y: auto;">
                                @foreach($commits as $commit)
                                    <li class="list-group-item px-0 py-1">
                                        <code>{{ $commit['short'] }}</code> {{ $commit['subject'] }}
                                        <br>
                                        <small class="text-muted">
                                            {{ $commit['author'] }}, {{ $commit['date']?->format('d.m.Y H:i') }}
                                        </small>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted mt-2 mb-0">
                                Keine neuen Änderungen bekannt. „Nach Updates suchen“ fragt den Server erneut ab.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Vorabprüfung & Start --}}
                @unless($active)
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Vorabprüfung</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                @foreach($checks as $check)
                                    <li class="mb-1">
                                        @if($check['ok'])
                                            <i class="fas fa-check-circle text-success"></i>
                                        @elseif($check['blocking'])
                                            <i class="fas fa-times-circle text-danger"></i>
                                        @else
                                            <i class="fas fa-exclamation-triangle text-warning"></i>
                                        @endif
                                        {{ $check['label'] }}
                                        @if($check['hint'])
                                            <br><small class="text-muted ml-4">{{ $check['hint'] }}</small>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <div class="alert alert-warning small">
                                Vor dem Update sollte ein aktuelles Backup von Datenbank und Dateien vorhanden sein.
                                Während des Updates ist die Anwendung im Wartungsmodus und für alle anderen nicht erreichbar.
                                Schlägt composer oder der Frontend-Build fehl, wird automatisch auf den vorherigen Stand
                                zurückgesetzt; Datenbankmigrationen werden nicht zurückgerollt.
                            </div>

                            <form action="{{ route('updater.update') }}" method="post"
                                  onsubmit="return confirm('Update jetzt starten? Die Anwendung ist währenddessen im Wartungsmodus.');">
                                @csrf
                                <button type="submit" class="btn btn-primary" @disabled(! $passed || ! count($commits))>
                                    <i class="fas fa-cloud-download-alt"></i> Update starten
                                </button>
                                @if($passed && ! count($commits))
                                    <small class="text-muted ml-2">Keine neuen Änderungen – zuerst nach Updates suchen.</small>
                                @elseif(! $passed)
                                    <small class="text-danger ml-2">Bitte zuerst die markierten Punkte beheben.</small>
                                @endif
                            </form>
                        </div>
                    </div>
                @endunless
            </div>

            {{-- Status & Log --}}
            <div class="col-lg-6">
                <div class="card" id="updater-status"
                     data-url="{{ route('updater.status') }}"
                     data-active="{{ $active ? '1' : '0' }}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Letztes Update</h6>
                        @php([$color, $label] = $stateLabels[$status['state']] ?? $stateLabels['idle'])
                        <span class="badge badge-{{ $color }}" data-field="badge">{{ $label }}</span>
                    </div>
                    <div class="card-body">
                        <p class="mb-1" data-field="step">
                            @if($active && ! empty($status['step']))
                                <i class="fas fa-spinner fa-spin"></i> {{ $status['step'] }}
                            @endif
                        </p>
                        <p class="mb-1" data-field="message">{{ $status['message'] ?? '' }}</p>
                        <p class="small text-muted mb-2" data-field="meta">
                            @if(! empty($status['started_at']))
                                Gestartet {{ \Illuminate\Support\Carbon::parse($status['started_at'])->format('d.m.Y H:i') }}
                                @if(! empty($status['user'])) von {{ $status['user'] }}@endif
                                @if(! empty($status['finished_at']))
                                    – beendet {{ \Illuminate\Support\Carbon::parse($status['finished_at'])->format('H:i') }}
                                @endif
                            @endif
                        </p>
                        <pre class="bg-dark text-light p-2 small mb-0" data-field="log"
                             style="max-height: 480px; min-height: 120px; overflow-y: auto; white-space: pre-wrap;">{{ $log ?: 'Noch kein Log vorhanden.' }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function () {
            const card = document.getElementById('updater-status');
            if (!card || card.dataset.active !== '1') {
                return;
            }

            const labels = @json($stateLabels);
            const field = name => card.querySelector('[data-field="' + name + '"]');
            const logEl = field('log');
            logEl.scrollTop = logEl.scrollHeight;
            let failures = 0;

            async function poll() {
                try {
                    const response = await fetch(card.dataset.url, {headers: {'Accept': 'application/json'}});
                    if (!response.ok) throw new Error(response.status);
                    const data = await response.json();
                    failures = 0;

                    const status = data.status;
                    const [color, label] = labels[status.state] || labels.idle;
                    const badge = field('badge');
                    badge.className = 'badge badge-' + color;
                    badge.textContent = label;

                    const atBottom = logEl.scrollTop + logEl.clientHeight >= logEl.scrollHeight - 20;
                    logEl.textContent = data.log || '';
                    if (atBottom) logEl.scrollTop = logEl.scrollHeight;

                    field('message').textContent = status.message || '';

                    if (status.state === 'queued' || status.state === 'running') {
                        field('step').innerHTML = '<i class="fas fa-spinner fa-spin"></i> ';
                        field('step').append(status.step || '');
                        setTimeout(poll, 2000);
                    } else {
                        field('step').textContent = '';
                        // Seite neu laden, damit installierter Stand und Änderungen aktuell sind
                        setTimeout(() => window.location.reload(), 3000);
                    }
                } catch (e) {
                    // Während composer/Wartungsmodus kann die Anwendung kurz nicht antworten
                    failures++;
                    field('step').textContent = 'Warte auf Antwort der Anwendung …';
                    setTimeout(poll, Math.min(2000 * failures, 10000));
                }
            }

            setTimeout(poll, 1000);
        })();
    </script>
@endpush
