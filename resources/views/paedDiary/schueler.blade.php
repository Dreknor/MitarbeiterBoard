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
                        </ul>

                        <div class="tab-content" id="viewTabContent">
                            <!-- Einträge Tab -->
                            <div class="tab-pane fade show active" id="entries" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped" id="entriesTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 100px;">Datum</th>
                                                <th>Notiz</th>
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
                                            <tr>
                                                <th style="width: 100px;">Datum</th>
                                                <th id="columnHeaders"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="columnsTableBody"></tbody>
                                    </table>
                                </div>
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
</style>
@endpush

@push('js')
<script>
(function(){
    const csrf = document.querySelector('meta[name=csrf-token]').content;
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

    // Quick Date Buttons
    const last7DaysBtn = document.getElementById('last7Days');
    const last30DaysBtn = document.getElementById('last30Days');
    const last90DaysBtn = document.getElementById('last90Days');

    // Data Storage
    let currentData = null;

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
                renderData(data);
            })
            .catch(error => {
                console.error('Error loading data:', error);
                alert('Fehler beim Laden der Daten.');
            })
            .finally(() => {
                loadingIndicator.classList.add('d-none');
            });
    }

    // Render Data
    function renderData(data) {
        if (!data.entries.length && !data.tasks.length) {
            noDataMessage.classList.remove('d-none');
            return;
        }

        // Update Summary
        document.getElementById('periodText').textContent = `${data.period.from} - ${data.period.to}`;
        document.getElementById('entriesCount').textContent = data.entries.length;
        document.getElementById('tasksCount').textContent = data.tasks.length;

        // Calculate days with entries
        const uniqueDays = new Set(data.entries.map(e => e.date));
        document.getElementById('daysWithEntriesCount').textContent = uniqueDays.size;

        // Update Badges
        document.getElementById('entriesBadge').textContent = data.entries.length;
        document.getElementById('tasksBadge').textContent = data.tasks.length;
        document.getElementById('columnsBadge').textContent = data.columns.length;

        // Render Tables
        renderEntries(data.entries);
        renderTasks(data.tasks);
        renderColumns(data.columns, data.column_values);

        // Render current stage and history
        renderStage(data.current_stage);
        renderHistory(data.stage_history);

        // Show Sections
        summarySection.classList.remove('d-none');
        dataSection.classList.remove('d-none');
    }

    // Render Entries
    function renderEntries(entries) {
        const tbody = document.getElementById('entriesTableBody');
        tbody.innerHTML = '';

        entries.forEach(entry => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="text-center">${formatDisplayDate(entry.date)}</td>
                <td>${escapeHtml(entry.content)}</td>
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

        headers.innerHTML = '';
        tbody.innerHTML = '';

        if (columns.length === 0) {
            headers.innerHTML = '<th>Keine Spalten konfiguriert</th>';
            return;
        }

        // Create headers
        columns.forEach(column => {
            const th = document.createElement('th');
            th.textContent = column.name;
            th.style.minWidth = '100px';
            headers.appendChild(th);
        });

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
                const value = valuesByDate[date][column.id];

                if (value) {
                    if (column.type === 'boolean') {
                        cell.innerHTML = value.value === '1' ?
                            '<span class="badge badge-success">Ja</span>' :
                            '<span class="badge badge-secondary">Nein</span>';
                    } else {
                        cell.textContent = value.value;
                    }
                } else {
                    cell.innerHTML = '<span class="text-muted">-</span>';
                }

                cell.className = 'text-center';
                row.appendChild(cell);
            });

            tbody.appendChild(row);
        });

        if (sortedDates.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="${columns.length + 1}" class="text-center text-muted">Keine Spaltenwerte im gewählten Zeitraum</td>`;
            tbody.appendChild(row);
        }
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
        const row = document.getElementById('stageHistoryRow');
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

    // Initial load
    loadData();
})();
</script>
@endpush
