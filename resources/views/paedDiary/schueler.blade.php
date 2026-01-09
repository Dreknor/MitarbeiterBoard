@extends('layouts.app')

@section('content')
<div class="container-fluid" id="schueler-diary-app">
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-wrap">
                        <h5 class="mb-0 mr-3">Pädagogisches Tagebuch - {{ $schueler->vorname }} {{ $schueler->nachname }}</h5>
                        <div class="small text-muted">Klasse: {{ $klasse->name }}</div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center">
                        <a href="{{ route('paedDiary.index', ['klasse' => $klasse->id]) }}" class="btn btn-sm btn-outline-secondary mb-1 mr-2">
                            <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                        </a>
                        <button id="exportWordBtn" class="btn btn-sm btn-outline-success mb-1" title="Excel Export">
                            <i class="fas fa-file-excel"></i> Excel Export
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <!-- Zeitraum-Filter -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="small mb-1" for="dateFrom">Von:</label>
                            <input type="date" id="dateFrom" class="form-control form-control-sm" value="{{ now()->subDays(30)->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="small mb-1" for="dateTo">Bis:</label>
                            <input type="date" id="dateTo" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button id="loadDataBtn" class="btn btn-primary btn-sm">Daten laden</button>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary" id="last7Days">7 Tage</button>
                                <button type="button" class="btn btn-outline-secondary" id="last30Days">30 Tage</button>
                                <button type="button" class="btn btn-outline-secondary" id="last90Days">90 Tage</button>
                            </div>
                        </div>
                    </div>

                    <!-- Neue Filter-Zeile: Kategorie + Suche -->


                    <div id="loadingIndicator" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Lade...</span>
                        </div>
                        <div class="mt-2">Daten werden geladen...</div>
                    </div>

                    <!-- Zusammenfassung -->
                    <div id="summarySection" class="row mb-4 d-none">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body p-2 text-center">
                                    <h6 class="card-title mb-1">Zeitraum</h6>
                                    <p class="card-text small mb-0" id="periodText"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body p-2 text-center">
                                    <h6 class="card-title mb-1">Einträge</h6>
                                    <p class="card-text mb-0"><span id="entriesCount">0</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body p-2 text-center">
                                    <h6 class="card-title mb-1">Aufgaben</h6>
                                    <p class="card-text mb-0"><span id="tasksCount">0</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body p-2 text-center">
                                    <h6 class="card-title mb-1">Tage mit Einträgen</h6>
                                    <p class="card-text mb-0"><span id="daysWithEntriesCount">0</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daten-Anzeige -->
                    <div id="dataSection" class="d-none">
                        <!-- Navigation zwischen Ansichten -->
                        <ul class="nav nav-tabs mb-3" id="viewTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" id="entries-tab" data-toggle="tab" href="#entries" role="tab">
                                    Einträge <span class="badge badge-secondary" id="entriesBadge">0</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="tasks-tab" data-toggle="tab" href="#tasks" role="tab">
                                    Aufgaben <span class="badge badge-secondary" id="tasksBadge">0</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="columns-tab" data-toggle="tab" href="#columns" role="tab">
                                    Spalten <span class="badge badge-secondary" id="columnsBadge">0</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="graduations-tab" data-toggle="tab" href="#graduations" role="tab">
                                    Dokumentation <span class="badge badge-secondary">{{ $gradingSessions->count() }}</span>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content" id="viewTabContent">
                            <!-- Einträge Tab -->
                            <div class="tab-pane fade show active" id="entries" role="tabpanel">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="small mb-1" for="categoryFilter">Kategorie</label>
                                        <select id="categoryFilter" class="form-control form-control-sm">
                                            <option value="">Alle Kategorien</option>
                                            <!-- Kategorien werden clientseitig gefüllt -->
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="small mb-1" for="searchNotes">Suche Notizen</label>
                                        <div class="">
                                            <input type="text" id="searchNotes" class="form-control" placeholder="Textsuche in Notizen (Inhalt, Autor)">

                                        </div>
                                    </div>

                                    <div class="col-md-2 d-flex align-items-end">
                                        <small class="text-muted">Suche wird clientseitig gefiltert</small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped" id="entriesTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 100px;">Datum</th>
                                                <th class="w-50">Notiz</th>
                                                <th>Kategorie</th>
                                                <th style="width: 120px;">Autor</th>
                                            </tr>
                                        </thead>
                                        <tbody id="entriesTableBody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Aufgaben Tab -->
                            <div class="tab-pane fade" id="tasks" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped" id="tasksTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 120px;">Erstellt</th>
                                                <th>Titel</th>
                                                <th>Beschreibung</th>
                                                <th style="width: 100px;">Fällig</th>
                                                <th style="width: 80px;">Status</th>
                                                <th style="width: 80px;">Priorität</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tasksTableBody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Spalten Tab -->
                            <div class="tab-pane fade" id="columns" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped" id="columnsTable">
                                        <thead class="thead-light">
                                            <tr id="columnHeaders">
                                                <th style="width: 100px;">Datum</th>

                                            </tr>
                                        </thead>
                                        <tbody id="columnsTableBody"></tbody>
                                        <tfoot id="columnsTableFooter"></tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Graduierungen Tab -->
                            <div class="tab-pane fade" id="graduations" role="tabpanel">
                                @if($gradingSessions->isEmpty())
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Für diesen Schüler liegen noch keine Graduierungs-Dokumentationen vor.
                                    </div>
                                @else
                                    <!-- Entwicklungs-Übersicht -->
                                    <div class="row mb-4">
                                        <div class="col-lg-6 mb-3">
                                            <div class="card">
                                                <div class="card-header bg-primary text-white">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-chart-radar"></i> Aktuelle Kompetenzen (Letzte Session)
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <canvas id="radarChart" height="280"></canvas>
                                                    <div class="text-center mt-2">
                                                        <small class="text-muted">
                                                            <span class="badge badge-info">●</span> Schüler-Einschätzung &nbsp;
                                                            <span class="badge badge-success">●</span> Lehrer-Bewertung
                                                        </small>
                                                    </div>
                                                    <div class="mt-3 pt-2 border-top">
                                                        <small class="text-muted d-block mb-1"><strong>Bewertungsskala:</strong></small>
                                                        <div class="d-flex justify-content-between align-items-center px-2">
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-frown text-danger" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">1</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-frown-open text-warning" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">2</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-meh text-secondary" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">3</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-smile text-info" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">4</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-grin-stars text-success" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">5</small></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 mb-3">
                                            <div class="card">
                                                <div class="card-header bg-success text-white">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-chart-line"></i> Entwicklung über Zeit
                                                        </h6>
                                                        <div class="d-flex align-items-center">
                                                            <select id="lineChartQuestionSelector" class="form-control form-control-sm" style="max-width: 250px; background-color: white; color: #333;">
                                                                <option value="average">Durchschnitt aller Fragen</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <canvas id="lineChart" height="280"></canvas>
                                                    <div class="text-center mt-2">
                                                        <small class="text-muted" id="lineChartDescription">Durchschnittliche Bewertung über alle Fragen</small>
                                                    </div>
                                                    <div class="mt-3 pt-2 border-top">
                                                        <small class="text-muted d-block mb-1"><strong>Bewertungsskala:</strong></small>
                                                        <div class="d-flex justify-content-between align-items-center px-2">
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-frown text-danger" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">1</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-frown-open text-warning" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">2</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-meh text-secondary" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">3</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-smile text-info" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">4</small></div>
                                                            </div>
                                                            <div class="text-center" style="flex: 1;">
                                                                <i class="fas fa-grin-stars text-success" style="font-size: 1.5rem;"></i>
                                                                <div><small class="text-muted">5</small></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Schwierige Bereiche Zusammenfassung -->
                                    <div class="card mb-4 border-warning">
                                        <div class="card-header bg-warning">
                                            <h6 class="mb-0">
                                                <i class="fas fa-exclamation-triangle"></i> Entwicklungsbereiche
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="challengingAreas">
                                                <div class="text-center text-muted">
                                                    <i class="fas fa-spinner fa-spin"></i> Wird berechnet...
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Einzelne Dokumentations-Sessions -->
                                    <h6 class="mb-3"><i class="fas fa-list"></i> Einzelne Reflexions-Sessions</h6>
                                    <div class="accordion" id="documentationAccordion">
                                        @foreach($gradingSessions as $session)
                                            <div class="card mb-2">
                                                <div class="card-header" id="heading{{ $session->id }}">
                                                    <h6 class="mb-0">
                                                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse"
                                                                data-target="#collapse{{ $session->id }}" aria-expanded="false">
                                                            <i class="fas fa-calendar-alt"></i>
                                                            {{ $session->completed_at->format('d.m.Y H:i') }} Uhr
                                                            <span class="badge badge-info ml-2">{{ $session->gradingSystem->name }}</span>
                                                            <span class="badge badge-secondary ml-1">
                                                                {{ $session->isGroupSession() ? 'Gruppe' : 'Einzeln' }}
                                                            </span>
                                                        </button>
                                                    </h6>
                                                </div>
                                                <div id="collapse{{ $session->id }}" class="collapse" data-parent="#documentationAccordion">
                                                    <div class="card-body">
                                                        <p class="text-muted mb-3">
                                                            <strong>Lehrer:</strong> {{ $session->user->name }}
                                                        </p>
                                                            <table class="table table-sm table-bordered">
                                                                <thead class="thead-light">
                                                                    <tr>
                                                                        <th style="width: 40%;" rowspan="2">Frage</th>
                                                                        <th style="width: 30%" colspan="2" class="align-center">Einschätzung</th>
                                                                        <th rowspan="2">Kommentar</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="width: 15%;">Schüler</th>
                                                                        <th style="width: 15%;">Lehrer</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($session->gradingSystem->questions as $question)
                                                                        @php
                                                                            $studentAnswer = $session->studentAnswers->where('schueler_id', $schueler->id)
                                                                                                                     ->where('question_id', $question->id)
                                                                                                                     ->first();
                                                                            $teacherAssessment = $session->teacherAssessments->where('schueler_id', $schueler->id)
                                                                                                                              ->where('question_id', $question->id)
                                                                                                                              ->first();
                                                                        @endphp
                                                                        <tr>
                                                                            <td>{{ $question->question }}</td>
                                                                            <td class="text-center">
                                                                                @if($studentAnswer)
                                                                                    @php
                                                                                        $rating = $studentAnswer->self_rating;
                                                                                        $icons = [
                                                                                            1 => 'fas fa-frown text-danger',
                                                                                            2 => 'fas fa-frown-open text-warning',
                                                                                            3 => 'fas fa-meh text-secondary',
                                                                                            4 => 'fas fa-smile text-info',
                                                                                            5 => 'fas fa-grin-stars text-success'
                                                                                        ];
                                                                                        $labels = [
                                                                                            1 => 'Sehr schlecht',
                                                                                            2 => 'Schlecht',
                                                                                            3 => 'Mittel',
                                                                                            4 => 'Gut',
                                                                                            5 => 'Sehr gut'
                                                                                        ];
                                                                                    @endphp
                                                                                    <i class="{{ $icons[$rating] }}" style="font-size: 1.5rem;"
                                                                                       title="{{ $labels[$rating] }}"></i>
                                                                                @else
                                                                                    <span class="text-muted">-</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-center">
                                                                                @if($teacherAssessment && $teacherAssessment->teacher_rating)
                                                                                    @php
                                                                                        $rating = $teacherAssessment->teacher_rating;
                                                                                        $icons = [
                                                                                            1 => 'fas fa-frown text-danger',
                                                                                            2 => 'fas fa-frown-open text-warning',
                                                                                            3 => 'fas fa-meh text-secondary',
                                                                                            4 => 'fas fa-smile text-info',
                                                                                            5 => 'fas fa-grin-stars text-success'
                                                                                        ];
                                                                                        $labels = [
                                                                                            1 => 'Sehr schlecht',
                                                                                            2 => 'Schlecht',
                                                                                            3 => 'Mittel',
                                                                                            4 => 'Gut',
                                                                                            5 => 'Sehr gut'
                                                                                        ];
                                                                                    @endphp
                                                                                    <i class="{{ $icons[$rating] }}" style="font-size: 1.5rem;"
                                                                                       title="{{ $labels[$rating] }}"></i>
                                                                                @else
                                                                                    <span class="text-muted">-</span>
                                                                                @endif
                                                                            </td>
                                                                            <td>
                                                                                @if($teacherAssessment && $teacherAssessment->comment)
                                                                                    {{ $teacherAssessment->comment }}
                                                                                @else
                                                                                    <span class="text-muted">-</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Stage & History (sichtbar in Schüleransicht) -->
                    <div class="row mb-3" id="stageHistoryRow" style="display:none">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Aktuelle Stufe</h6>
                                    <div id="stageCard" class="mt-2">
                                        <div class="text-muted">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Stufen-Historie</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped" id="historyTable">
                                            <thead class="thead-light"><tr><th style="width:120px">Datum</th><th>Neu</th><th>Vorher</th><th style="width:120px">Geändert von</th></tr></thead>
                                            <tbody id="historyTableBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Keine Daten Nachricht -->
                    <div id="noDataMessage" class="text-center py-5 d-none">
                        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Keine Daten gefunden</h5>
                        <p class="text-muted">Für den gewählten Zeitraum wurden keine Einträge gefunden.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/tablet-scroll-optimization.css?v=20251110') }}">
