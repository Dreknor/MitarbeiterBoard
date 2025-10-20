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
            </div>
        </div>
    </div>
</div>

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
});
</script>
@endsection
