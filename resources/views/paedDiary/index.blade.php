@extends('layouts.app')

@section('content')
<div class="container-fluid" id="paed-diary-app">
    <div class="row">
        <!-- Gruppen Management Modal -->
        <div class="modal fade" id="groupModal" tabindex="-1" role="dialog">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header py-2">
                <h6 class="modal-title">Klassenkopplungen verwalten</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              </div>
              <div class="modal-body p-2">
                <div id="groupFeedback" class="small mb-2"></div>
                <form id="groupForm" class="border rounded p-2 mb-3">
                    <input type="hidden" name="group_id" id="groupId" value="">
                    <div class="form-row">
                        <div class="col-md-4 mb-2">
                            <label class="small mb-1">Name</label>
                            <input type="text" name="name" id="groupName" class="form-control form-control-sm" maxlength="80" required>
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="small mb-1">Klassen wählen (mind. 2)</label>
                            <!-- Scroll entfernt: max-height/overflow entfernt -->
                            <div class="border rounded p-2 bg-light" style="font-size:0.75rem;" id="groupKlassenBox">
                                @foreach($klassen as $k)
                                    <div class="form-check-inline mb-1">
                                        <input class="form-check-input" type="checkbox" id="grp_cls_{{$k->id}}" value="{{$k->id}}" name="klasse_ids[]">
                                        <label class="form-check-label" for="grp_cls_{{$k->id}}">{{$k->name}}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center">
                        <button class="btn btn-primary btn-sm mr-2" id="groupSaveBtn">Speichern</button>
                        <button class="btn btn-secondary btn-sm mr-2 d-none" id="groupCancelEdit" type="button">Abbrechen</button>
                        <span id="groupStatus" class="small text-muted"></span>
                    </div>
                </form>
                <h6 class="small font-weight-bold">Bestehende Kopplungen</h6>
                <div id="groupsList" class="small"></div>
              </div>
              <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Schließen</button>
              </div>
            </div>
          </div>
        </div>
        <!-- Ende Gruppen Management Modal -->

        <!-- Graduierungsstufen Modal -->
        <div class="modal fade" id="stagingModal" tabindex="-1" role="dialog">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header py-2">
                <h6 class="modal-title">Graduierungsstufe ändern</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              </div>
              <form id="stagingForm">
                <div class="modal-body p-2">
                    <div id="stagingFeedback" class="small mb-2"></div>
                    <input type="hidden" name="schueler_id" id="stagingSchueler" value="">
                    <div class="form-group mb-2">
                        <label class="small mb-1">Schüler</label>
                        <div class="form-control-plaintext small" id="stagingSchuelerName"></div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-1">Aktuelle Stufe</label>
                        <div class="form-control-plaintext small" id="stagingCurrentStage"></div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-1">Neue Stufe</label>
                        <select name="grading_stage_id" id="stagingNewStage" class="form-control form-control-sm">
                            <option value="">-- Keine Stufe --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="submit" class="btn btn-primary btn-sm">Ändern</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Abbrechen</button>
                    <span class="text-muted small ml-3" id="stagingStatus"></span>
                </div>
              </form>
            </div>
          </div>
        </div>

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
                                <!-- Scroll entfernt: max-height/overflow entfernt -->
                                <div id="noteStudents" class="border rounded p-2 bg-light" style="font-size:0.75rem;"></div>
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small mb-1" for="noteContent">Notiz</label>
                            <textarea name="content" id="noteContent" rows="3" class="form-control form-control-sm" required></textarea>
                        </div>
                        <div class="form-group mb-2">
                            <input type="checkbox" name="completed" id="noteCompleted" value="1" checked class="align-middle" style="vertical-align:middle;">
                            <label for="noteCompleted" class="small mb-0 align-middle" style="vertical-align:middle;">Erledigt</label>
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
        <!-- Columns Management Card (neu) -->
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
                        <!-- Category controls: choose existing or enter new -->
                        <select name="category_select" class="form-control form-control-sm mr-1 mb-1">
                            <option value="">-- Keine / Neue --</option>
                        </select>
                        <input type="text" name="new_category" class="form-control form-control-sm mr-1 mb-1" placeholder="Neue Kategorie (optional)" />
                        <select name="type" class="form-control form-control-sm mr-1 mb-1">
                            <option value="boolean">Ja/Nein</option>
                        </select>
                        <button class="btn btn-sm btn-primary mb-1">Hinzufügen</button>
                    </form>
                    <div class="text-muted small">Löschen deaktiviert die Spalte ab der aktuellen Woche (Werte ab dieser Woche werden entfernt). Historische Wochen bleiben erhalten.</div>
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
                        <div class="form-inline mr-3">
                            <label class="mr-2 mb-0">Kopplung</label>
                            <select id="groupSelect" class="form-control form-control-sm">
                                <option value="">-- Gruppe --</option>
                                @foreach($groups as $g)
                                    <option value="{{$g->id}}" @if(isset($selectedGroup) && $selectedGroup && $selectedGroup->id === $g->id) selected @endif>{{$g->name}}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-outline-secondary btn-sm ml-2" id="manageGroupsBtn" type="button" title="Kopplungen verwalten"><i class="fas fa-object-group"></i></button>
                        </div>
                        <div class="btn-group btn-group-sm mr-2" role="group">
                            <button class="btn btn-outline-secondary" id="prevWeek" title="Vorherige Woche">&laquo;</button>
                            <button class="btn btn-outline-secondary" id="todayWeek" title="Aktuelle Woche">Heute</button>
                            <button class="btn btn-outline-secondary" id="nextWeek" title="Nächste Woche">&raquo;</button>
                        </div>
                        <span id="weekLabel" class="font-weight-bold small"></span>
                        <span id="modeBadge" class="badge badge-info ml-2 d-none">Gruppenmodus</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center">
                        <button class="btn btn-sm btn-outline-secondary mb-1 mr-2" id="manageColumnsBtn" title="Spalten verwalten"><i class="fas fa-columns"></i> Spalten</button>
                        <a id="exportCsvBtn" class="btn btn-sm btn-outline-primary mb-1 mr-2" title="CSV Export"><i class="fas fa-file-csv"></i></a>
                        <button class="btn btn-sm btn-warning mb-1 mr-2" id="openAppointmentModal"><i class="fas fa-calendar-alt"></i> Termin</button>
                        <button class="btn btn-sm btn-success mb-1 mr-2" id="openTaskModal">Aufgabe</button>
                        <button class="btn btn-sm btn-info mb-1" id="openNoteInline">Neue Notiz</button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <!-- Scroll-Container entfernt: max-height entfernt, Standard-Seitenscroll verwenden -->
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="diaryTable">
                            <thead class="thead-light" id="diaryHead"></thead>
                            <tbody id="diaryBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 order-lg-5" id="tasksPanel" style="display:none;">
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold small">Offene Aufgaben</span>
                    <button class="btn btn-link btn-sm p-0" id="refreshTasks" title="Aktualisieren"><i class="fas fa-sync"></i></button>
                </div>
                <!-- Scrollbegrenzung entfernt -->
                <div class="card-body p-2" id="tasksList"></div>
            </div>
        </div>
    </div>