<style>
.card-title {
    font-size: 0.9rem;
}
.card-text {
    font-size: 1.2rem;
    font-weight: bold;
}
.badge {
    font-size: 0.7rem;
}
#entriesTable td:nth-child(2) {
    max-width: 400px;
    word-wrap: break-word;
}
#tasksTable td:nth-child(3) {
    max-width: 300px;
    word-wrap: break-word;
}
.status-open {
    color: #dc3545;
    font-weight: bold;
}
.status-closed {
    color: #28a745;
}
.priority-high {
    color: #dc3545;
    font-weight: bold;
}
.table-responsive {
    max-height: 70vh;
    overflow-y: auto;
}
/* Chart Styling */
#radarChart, #lineChart {
    max-height: 280px;
}
.challenging-area-item {
    border-left: 3px solid #ffc107;
    padding-left: 12px;
    margin-bottom: 12px;
}
.progress-small {
    height: 8px;
}
</style>
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('/js/tablet-scroll-optimization.js?v=20251110')}}"></script>
<script>
(function(){
    const schuelerID = {{ $schueler->id }};

    // DOM Elemente
    const dateFromInput = document.getElementById('dateFrom');
    const dateToInput = document.getElementById('dateTo');
    const loadDataBtn = document.getElementById('loadDataBtn');
    const exportWordBtn = document.getElementById('exportWordBtn');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const summarySection = document.getElementById('summarySection');
    const dataSection = document.getElementById('dataSection');
    const noDataMessage = document.getElementById('noDataMessage');

    // Neue Filter-Elemente
    const categoryFilter = document.getElementById('categoryFilter');
    const searchNotesInput = document.getElementById('searchNotes');
    const clearSearchBtn = document.getElementById('clearSearch');

    // Quick Date Buttons
    const last7DaysBtn = document.getElementById('last7Days');
    const last30DaysBtn = document.getElementById('last30Days');
    const last90DaysBtn = document.getElementById('last90Days');

    // Data Storage
    let currentData = null;
    let filteredEntries = [];
    // Map für Kategorien (id -> name), damit renderEntries zuverlässig Namen findet
    let categoryMap = new Map();

    // Utils
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    function formatDisplayDate(dateString) {
        return new Date(dateString).toLocaleDateString('de-DE');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(fn, wait){
        let t;
        return function(...args){
            clearTimeout(t);
            t = setTimeout(()=> fn.apply(this, args), wait);
        };
    }

    // Quick Date Functions
    function setDateRange(days) {
        const today = new Date();
        const fromDate = new Date();
        fromDate.setDate(today.getDate() - days);

        dateFromInput.value = formatDate(fromDate);
        dateToInput.value = formatDate(today);
    }

    // Load Data
    function loadData() {
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;

        if (!dateFrom || !dateTo) {
            alert('Bitte wählen Sie einen gültigen Zeitraum aus.');
            return;
        }

        if (new Date(dateFrom) > new Date(dateTo)) {
            alert('Das Startdatum muss vor dem Enddatum liegen.');
            return;
        }

        // UI Updates
        loadingIndicator.classList.remove('d-none');
        summarySection.classList.add('d-none');
        dataSection.classList.add('d-none');
        noDataMessage.classList.add('d-none');

        // Fetch Data
        const params = new URLSearchParams({
            date_from: dateFrom,
            date_to: dateTo
        });

        fetch(`/paed-diary/schueler/${schuelerID}/data?${params}`)
            .then(response => response.json())
            .then(data => {
                currentData = data;
                console.log('Loaded Data:', data);
                populateCategories(data.categories || []);
                applyFiltersAndRender();
            })
            .catch(error => {
                console.error('Error loading data:', error);
                alert('Fehler beim Laden der Daten.');
            })
            .finally(() => {
                loadingIndicator.classList.add('d-none');
            });
    }

    function populateCategories(categories){
        if (!categoryFilter) return;
        // Preserve current selection
        const cur = categoryFilter.value;
        categoryFilter.innerHTML = '<option value="">Alle Kategorien</option>';

        // Build map id -> name from provided categories
        const catMap = new Map();
        if (categories) {
            // If categories is an object mapping id->name
            if (typeof categories === 'object' && !Array.isArray(categories)) {
                Object.keys(categories).forEach(k => {
                    const v = categories[k];
                    // if v is string, use it, else try to read properties
                    if (typeof v === 'string') catMap.set(String(k), v);
                    else if (v && (v.name || v.title || v.label)) catMap.set(String(k), v.name || v.title || v.label);
                });
            } else if (Array.isArray(categories)) {
                categories.forEach(c => {
                    if (!c) return;
                    const id = c.id != null ? String(c.id) : (c.value != null ? String(c.value) : null);
                    const name = c.name || c.title || c.label || c.text || '';
                    if (id) catMap.set(id, name || id);
                });
            }
        }

        // If no categories provided, try to extract from currentData.entries
        if (catMap.size === 0 && currentData && Array.isArray(currentData.entries)) {
            currentData.entries.forEach(e => {
                const cid = e.category_id || (e.category && (e.category.id || e.categoryId)) || e.kategorie_id || e.kategorieId || null;
                const cname = (e.category && (e.category.name || e.category.title)) || e.category_name || e.kategorie || e.kategorie_name || '';
                if (cid) catMap.set(String(cid), cname || ('Kategorie ' + cid));
            });
        }

        // Populate select from map and set global categoryMap
        categoryMap = new Map(catMap);
        Array.from(catMap.entries()).forEach(([id,name]) => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name || id;
            categoryFilter.appendChild(opt);
        });

        if (cur) categoryFilter.value = cur;
    }

    // Apply filters (category + search) and render
    function applyFiltersAndRender(){
        if (!currentData) return;
        const entries = currentData.entries || [];
        const searchTerm = (searchNotesInput && searchNotesInput.value || '').trim().toLowerCase();
        const category = (categoryFilter && categoryFilter.value) || '';

        filteredEntries = entries.filter(e => {
            // Category filter: support e.category_id or e.category?.id or e.category_id as string
            if (category){
                const entryCat = e.category_id || (e.category && e.category.id) || (e.category && e.category_id) || '';
                if (String(entryCat) !== String(category)) return false;
            }
            if (!searchTerm) return true;
            // Search in content and user and (category name)
            const fields = [e.content || '', e.user || '', (e.category && e.category.name) || ''].join(' ').toLowerCase();
            return fields.indexOf(searchTerm) !== -1;
        });

        // Update summary counts based on filtered entries
        document.getElementById('periodText').textContent = `${currentData.period.from} - ${currentData.period.to}`;
        document.getElementById('entriesCount').textContent = filteredEntries.length;
        document.getElementById('tasksCount').textContent = (currentData.tasks || []).length;

        const uniqueDays = new Set(filteredEntries.map(e => e.date));
        document.getElementById('daysWithEntriesCount').textContent = uniqueDays.size;

        // Update Badges
        document.getElementById('entriesBadge').textContent = filteredEntries.length;
        document.getElementById('tasksBadge').textContent = (currentData.tasks || []).length;

        // Determine active columns: if a column has an explicit `active` flag use it, otherwise treat as active
        const allColumns = (currentData.columns || []);
        const endDate = dateToInput && dateToInput.value ? new Date(dateToInput.value) : null;
        const activeColumns = allColumns.filter(c => {
            if (!c) return false;
            // If active flag exists, only include when truthy (supports 1/'1'/true/'true')
            if (typeof c.active !== 'undefined' && c.active !== null) {
                const isActiveFlag = (c.active === 1 || c.active === '1' || c.active === true || c.active === 'true');
                if (!isActiveFlag) return false;
            }

            // If deactivated_from is set and it's on or before the selected end date, consider the column deactivated
            if (c.deactivated_from && endDate) {
                const deact = new Date(c.deactivated_from);
                if (!isNaN(deact.getTime()) && deact <= endDate) return false;
            }

            return true;
        });

        document.getElementById('columnsBadge').textContent = activeColumns.length;

        // Render Tables with filtered entries
        renderEntries(filteredEntries);
        renderTasks(currentData.tasks || []);
        renderColumns(activeColumns, currentData.column_values || {});

        renderStage(currentData.current_stage);
        renderHistory(currentData.stage_history || []);

        summarySection.classList.remove('d-none');
        dataSection.classList.remove('d-none');

        if (filteredEntries.length === 0 && (currentData.tasks || []).length === 0) {
            noDataMessage.classList.remove('d-none');
        } else {
            noDataMessage.classList.add('d-none');
        }
    }

    // Render Entries (zeigt Kategorie als Badge falls vorhanden)
    function renderEntries(entries) {
        const tbody = document.getElementById('entriesTableBody');
        tbody.innerHTML = '';

        if (!entries || entries.length === 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="3" class="text-center text-muted">Keine Einträge</td>`;
            tbody.appendChild(tr);
            return;
        }

        entries.forEach(entry => {
            const row = document.createElement('tr');

            // determine category name robustly
            let categoryName = '';
            if (entry.category) categoryName = entry.category || '';

            // Wenn noch keine Kategorie-Name, versuche Lookup von ID in categoryMap
            if (!categoryName) {
                const cid = entry.category_id || (entry.category && (entry.category.id || entry.categoryId)) || entry.kategorie_id || entry.kategorieId || null;
                if (cid && categoryMap.has(String(cid))) categoryName = categoryMap.get(String(cid));
            }


            row.innerHTML = `
                <td class="text-center">${formatDisplayDate(entry.date)}</td>
                <td>${escapeHtml(entry.content)}</td>
                <td >${escapeHtml(categoryName)}</td>
                <td class="text-center">${escapeHtml(entry.user || '')}</td>
            `;
            tbody.appendChild(row);
        });
    }

    // Render Tasks
    function renderTasks(tasks) {
        const tbody = document.getElementById('tasksTableBody');
        tbody.innerHTML = '';

        tasks.forEach(task => {
            const row = document.createElement('tr');
            const statusClass = task.status === 'open' ? 'status-open' : 'status-closed';
            const priorityClass = task.highlighted ? 'priority-high' : '';

            row.innerHTML = `
                <td class="text-center">${escapeHtml(task.created_at)}</td>
                <td>${escapeHtml(task.title)}</td>
                <td>${escapeHtml(task.description || '')}</td>
                <td class="text-center">${escapeHtml(task.due_date || '')}</td>
                <td class="text-center"><span class="${statusClass}">${task.status === 'open' ? 'Offen' : 'Geschlossen'}</span></td>
                <td class="text-center"><span class="${priorityClass}">${task.highlighted ? 'Hoch' : 'Normal'}</span></td>
            `;
            tbody.appendChild(row);
        });
    }

    // Render Columns
    function renderColumns(columns, columnValues) {
        const headers = document.getElementById('columnHeaders');
        const tbody = document.getElementById('columnsTableBody');

        // Always ensure the first header cell for the date exists
        headers.innerHTML = '<th style="width: 100px;">Datum</th>';
        tbody.innerHTML = '';

        if (columns.length === 0) {
            // If no columns, keep the date header and show a placeholder
            headers.innerHTML = '<th style="width:100px;">Datum</th><th>Keine Spalten konfiguriert</th>';
            return;
        }

        // Create headers
        // Nur aktive Spalten werden übergeben; hier werden die Header für diese Spalten angelegt
        columns.forEach(column => {
            const th = document.createElement('th');
            th.textContent = column.name || '';
            th.style.minWidth = '100px';
            headers.appendChild(th);
        });

        // Prepare counts for boolean "true" values per column
        const trueCounts = {};
        columns.forEach(column => { trueCounts[column.id] = 0; });

         // Group column values by date
         const valuesByDate = {};
         Object.keys(columnValues).forEach(date => {
             valuesByDate[date] = columnValues[date];
         });

         // Create rows for each date that has column values
         const sortedDates = Object.keys(valuesByDate).sort();
         sortedDates.forEach(date => {
             const row = document.createElement('tr');

             // Date column
             const dateCell = document.createElement('td');
             dateCell.textContent = formatDisplayDate(date);
             dateCell.className = 'text-center';
             row.appendChild(dateCell);

             // Value columns
             columns.forEach(column => {
                 const cell = document.createElement('td');
                 const value = valuesByDate[date] ? valuesByDate[date][column.id] : null;

                 if (value) {
                     if (column.type === 'boolean') {
                         const isTrue = (value.value === '1' || value.value === 1 || value.value === true || value.value === 'true');
                         cell.innerHTML = isTrue ?
                            '<span class="badge badge-success">Ja</span>' :
                            '<span class="badge badge-secondary">Nein</span>';
                         if (isTrue) {
                            trueCounts[column.id] = (trueCounts[column.id] || 0) + 1;
                         }
                     } else {
                         cell.textContent = value.value;
                     }
                 } else {
                     cell.innerHTML = '<span class="text-muted">-</span>';
                 }

                 cell.className = '';
                 row.appendChild(cell);
             });

             tbody.appendChild(row);
         });

         if (sortedDates.length === 0) {
             const row = document.createElement('tr');
             row.innerHTML = `<td colspan="${columns.length + 1}" class="text-center text-muted">Keine Spaltenwerte im gewählten Zeitraum</td>`;
             tbody.appendChild(row);
         }

        // Append a counts row under the values: Anzahl (Ja) pro Spalte
        const countsRow = document.createElement('tr');
        const countsLabelCell = document.createElement('td');
        countsLabelCell.className = 'text-center font-weight-bold';
        countsLabelCell.textContent = 'Anzahl (Ja)';
        countsRow.appendChild(countsLabelCell);
        columns.forEach(column => {
            const ccell = document.createElement('td');
            ccell.className = ' font-weight-bold';
            if (column.type === 'boolean') {
                ccell.textContent = String(trueCounts[column.id] || 0);
            } else {
                ccell.innerHTML = '<span class="text-muted">-</span>';
            }
            countsRow.appendChild(ccell);
        });
        tbody.appendChild(countsRow);
     }

    // Export Word
    function exportWord() {
        if (!currentData) {
            alert('Bitte laden Sie zuerst Daten.');
            return;
        }

        const params = new URLSearchParams({
            date_from: dateFromInput.value,
            date_to: dateToInput.value
        });

        // include filters
        if (categoryFilter && categoryFilter.value) params.append('category', categoryFilter.value);
        if (searchNotesInput && searchNotesInput.value.trim()) params.append('search', searchNotesInput.value.trim());

        window.location.href = `/paed-diary/schueler/${schuelerID}/export/word?${params}`;
    }

    // Render current stage
    function renderStage(stage){
        const container = document.getElementById('stageCard');
        const row = document.getElementById('stageHistoryRow');
        if (!container) return;
        container.innerHTML = '';
        if (!stage){
            container.innerHTML = '<div class="text-muted">Keine Stufe zugewiesen</div>';
            row.style.display = 'none';
            return;
        }
        row.style.display = '';
        const box = document.createElement('div');
        box.className = 'd-flex align-items-center justify-content-center';
        // image or symbol
        if (stage.image_url){
            const img = document.createElement('img');
            img.src = stage.image_url;
            img.alt = stage.name;
            img.style.width = '56px'; img.style.height = '56px'; img.style.objectFit = 'cover'; img.style.borderRadius = '6px'; img.className = 'mr-2';
            box.appendChild(img);
        } else if (stage.symbol){
            const i = document.createElement('i');
            i.className = stage.symbol + ' fa-2x mr-2';
            i.setAttribute('aria-hidden','true');
            box.appendChild(i);
        } else {
            const placeholder = document.createElement('div');
            placeholder.style.width='56px'; placeholder.style.height='56px'; placeholder.style.background='#f0f0f0'; placeholder.style.borderRadius='6px'; placeholder.className='mr-2';
            box.appendChild(placeholder);
        }
        const txt = document.createElement('div');
        txt.innerHTML = `<div><strong>${escapeHtml(stage.name)}</strong></div>`;
        box.appendChild(txt);
        container.appendChild(box);
    }

    // Render grading history
    function renderHistory(history){
        const tbody = document.getElementById('historyTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!history || history.length === 0){
            // hide row if no history and no current stage
            // keep stage card visible if stage exists; handled elsewhere
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="4" class="text-center text-muted">Keine Historie vorhanden</td>`;
            tbody.appendChild(tr);
            return;
        }
        // history items assumed to be in chronological order — show newest first
        const sorted = history.slice().sort((a,b)=> new Date(b.at) - new Date(a.at));
        sorted.forEach(h => {
            const tr = document.createElement('tr');
            const changer = h.changed_by_name || h.changed_by || '';
            tr.innerHTML = `
                <td class="text-center">${formatDisplayDate(h.at)}</td>
                <td>${escapeHtml(h.stage_name || '-')}</td>
                <td>${escapeHtml(h.previous_stage_name || '-')}</td>
                <td class="text-center">${escapeHtml(changer)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Event Listeners
    loadDataBtn.addEventListener('click', loadData);
    exportWordBtn.addEventListener('click', exportWord);

    last7DaysBtn.addEventListener('click', () => {
        setDateRange(7);
        loadData();
    });

    last30DaysBtn.addEventListener('click', () => {
        setDateRange(30);
        loadData();
    });

    last90DaysBtn.addEventListener('click', () => {
        setDateRange(90);
        loadData();
    });

    // Enter key on date inputs
    dateFromInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadData();
    });

    dateToInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadData();
    });

    // Category / Search listeners
    if (categoryFilter) categoryFilter.addEventListener('change', () => applyFiltersAndRender());
    if (searchNotesInput) searchNotesInput.addEventListener('input', debounce(() => applyFiltersAndRender(), 300));
    if (clearSearchBtn) clearSearchBtn.addEventListener('click', () => { if (searchNotesInput) { searchNotesInput.value = ''; applyFiltersAndRender(); } });

    // Initial load
    loadData();

    // ===== Graduierungs-Visualisierung =====
    // Daten für Graduierungen aus Blade-Template
    const gradingSessions = @json($gradingSessions ?? []);

    // Globale Variable für das Chart-Objekt
    let lineChartInstance = null;

    if (gradingSessions && gradingSessions.length > 0) {
        initializeGraduationCharts();
    }

    function initializeGraduationCharts() {
        // Letzte Session für Radar-Chart
        const latestSession = gradingSessions[gradingSessions.length - 1];

        // Extrahiere alle einzigartigen Fragen aus allen Sessions
        const allQuestionsMap = new Map();
        gradingSessions.forEach(session => {
            const sessionQuestions = session.grading_system?.questions || [];
            sessionQuestions.forEach(q => {
                if (!allQuestionsMap.has(q.id)) {
                    allQuestionsMap.set(q.id, q);
                }
            });
        });
        const allQuestions = Array.from(allQuestionsMap.values());

        // Extrahiere Fragen aus letzter Session für Radar Chart
        const questions = latestSession.grading_system?.questions || [];

        // Bereite Daten für Radar-Chart vor (letzte Session)
        const radarLabels = questions.map(q => truncateText(q.question, 30));
        const studentRatings = [];
        const teacherRatings = [];

        questions.forEach(question => {
            // Finde Student Answer
            const studentAnswer = latestSession.student_answers?.find(
                sa => sa.schueler_id === {{ $schueler->id }} && sa.question_id === question.id
            );
            studentRatings.push(studentAnswer?.self_rating || 0);

            // Finde Teacher Assessment
            const teacherAssessment = latestSession.teacher_assessments?.find(
                ta => ta.schueler_id === {{ $schueler->id }} && ta.question_id === question.id
            );
            teacherRatings.push(teacherAssessment?.teacher_rating || 0);
        });

        // Erstelle Radar Chart
        const radarCtx = document.getElementById('radarChart');
        if (radarCtx) {
            new Chart(radarCtx, {
                type: 'radar',
                data: {
                    labels: radarLabels,
                    datasets: [{
                        label: 'Schüler-Einschätzung',
                        data: studentRatings,
                        borderColor: 'rgba(23, 162, 184, 1)',
                        backgroundColor: 'rgba(23, 162, 184, 0.2)',
                        pointBackgroundColor: 'rgba(23, 162, 184, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(23, 162, 184, 1)'
                    }, {
                        label: 'Lehrer-Bewertung',
                        data: teacherRatings,
                        borderColor: 'rgba(40, 167, 69, 1)',
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(40, 167, 69, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 5,
                            min: 0,
                            ticks: {
                                stepSize: 1
                            },
                            pointLabels: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const labels = ['', 'Sehr schlecht', 'Schlecht', 'Mittel', 'Gut', 'Sehr gut'];
                                    return context.dataset.label + ': ' + labels[context.parsed.r] + ' (' + context.parsed.r + ')';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Fülle das Dropdown mit allen Fragen
        populateQuestionSelector(allQuestions);

        // Erstelle das Linien-Chart (Entwicklung über Zeit)
        createLineChart('average', allQuestions);

        // Analysiere schwierige Bereiche (Fragen mit niedrigen Bewertungen)
        analyzeChallenges(questions, latestSession);
    }


    function populateQuestionSelector(questions) {
        const selector = document.getElementById('lineChartQuestionSelector');
        if (!selector) {
            console.error('lineChartQuestionSelector nicht gefunden');
            return;
        }

        // Behalte die "Durchschnitt"-Option
        selector.innerHTML = '<option value="average">Durchschnitt aller Fragen</option>';

        // Füge alle Fragen hinzu
        questions.forEach((question) => {
            const option = document.createElement('option');
            option.value = question.id;
            option.textContent = truncateText(question.question, 50);
            selector.appendChild(option);
        });


        // Event-Listener für Änderungen
        selector.addEventListener('change', function() {
            const selectedValue = this.value;
            createLineChart(selectedValue, questions);
        });
    }

    function createLineChart(selectedOption, allQuestions) {
        // Bereite Daten vor
        const sessionDates = gradingSessions.map(s => {
            const date = new Date(s.completed_at);
            return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
        });

        let studentRatingsData = [];
        let teacherRatingsData = [];
        let chartTitle = 'Durchschnittliche Bewertung über alle Fragen';

        if (selectedOption === 'average') {
            // Durchschnitt aller Fragen
            gradingSessions.forEach(session => {
                const questions = session.grading_system?.questions || [];
                let studentSum = 0, studentCount = 0;
                let teacherSum = 0, teacherCount = 0;

                questions.forEach(question => {
                    const studentAnswer = session.student_answers?.find(
                        sa => sa.schueler_id === {{ $schueler->id }} && sa.question_id === question.id
                    );
                    if (studentAnswer && studentAnswer.self_rating) {
                        studentSum += studentAnswer.self_rating;
                        studentCount++;
                    }

                    const teacherAssessment = session.teacher_assessments?.find(
                        ta => ta.schueler_id === {{ $schueler->id }} && ta.question_id === question.id
                    );
                    if (teacherAssessment && teacherAssessment.teacher_rating) {
                        teacherSum += teacherAssessment.teacher_rating;
                        teacherCount++;
                    }
                });

                studentRatingsData.push(studentCount > 0 ? parseFloat((studentSum / studentCount).toFixed(2)) : null);
                teacherRatingsData.push(teacherCount > 0 ? parseFloat((teacherSum / teacherCount).toFixed(2)) : null);
            });
        } else {
            // Spezifische Frage
            const selectedQuestionId = parseInt(selectedOption);
            const selectedQuestion = allQuestions.find(q => q.id === selectedQuestionId);

            if (selectedQuestion) {
                chartTitle = truncateText(selectedQuestion.question, 60);

                gradingSessions.forEach(session => {
                    const studentAnswer = session.student_answers?.find(
                        sa => sa.schueler_id === {{ $schueler->id }} && sa.question_id === selectedQuestionId
                    );
                    studentRatingsData.push(studentAnswer?.self_rating || null);

                    const teacherAssessment = session.teacher_assessments?.find(
                        ta => ta.schueler_id === {{ $schueler->id }} && ta.question_id === selectedQuestionId
                    );
                    teacherRatingsData.push(teacherAssessment?.teacher_rating || null);
                });
            }
        }

        // Aktualisiere die Beschreibung
        const descriptionElement = document.getElementById('lineChartDescription');
        if (descriptionElement) {
            descriptionElement.textContent = chartTitle;
        }

        // Erstelle oder aktualisiere das Chart
        const lineCtx = document.getElementById('lineChart');
        if (lineCtx) {
            // Zerstöre das alte Chart, falls vorhanden
            if (lineChartInstance) {
                lineChartInstance.destroy();
            }

            // Erstelle neues Chart
            lineChartInstance = new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: sessionDates,
                    datasets: [{
                        label: 'Schüler-Einschätzung',
                        data: studentRatingsData,
                        borderColor: 'rgba(23, 162, 184, 1)',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        tension: 0.4,
                        fill: true,
                        spanGaps: true
                    }, {
                        label: 'Lehrer-Bewertung',
                        data: teacherRatingsData,
                        borderColor: 'rgba(40, 167, 69, 1)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true,
                        spanGaps: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            min: 0,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const labels = ['', 'Sehr schlecht', 'Schlecht', 'Mittel', 'Gut', 'Sehr gut'];
                                    const value = context.parsed.y;
                                    if (value === null || value === 0) {
                                        return context.dataset.label + ': Keine Bewertung';
                                    }
                                    const labelText = Math.round(value) >= 1 && Math.round(value) <= 5 ? labels[Math.round(value)] : '';
                                    return context.dataset.label + ': ' + value + (labelText ? ' (' + labelText + ')' : '');
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    function analyzeChallenges(questions, latestSession) {
        const challengeData = [];

        questions.forEach(question => {
            const studentAnswer = latestSession.student_answers?.find(
                sa => sa.schueler_id === {{ $schueler->id }} && sa.question_id === question.id
            );
            const teacherAssessment = latestSession.teacher_assessments?.find(
                ta => ta.schueler_id === {{ $schueler->id }} && ta.question_id === question.id
            );

            const studentRating = studentAnswer?.self_rating || 0;
            const teacherRating = teacherAssessment?.teacher_rating || 0;
            const avgRating = (studentRating + teacherRating) / 2;

            // Nur Fragen mit durchschnittlicher Bewertung unter 3 als "herausfordernd" markieren
            if (avgRating > 0 && avgRating < 3) {
                challengeData.push({
                    question: question.question,
                    studentRating: studentRating,
                    teacherRating: teacherRating,
                    avgRating: avgRating,
                    comment: teacherAssessment?.comment || ''
                });
            }
        });

        // Sortiere nach niedrigster Durchschnittsbewertung
        challengeData.sort((a, b) => a.avgRating - b.avgRating);

        // Rendere Entwicklungsbereiche
        const container = document.getElementById('challengingAreas');
        if (container) {
            if (challengeData.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle"></i> <strong>Ausgezeichnet!</strong>
                        Alle Bereiche zeigen gute bis sehr gute Bewertungen. Weiter so!
                    </div>
                `;
            } else {
                let html = '<div class="row">';
                challengeData.forEach((item, index) => {
                    const ratingLabels = ['', 'Sehr schlecht', 'Schlecht', 'Mittel', 'Gut', 'Sehr gut'];
                    const progressPercent = (item.avgRating / 5) * 100;
                    const progressColor = item.avgRating < 2 ? 'bg-danger' : 'bg-warning';

                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="challenging-area-item">
                                <h6 class="font-weight-bold mb-2">
                                    <span class="badge badge-warning">${index + 1}</span> ${escapeHtml(item.question)}
                                </h6>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">Schüler:</small>
                                        <strong>${ratingLabels[item.studentRating] || '-'}</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Lehrer:</small>
                                        <strong>${ratingLabels[item.teacherRating] || '-'}</strong>
                                    </div>
                                </div>
                                <div class="progress progress-small mb-2">
                                    <div class="progress-bar ${progressColor}" role="progressbar"
                                         style="width: ${progressPercent}%"
                                         aria-valuenow="${item.avgRating}" aria-valuemin="0" aria-valuemax="5">
                                    </div>
                                </div>
                                ${item.comment ? `<small class="text-muted"><i class="fas fa-comment"></i> ${escapeHtml(item.comment)}</small>` : ''}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            }
        }
    }

    function truncateText(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
})();
</script>
@endpush
