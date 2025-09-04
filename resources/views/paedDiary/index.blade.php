@extends('layouts.app')

@section('content')
<div class="container-fluid" id="paed-diary-app">
    <div class="row">
        <div class="col-12 mb-2" id="noteEditorWrapper">
            <div class="card shadow-sm d-none" id="noteEditorCard">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <strong class="small mb-0" id="noteEditorTitle">Notiz erfassen</strong>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" id="noteEditorCancel" title="Schließen">✕</button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <form id="noteForm" class="mb-0">
                        <input type="hidden" name="entry_id" id="noteEntryId" value="">
                        <input type="hidden" name="klasse_id" id="noteKlasseId" value="{{$klasse->id}}">
                        <div class="form-row">
                            <div class="col-md-2 mb-2">
                                <label class="small mb-1" for="noteDate">Datum</label>
                                <input type="date" name="date" id="noteDate" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-10 mb-2">
                                <label class="small mb-1">Schüler</label>
                                <div id="noteStudents" class="border rounded p-2 bg-light" style="max-height:112px; overflow:auto; font-size:0.75rem;"></div>
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small mb-1" for="noteContent">Notiz</label>
                            <textarea name="content" id="noteContent" rows="3" class="form-control form-control-sm" required></textarea>
                        </div>
                        <div class="d-flex align-items-center flex-wrap">
                            <button type="submit" class="btn btn-primary btn-sm mr-2" id="noteSaveBtn">Speichern</button>
                            <button type="button" class="btn btn-danger btn-sm mr-2 d-none" id="noteDeleteBtn">Löschen</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="noteClearBtn">Neu</button>
                            <span class="text-muted small ml-3" id="noteStatus"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Neue Aufgaben Card (ersetzt das ehemalige Modal) -->
        <div class="col-12 mb-2" id="taskEditorWrapper">
            <div class="card shadow-sm d-none" id="taskEditorCard">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <strong class="small mb-0" id="taskEditorTitle">Aufgabe erfassen</strong>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" id="taskEditorCancel" title="Schließen">✕</button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <form id="taskForm" class="mb-0">
                        <input type="hidden" name="klasse_id" id="taskKlasseId" value="{{$klasse->id}}">
                        <div class="form-row">
                            <div class="col-md-12 mb-2">
                                <label class="small mb-1">Schüler</label>
                                <div id="taskStudents" class="border rounded p-2 bg-light" style="max-height:112px; overflow:auto; font-size:0.75rem;"></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-4 mb-2">
                                <label class="small mb-1">Titel</label>
                                <input type="text" name="title" class="form-control form-control-sm" required maxlength="100">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="small mb-1">Fällig</label>
                                <input type="date" name="due_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4 mb-2 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="highlighted" id="taskHighlighted" checked value="1">
                                    <label class="form-check-label small" for="taskHighlighted">Hervorheben</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-12 mb-2">
                                <label class="small mb-1">Beschreibung</label>
                                <textarea name="description" class="form-control form-control-sm" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap">
                            <button type="submit" class="btn btn-primary btn-sm mr-2" id="taskSaveBtn">Speichern</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="taskClearBtn">Neu</button>
                            <span class="text-muted small ml-3" id="taskStatus"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-wrap">
                        <h5 class="mb-0 mr-3">Pädagogisches Tagebuch</h5>
                        <div class="form-inline mr-3">
                            <label class="mr-2 mb-0">Klasse</label>
                            <select id="klasseSelect" class="form-control form-control-sm">
                                @foreach($klassen as $k)
                                    <option value="{{$k->id}}" @if($k->id === $klasse->id) selected @endif>{{$k->name}} ({{$k->schueler_count}})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="btn-group btn-group-sm mr-2" role="group">
                            <button class="btn btn-outline-secondary" id="prevWeek" title="Vorherige Woche">&laquo;</button>
                            <button class="btn btn-outline-secondary" id="todayWeek" title="Aktuelle Woche">Heute</button>
                            <button class="btn btn-outline-secondary" id="nextWeek" title="Nächste Woche">&raquo;</button>
                        </div>
                        <span id="weekLabel" class="font-weight-bold small"></span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center">
                        <button class="btn btn-sm btn-outline-secondary mb-1 mr-2" id="manageColumnsBtn" title="Spalten verwalten"><i class="fas fa-columns"></i> Spalten</button>
                        <a id="exportCsvBtn" class="btn btn-sm btn-outline-primary mb-1 mr-2" title="CSV Export"><i class="fas fa-file-csv"></i></a>
                        <button class="btn btn-sm btn-success mb-1 mr-2" id="openTaskModal">Aufgabe</button>
                        <button class="btn btn-sm btn-info mb-1" id="openNoteInline">Neue Notiz</button>
                        <button class="btn btn-sm btn-outline-secondary mb-1 mr-2" id="toggleSearchCard" title="Suche & Filter"><i class="fas fa-search"></i> Suche</button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive" style="max-height:70vh;">
                        <table class="table table-sm table-bordered mb-0" id="diaryTable">
                            <thead class="thead-light" id="diaryHead"></thead>
                            <tbody id="diaryBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 order-lg-2" id="tasksPanel" style="display:none;">
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold small">Offene Aufgaben</span>
                    <button class="btn btn-link btn-sm p-0" id="refreshTasks" title="Aktualisieren"><i class="fas fa-sync"></i></button>
                </div>
                <div class="card-body p-2" id="tasksList" style="max-height:50vh; overflow:auto;"></div>
            </div>
        </div>
        <!-- Columns Management Card (wieder eingefügt) -->
        <div class="col-12 mb-3 d-none" id="columnsCardWrapper">
            <div class="card shadow-sm" id="columnsCard">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong class="small mb-0">Spalten verwalten</strong>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" id="columnsCloseBtn" title="Schließen">✕</button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <div id="columnsFeedback" class="mb-2 small"></div>
                    <div id="columnsList" class="mb-2 d-flex flex-wrap align-items-start"></div>
                    <form id="addColumnForm" class="form-inline small mb-2">
                        <input type="text" name="name" class="form-control form-control-sm mr-1 mb-1" placeholder="Name" required maxlength="50">
                        <select name="type" class="form-control form-control-sm mr-1 mb-1">
                            <option value="boolean">Ja/Nein</option>
                        </select>
                        <button class="btn btn-sm btn-primary mb-1">Hinzufügen</button>
                    </form>
                    <div class="text-muted small">Löschen deaktiviert die Spalte ab der aktuellen Woche (Werte ab dieser Woche werden entfernt). Historische Wochen bleiben erhalten.</div>
                </div>
            </div>
        </div>
        <!-- Such- / Filter Card -->
        <div class="col-12 mb-2 d-none" id="searchCardWrapper">
            <div class="card shadow-sm" id="searchCard">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <strong class="small mb-0">Suche & Filter</strong>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" id="searchCardClose" title="Schließen">✕</button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <form id="searchForm" class="small mb-2">
                        <div class="form-row">
                            <div class="col-md-4 mb-2">
                                <label class="small mb-1">Suchbegriff</label>
                                <input type="text" name="q" id="searchQ" class="form-control form-control-sm" placeholder="Text in Notizen...">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="small mb-1">Stufe</label>
                                <select id="searchStage" class="form-control form-control-sm">
                                    <option value="">(Alle / keine Auswahl)</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="small mb-1">Von</label>
                                <input type="date" name="date_from" id="searchDateFrom" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="small mb-1">Bis</label>
                                <input type="date" name="date_to" id="searchDateTo" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <div class="btn-group btn-group-sm flex-wrap" role="group">
                                    <button type="button" class="btn btn-outline-secondary" id="searchSetSchoolYear">Schuljahr</button>
                                    <button type="submit" class="btn btn-primary" id="searchRunBtn">Suchen</button>
                                    <button type="button" class="btn btn-outline-secondary" id="searchResetBtn">Reset</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div id="searchStatus" class="text-muted small mb-1"></div>
                    <div id="searchResults" class="small" style="max-height:45vh; overflow:auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Entferntes Modal: Aufgabe Modal wurde durch Card ersetzt -->
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/paedDiary.css') }}">
<style>
.stage-badge { display:inline-flex; align-items:center; gap:.4rem; padding:.12rem .4rem; border-radius:.35rem; background:#fff; border:1px solid #e9ecef; }
.stage-badge img { box-shadow:0 0 0 1px rgba(0,0,0,0.03) inset; }
.stage-card-row .grading-card { transition:transform .08s ease, box-shadow .08s ease; }
.stage-cards-wrapper .grading-card:hover { transform:translateY(-4px); box-shadow:0 6px 18px rgba(0,0,0,0.06); }
.stage-cards-wrapper { background: #f8f9fa; border-radius:6px; }
.stage-badge-container { vertical-align: middle; }
</style>
@endpush

@push('js')
<script src="{{ asset('js/paedDiary.js') }}"></script>
@endpush