</div>

<!-- Aufgabe Modal (unverändert) -->
<div class="modal fade" id="taskModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Aufgabe erfassen</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form id="taskForm">
        <div class="modal-body p-2">
            <input type="hidden" name="klasse_id" id="taskKlasseId" value="{{$klasse->id}}">
            <div class="form-group mb-2">
                <label class="small mb-1">Schüler</label>
                <select name="schueler_id" id="taskSchueler" class="form-control form-control-sm" required></select>
            </div>
            <div class="form-group mb-2">
                <label class="small mb-1">Titel</label>
                <input type="text" name="title" class="form-control form-control-sm" required maxlength="100">
            </div>
            <div class="form-group mb-2">
                <label class="small mb-1">Beschreibung</label>
                <textarea name="description" class="form-control form-control-sm" rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="col-md-6 mb-2">
                    <label class="small mb-1">Fällig</label>
                    <input type="date" name="due_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-6 mb-2 d-flex align-items-end">
                    <div class="">
                        <input class="form-check-input" type="checkbox" name="highlighted" id="taskHighlighted" checked value="1">
                        <label class="form-check-label small" for="taskHighlighted">Hervorheben</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer py-2">
            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Schließen</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Termin Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="appointmentModalTitle">Termin erstellen</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form id="appointmentForm">
        <div class="modal-body p-2">
            <div id="appointmentFeedback" class="small mb-2"></div>
            <input type="hidden" name="appointment_id" id="appointmentId" value="">

            <div class="form-row">
                <div class="col-md-6 mb-2">
                    <label class="small mb-1">Titel *</label>
                    <input type="text" name="title" id="appointmentTitle" class="form-control form-control-sm" required maxlength="255">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="small mb-1">Startdatum *</label>
                    <input type="date" name="start_date" id="appointmentStartDate" class="form-control form-control-sm" required>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-6 mb-2">
                    <label class="small mb-1">Startzeit</label>
                    <input type="time" name="start_time" id="appointmentStartTime" class="form-control form-control-sm">
                </div>
                <div class="col-md-6 mb-2">
                    <label class="small mb-1">Endzeit</label>
                    <input type="time" name="end_time" id="appointmentEndTime" class="form-control form-control-sm">
                </div>
            </div>

            <div class="form-group mb-2">
                <label class="small mb-1">Beschreibung</label>
                <textarea name="description" id="appointmentDescription" rows="3" class="form-control form-control-sm"></textarea>
            </div>

            <div class="form-group mb-2">
                <input type="checkbox" name="is_recurring" id="appointmentIsRecurring" value="1" class="align-middle">
                <label for="appointmentIsRecurring" class="small mb-0 align-middle">Wiederkehrender Termin</label>
            </div>

            <div id="recurringOptions" class="d-none">
                <div class="form-row">
                    <div class="col-md-4 mb-2">
                        <label class="small mb-1">Wiederholung</label>
                        <select name="recurring_type" id="appointmentRecurringType" class="form-control form-control-sm">
                            <option value="daily">Täglich</option>
                            <option value="weekly">Wöchentlich</option>
                            <option value="monthly">Monatlich</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="small mb-1">Intervall</label>
                        <input type="number" name="recurring_interval" id="appointmentRecurringInterval" class="form-control form-control-sm" min="1" value="1">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="small mb-1">Ende der Wiederholung</label>
                        <input type="date" name="recurring_end_date" id="appointmentRecurringEndDate" class="form-control form-control-sm">
                    </div>
                </div>
            </div>

            <div class="form-group mb-2">
                <label class="small mb-1">Zuweisen an</label>
                <div class="border rounded p-2 bg-light" style="font-size:0.75rem;" id="appointmentStudentsBox">
                    <div class="mb-2">
                        <strong>Klassen:</strong>
                        @foreach($klassen as $k)
                            <div class="form-check-inline mb-1">
                                <input class="form-check-input" type="checkbox" id="app_cls_{{$k->id}}" value="{{$k->id}}" name="klasse_ids[]">
                                <label class="form-check-label" for="app_cls_{{$k->id}}">{{$k->name}}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="mb-2">
                        <strong>Gruppen:</strong>
                        @foreach($groups as $g)
                            <div class="form-check-inline mb-1">
                                <input class="form-check-input" type="checkbox" id="app_grp_{{$g->id}}" value="{{$g->id}}" name="group_ids[]">
                                <label class="form-check-label" for="app_grp_{{$g->id}}">{{$g->name}}</label>
                            </div>
                        @endforeach
                    </div>
                    <div id="appointmentSchuelerBox" class="mb-2">
                        <strong>Schüler:</strong>
                        <div class="mt-1" id="appointmentSchuelerList">
                            <!-- Wird dynamisch befüllt basierend auf ausgewählter Klasse/Gruppe -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer py-2">
            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
            <button type="button" class="btn btn-danger btn-sm d-none" id="appointmentDeleteBtn">Löschen</button>
            <button type="button" class="btn btn-warning btn-sm d-none" id="appointmentPauseBtn">Pausieren</button>
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Schließen</button>
            <span class="text-muted small ml-3" id="appointmentStatus"></span>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/paedDiary.css?v=20250916') }}">
<style>
.class-divider-row td { background:#f1f3f5; font-weight:bold; font-size:.75rem; }
.group-disabled { opacity:.5; pointer-events:none; }
</style>
@endpush

@push('js')
<script src="{{ asset('/js/paedDiary.js')}}"></script>
@endpush
