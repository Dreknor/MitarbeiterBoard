@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Graduierungssystem-Dokumentation</h5>
                    <a href="{{ route('paedDiary.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück zum Tagebuch
                    </a>
                </div>
                <div class="card-body">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs mb-4" id="sessionTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="new-tab" data-toggle="tab" href="#newSession" role="tab">
                                <i class="fas fa-plus-circle"></i> Neue Session starten
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="open-tab" data-toggle="tab" href="#openSessions" role="tab">
                                <i class="fas fa-clock"></i> Offene Sessions
                                @if($openSessions->isNotEmpty())
                                    <span class="badge badge-warning ml-1">{{ $openSessions->count() }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="completed-tab" data-toggle="tab" href="#completedSessions" role="tab">
                                <i class="fas fa-check-circle"></i> Abgeschlossene Sessions (Schuljahr)
                                @if($completedSessions->isNotEmpty())
                                    <span class="badge badge-info ml-1">{{ $completedSessions->count() }}</span>
                                @endif
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="sessionTabsContent">
                    <!-- Tab Content -->
                    <div class="tab-content" id="sessionTabsContent">
                        <!-- Neue Session starten -->
                        <div class="tab-pane fade show active" id="newSession" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0"><i class="fas fa-users"></i> Gruppendokumentation</h6>
                                        </div>
                                        <div class="card-body">
                                            <p>Alle Schüler einer Klasse oder Gruppe beantworten nacheinander die Fragen zur Selbsteinschätzung.</p>

                                            <form method="POST" action="{{ route('gradingDocumentation.startGroup') }}" id="groupForm">
                                                @csrf
                                                <div class="form-group">
                                                    <label for="klasse_group">Klasse auswählen</label>
                                                    <select name="klasse_id" id="klasse_group" class="form-control" required>
                                                        <option value="">-- Bitte wählen --</option>
                                                        @foreach($klassen as $klasse)
                                                            @if($klasse->gradingSystem)
                                                                <option value="{{ $klasse->id }}">{{ $klasse->name }} ({{ $klasse->gradingSystem->name }})</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </div>

                                                @if($groups->isNotEmpty())
                                                <div class="form-group">
                                                    <label for="group_id">Gruppe (optional)</label>
                                                    <select name="group_id" id="group_id" class="form-control">
                                                        <option value="">-- Alle Schüler der Klasse --</option>
                                                        @foreach($groups as $group)
                                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                @endif

                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-play"></i> Gruppendokumentation starten
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0"><i class="fas fa-user"></i> Einzeldokumentation</h6>
                                        </div>
                                        <div class="card-body">
                                            <p>Ein einzelner Schüler bearbeitet alle Fragen vorab.</p>

                                            <form method="POST" action="{{ route('gradingDocumentation.startIndividual') }}" id="individualForm">
                                                @csrf
                                                <div class="form-group">
                                                    <label for="klasse_individual">Klasse auswählen</label>
                                                    <select name="klasse_id" id="klasse_individual" class="form-control" required>
                                                        <option value="">-- Bitte wählen --</option>
                                                        @foreach($klassen as $klasse)
                                                            @if($klasse->gradingSystem)
                                                                <option value="{{ $klasse->id }}">{{ $klasse->name }} ({{ $klasse->gradingSystem->name }})</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="schueler_id">Schüler auswählen</label>
                                                    <select name="schueler_id" id="schueler_id" class="form-control" required disabled>
                                                        <option value="">-- Erst Klasse wählen --</option>
                                                    </select>
                                                </div>

                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-play"></i> Einzeldokumentation starten
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> <strong>Hinweis:</strong>
                                Nur Klassen mit einem zugewiesenen Graduierungssystem werden angezeigt.
                            </div>
                        </div>

                        <!-- Offene Sessions -->
                        <div class="tab-pane fade" id="openSessions" role="tabpanel">
                            @if($openSessions->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Typ</th>
                                                <th>Klasse</th>
                                                <th>Details</th>
                                                <th>Gestartet</th>
                                                <th>Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($openSessions as $session)
                                            <tr>
                                                <td>
                                                    @if($session->type === 'group')
                                                        <span class="badge badge-primary"><i class="fas fa-users"></i> Gruppe</span>
                                                    @else
                                                        <span class="badge badge-success"><i class="fas fa-user"></i> Einzel</span>
                                                    @endif
                                                </td>
                                                <td>{{ $session->klasse->name }}</td>
                                                <td>
                                                    @if($session->type === 'individual')
                                                        {{ $session->schueler->nachname }}, {{ $session->schueler->vorname }}
                                                    @elseif($session->group_id)
                                                        {{ $session->group->name }}
                                                    @else
                                                        Alle Schüler
                                                    @endif
                                                </td>
                                                <td>{{ $session->started_at->format('d.m.Y H:i') }}</td>
                                                <td>
                                                    @if($session->type === 'group')
                                                        <a href="{{ route('gradingDocumentation.groupSession', $session->id) }}" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-play"></i> Fortsetzen
                                                        </a>
                                                    @else
                                                        <a href="{{ route('gradingDocumentation.individualSession', $session->id) }}" class="btn btn-sm btn-success">
                                                            <i class="fas fa-play"></i> Fortsetzen
                                                        </a>
                                                    @endif
                                                    <button class="btn btn-sm btn-danger cancel-session-btn" data-session-id="{{ $session->id }}">
                                                        <i class="fas fa-times"></i> Abbrechen
                                                    </button>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Sie haben derzeit keine offenen Sessions.
                                </div>
                            @endif
                        </div>

                        <!-- Abgeschlossene Sessions -->
                        <div class="tab-pane fade" id="completedSessions" role="tabpanel">
                            <div class="mb-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Wiederöffnungsfrist:</strong> Sessions können innerhalb von {{ $reopenDays }} Tagen nach Abschluss wiedergeöffnet werden.
                                </div>
                            </div>

                            @if($completedSessions->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Typ</th>
                                                <th>Klasse</th>
                                                <th>Details</th>
                                                <th>Gestartet</th>
                                                <th>Abgeschlossen</th>
                                                <th>Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($completedSessions as $session)
                                            <tr>
                                                <td>
                                                    @if($session->type === 'group')
                                                        <span class="badge badge-primary"><i class="fas fa-users"></i> Gruppe</span>
                                                    @else
                                                        <span class="badge badge-success"><i class="fas fa-user"></i> Einzel</span>
                                                    @endif
                                                </td>
                                                <td>{{ $session->klasse->name }}</td>
                                                <td>
                                                    @if($session->type === 'individual')
                                                        {{ $session->schueler->nachname }}, {{ $session->schueler->vorname }}
                                                    @elseif($session->group_id)
                                                        {{ $session->group->name }}
                                                    @else
                                                        Alle Schüler
                                                    @endif
                                                </td>
                                                <td>{{ $session->started_at->format('d.m.Y H:i') }}</td>
                                                <td>
                                                    {{ $session->completed_at->format('d.m.Y H:i') }}
                                                    <br>
                                                    <small class="text-muted">vor {{ $session->completed_at->diffForHumans() }}</small>
                                                </td>
                                                <td>
                                                    @if($session->canBeReopened())
                                                        <button class="btn btn-sm btn-warning reopen-session-btn" data-session-id="{{ $session->id }}">
                                                            <i class="fas fa-folder-open"></i> Wiederöffnen
                                                        </button>
                                                    @else
                                                        <span class="badge badge-secondary" title="Wiederöffnungsfrist abgelaufen">
                                                            <i class="fas fa-lock"></i> Gesperrt
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Keine abgeschlossenen Sessions im aktuellen Schuljahr gefunden.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root{ --primary: #0d6efd; --muted:#6c757d; --radius:12px; }

.card { border-radius: 12px; box-shadow: 0 10px 30px rgba(15,23,42,0.06); border: none; }
.card-header { background: linear-gradient(90deg,var(--primary), #0b5ed7); color: #fff; }

.form-control { border-radius: 8px; border: 1px solid #e6e9ef; }
.form-group label { font-weight: 600; }

.btn { border-radius: 10px; transition: transform 160ms ease, box-shadow 160ms ease; }
.btn:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(13,110,253,0.08); }

.alert-info { border-radius: 10px; background: linear-gradient(90deg,#e9f2ff,#f7fbff); color: #08325a; }

.nav-tabs .nav-link { border-radius: 8px 8px 0 0; font-weight: 500; }
.nav-tabs .nav-link.active { background: #fff; border-bottom-color: #fff; }

.table-hover tbody tr:hover { background-color: #f8f9fa; }

@media (max-width: 768px){ .card { margin-bottom: 0.8rem; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const klasseIndividual = document.getElementById('klasse_individual');
    const schuelerSelect = document.getElementById('schueler_id');
    const groupForm = document.getElementById('groupForm');
    const individualForm = document.getElementById('individualForm');

    // Gruppendokumentation starten
    groupForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wird gestartet...';

        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.resumed) {
                alert('Eine offene Session für diese Auswahl wurde gefunden und wird fortgesetzt.');
            }
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                throw new Error('Keine Weiterleitung erhalten');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            alert('Fehler beim Starten der Dokumentation: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });

    // Einzeldokumentation starten
    individualForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wird gestartet...';

        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.resumed) {
                alert('Eine offene Session für diesen Schüler wurde gefunden und wird fortgesetzt.');
            }
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                throw new Error('Keine Weiterleitung erhalten');
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            alert('Fehler beim Starten der Dokumentation: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });

    // Schüler-Dropdown basierend auf Klasse laden
    klasseIndividual.addEventListener('change', function() {
        const klasseId = this.value;

        if (!klasseId) {
            schuelerSelect.disabled = true;
            schuelerSelect.innerHTML = '<option value="">-- Erst Klasse wählen --</option>';
            return;
        }

        // Lade Schüler der ausgewählten Klasse
        fetch(`/paed-diary/klasse/${klasseId}/schueler`)
            .then(response => response.json())
            .then(data => {
                schuelerSelect.disabled = false;
                schuelerSelect.innerHTML = '<option value="">-- Bitte wählen --</option>';

                if (data.schueler && data.schueler.length > 0) {
                    data.schueler.forEach(schueler => {
                        const option = document.createElement('option');
                        option.value = schueler.id;
                        option.textContent = `${schueler.nachname}, ${schueler.vorname}`;
                        schuelerSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Fehler beim Laden der Schüler:', error);
                alert('Fehler beim Laden der Schüler.');
            });
    });

    // Session abbrechen
    const cancelButtons = document.querySelectorAll('.cancel-session-btn');
    cancelButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sessionId = this.dataset.sessionId;

            if (!confirm('Möchten Sie diese Session wirklich abbrechen? Alle bisherigen Eingaben gehen verloren.')) {
                return;
            }

            const originalBtnText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(`/paed-diary/documentation/session/${sessionId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                alert('Session erfolgreich abgebrochen.');
                location.reload();
            })
            .catch(error => {
                console.error('Fehler:', error);
                alert('Fehler beim Abbrechen der Session.');
                this.disabled = false;
                this.innerHTML = originalBtnText;
            });
        });
    });

    // Session wiederöffnen
    const reopenButtons = document.querySelectorAll('.reopen-session-btn');
    reopenButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sessionId = this.dataset.sessionId;

            if (!confirm('Möchten Sie diese abgeschlossene Session wiederöffnen? Die Session wird wieder bearbeitbar.')) {
                return;
            }

            const originalBtnText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(`/paed-diary/documentation/session/${sessionId}/reopen`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw err; });
                }
                return response.json();
            })
            .then(data => {
                alert(data.message);
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Fehler:', error);
                alert(error.message || 'Fehler beim Wiederöffnen der Session.');
                this.disabled = false;
                this.innerHTML = originalBtnText;
            });
        });
    });
});
</script>
@endsection
