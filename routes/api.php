<?php

use App\Http\Controllers\API\v1\AbsenceApiController;
use App\Http\Controllers\API\v1\AuthApiController;
use App\Http\Controllers\API\v1\ClassApiController;
use App\Http\Controllers\API\v1\ClassOverviewApiController;
use App\Http\Controllers\API\v1\DiagnosticApiController;
use App\Http\Controllers\API\v1\DossierApiController;
use App\Http\Controllers\API\v1\ParentMeetingApiController;
use App\Http\Controllers\API\v1\GradingApiController;
use App\Http\Controllers\API\v1\GradingJoinApiController;
use App\Http\Controllers\API\v1\HolidayApiController;
use App\Http\Controllers\API\v1\InstanceApiController;
use App\Http\Controllers\API\v1\PaedDiaryApiController;
use App\Http\Controllers\API\v1\PaedDiaryPlanningApiController;
use App\Http\Controllers\API\v1\PaedDiaryWeekApiController;
use App\Http\Controllers\API\v1\RosterApiController;
use App\Http\Controllers\API\v1\SsoApiController;
use App\Http\Controllers\API\v1\StudentGradingApiController;
use App\Http\Controllers\API\v1\StudentViewApiController;
use App\Http\Controllers\API\v1\TimesheetApiController;
use App\Http\Controllers\API\VertretungsplanImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::put('/vertretungen/{key}/vp', [VertretungsplanImportController::class, 'import'])
    ->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| API v1 – Pädagogen-App (Schüler-View, Tagebuch, Graduierung, Diagnose)
|--------------------------------------------------------------------------
| Authentifizierung: Laravel Sanctum (Authorization: Bearer <token>)
| Dokumentation:     resources/api-docs/openapi-v1.yaml (OpenAPI 3.0) + README.md
*/
Route::prefix('v1')->name('api.v1.')->middleware('json')->group(function () {

    // Instanz-Info für die Serverwahl der App (öffentlich, keine personenbezogenen Daten)
    Route::get('instance', [InstanceApiController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('instance');

    // Token ausstellen (Login mit lokalem Passwort) – 6/min je E-Mail+IP, 60/min je IP
    Route::post('auth/token', [AuthApiController::class, 'issueToken'])
        ->middleware('throttle:paed-app-login')
        ->name('auth.token');

    // SSO-Login (Keycloak über das Backend, PKCE zwischen App und Backend)
    Route::get('auth/sso/start', [SsoApiController::class, 'start'])
        ->middleware(['api.session', 'throttle:30,1'])
        ->name('auth.sso.start');
    Route::post('auth/sso/exchange', [SsoApiController::class, 'exchange'])
        ->middleware('throttle:10,1')
        ->name('auth.sso.exchange');

    // Selbsteinschätzung auf Schüler-iPads: Beitritt per Code (öffentlich) und Schüler-Endpunkte.
    // Schüler-Tokens sind ausschließlich hier gültig (api.student prüft student-grading:{session}:{schueler}).
    Route::post('student/join', [StudentGradingApiController::class, 'join'])
        ->middleware('throttle:10,1')
        ->name('student.join');
    Route::middleware(['auth:sanctum', 'api.student'])->prefix('student')->name('student.')->group(function () {
        Route::get('session', [StudentGradingApiController::class, 'session'])->name('session');
        Route::post('session/answers', [StudentGradingApiController::class, 'storeAnswer'])->name('session.answers');
    });

    // Eigenes Konto & Geräte: für alle Benutzer-Tokens (Pädagogen-App und Dienstplan-App)
    Route::middleware(['auth:sanctum', 'api.staff'])->group(function () {
        Route::get('auth/me', [AuthApiController::class, 'me'])->name('auth.me');
        Route::delete('auth/token', [AuthApiController::class, 'revokeToken'])->name('auth.revoke');
        Route::get('auth/devices', [AuthApiController::class, 'devices'])->name('auth.devices');
        Route::delete('auth/devices/{device}', [AuthApiController::class, 'destroyDevice'])
            ->whereNumber('device')->name('auth.devices.destroy');
    });

    // Lehrkraft-Endpunkte: nur Benutzer-Tokens (api.staff), schreibende Aufrufe idempotent per Idempotency-Key
    Route::middleware(['auth:sanctum', 'api.staff', 'permission:view paed diary', 'idempotent'])->group(function () {

        // Bereich 0: Klassen-Kontext & Schülerauswahl
        Route::get('classes', [ClassApiController::class, 'index'])->name('classes.index');
        Route::get('classes/{klasse}/students', [ClassApiController::class, 'students'])
            ->whereNumber('klasse')->name('classes.students');

        // Bereich 1: Zentrale Schüler-View
        Route::get('students/{schueler}/view', [StudentViewApiController::class, 'show'])
            ->whereNumber('schueler')->name('students.view');

        // Bereich 2: Pädagogisches Tagebuch
        Route::get('classes/{klasse}/paed-diary/entries', [PaedDiaryApiController::class, 'classEntries'])
            ->whereNumber('klasse')->name('paed-diary.class-entries');
        Route::get('paed-diary/categories', [PaedDiaryApiController::class, 'categories'])
            ->middleware('etag')->name('paed-diary.categories');
        Route::get('students/{schueler}/paed-diary/entries', [PaedDiaryApiController::class, 'studentEntries'])
            ->whereNumber('schueler')->name('paed-diary.student-entries');
        Route::post('paed-diary/entries', [PaedDiaryApiController::class, 'store'])->name('paed-diary.entries.store');
        Route::post('paed-diary/bulk-entries', [PaedDiaryApiController::class, 'bulkStore'])->name('paed-diary.entries.bulk');
        Route::get('paed-diary/entries/{entry}', [PaedDiaryApiController::class, 'show'])
            ->whereNumber('entry')->name('paed-diary.entries.show');
        Route::put('paed-diary/entries/{entry}', [PaedDiaryApiController::class, 'update'])
            ->whereNumber('entry')->name('paed-diary.entries.update');
        Route::delete('paed-diary/entries/{entry}', [PaedDiaryApiController::class, 'destroy'])
            ->whereNumber('entry')->name('paed-diary.entries.destroy');

        // Wochenansicht (Kalender): offene Notizen, Pausen, Abwesenheiten, Spalten, Termine
        Route::get('paed-diary/week', [PaedDiaryWeekApiController::class, 'week'])->name('paed-diary.week');
        Route::post('paed-diary/entries/{entry}/complete', [PaedDiaryWeekApiController::class, 'complete'])
            ->whereNumber('entry')->name('paed-diary.entries.complete');
        Route::put('paed-diary/entries/{entry}/pause', [PaedDiaryWeekApiController::class, 'pause'])
            ->whereNumber('entry')->name('paed-diary.entries.pause');
        Route::put('paed-diary/absences', [PaedDiaryWeekApiController::class, 'absence'])->name('paed-diary.absences');
        Route::put('paed-diary/day-pauses', [PaedDiaryWeekApiController::class, 'dayPause'])->name('paed-diary.day-pauses');
        Route::put('paed-diary/column-values', [PaedDiaryWeekApiController::class, 'columnValue'])
            ->name('paed-diary.column-values');
        Route::post('paed-diary/tasks/{task}/close', [PaedDiaryWeekApiController::class, 'closeTask'])
            ->whereNumber('task')->name('paed-diary.tasks.close');

        // Planung: Aufgaben, Termine, Wiedervorlage, Schüler eines Eintrags
        Route::post('paed-diary/tasks', [PaedDiaryPlanningApiController::class, 'storeTask'])->name('paed-diary.tasks.store');
        Route::put('paed-diary/tasks/{task}', [PaedDiaryPlanningApiController::class, 'updateTask'])
            ->whereNumber('task')->name('paed-diary.tasks.update');
        Route::post('paed-diary/appointments', [PaedDiaryPlanningApiController::class, 'storeAppointment'])
            ->name('paed-diary.appointments.store');
        Route::put('paed-diary/appointments/{appointment}', [PaedDiaryPlanningApiController::class, 'updateAppointment'])
            ->whereNumber('appointment')->name('paed-diary.appointments.update');
        Route::delete('paed-diary/appointments/{appointment}', [PaedDiaryPlanningApiController::class, 'destroyAppointment'])
            ->whereNumber('appointment')->name('paed-diary.appointments.destroy');
        Route::put('paed-diary/entries/{entry}/resubmission', [PaedDiaryPlanningApiController::class, 'resubmission'])
            ->whereNumber('entry')->name('paed-diary.entries.resubmission');
        Route::put('paed-diary/entries/{entry}/students/{schueler}', [PaedDiaryPlanningApiController::class, 'attachStudent'])
            ->whereNumber('entry')->whereNumber('schueler')->name('paed-diary.entries.students.attach');
        Route::delete('paed-diary/entries/{entry}/students/{schueler}', [PaedDiaryPlanningApiController::class, 'detachStudent'])
            ->whereNumber('entry')->whereNumber('schueler')->name('paed-diary.entries.students.detach');

        // Bereich 3: Graduierung
        Route::get('grading/stages', [GradingApiController::class, 'stages'])
            ->middleware('etag')->name('grading.stages');
        Route::get('students/{schueler}/grading/history', [GradingApiController::class, 'history'])
            ->whereNumber('schueler')->name('grading.history');
        Route::get('classes/{klasse}/grading/overview', [ClassOverviewApiController::class, 'grading'])
            ->whereNumber('klasse')->name('grading.class-overview');
        Route::get('classes/{klasse}/grading/sessions', [GradingApiController::class, 'classSessions'])
            ->whereNumber('klasse')->name('grading.class-sessions');
        Route::post('grading/sessions', [GradingApiController::class, 'storeSession'])->name('grading.sessions.store');
        Route::get('grading/sessions/{session}', [GradingApiController::class, 'showSession'])
            ->whereNumber('session')->name('grading.sessions.show');
        Route::patch('grading/sessions/{session}', [GradingApiController::class, 'updateSession'])
            ->whereNumber('session')->name('grading.sessions.update');
        Route::post('grading/sessions/{session}/assessments', [GradingApiController::class, 'storeAssessments'])
            ->whereNumber('session')->name('grading.sessions.assessments');

        // Selbsteinschätzung auf Schüler-iPads (nur Ersteller der Session)
        Route::post('grading/sessions/{session}/join-codes', [GradingJoinApiController::class, 'store'])
            ->whereNumber('session')->name('grading.sessions.join-codes.store');
        Route::delete('grading/sessions/{session}/join-codes', [GradingJoinApiController::class, 'destroy'])
            ->whereNumber('session')->name('grading.sessions.join-codes.destroy');
        // Gerät eines Schülers zurücksetzen (Code gilt für ein Gerät; danach neuen Code erzeugen)
        Route::delete('grading/sessions/{session}/join-codes/{schueler}', [GradingJoinApiController::class, 'resetDevice'])
            ->whereNumber('session')->whereNumber('schueler')->name('grading.sessions.join-codes.reset');
        Route::post('grading/sessions/{session}/current-question', [GradingJoinApiController::class, 'currentQuestion'])
            ->whereNumber('session')->name('grading.sessions.current-question');

        // Bereich 4: Diagnose & Entwicklungsziele
        Route::middleware('permission:view diagnostics')->group(function () {
            Route::get('diagnostic/areas', [DiagnosticApiController::class, 'areas'])
                ->middleware('etag')->name('diagnostic.areas');
            Route::get('students/{schueler}/diagnostic/history', [DiagnosticApiController::class, 'history'])
                ->whereNumber('schueler')->name('diagnostic.history');
            Route::post('diagnostic/sessions', [DiagnosticApiController::class, 'storeSession'])->name('diagnostic.sessions.store');
            Route::put('diagnostic/goals/{goal}', [DiagnosticApiController::class, 'updateGoal'])
                ->whereNumber('goal')->name('diagnostic.goals.update');
            Route::delete('diagnostic/goals/{goal}', [DiagnosticApiController::class, 'destroyGoal'])
                ->whereNumber('goal')->name('diagnostic.goals.destroy');
            Route::get('classes/{klasse}/diagnostic/overview', [ClassOverviewApiController::class, 'diagnostic'])
                ->whereNumber('klasse')->name('diagnostic.class-overview');
        });

        // Bereich 5: Dossier-Export (JSON und PDF)
        Route::get('students/{schueler}/dossier.pdf', [DossierApiController::class, 'pdf'])
            ->whereNumber('schueler')->name('students.dossier.pdf');
        Route::get('students/{schueler}/dossier', [DossierApiController::class, 'show'])
            ->whereNumber('schueler')->name('students.dossier');

        // Bereich 6: Elterngespräche (Planung, Vorbereitung, Protokoll)
        Route::get('students/{schueler}/parent-meetings', [ParentMeetingApiController::class, 'index'])
            ->whereNumber('schueler')->name('parent-meetings.index');
        Route::post('students/{schueler}/parent-meetings', [ParentMeetingApiController::class, 'store'])
            ->whereNumber('schueler')->name('parent-meetings.store');
        Route::get('parent-meetings/{meeting}', [ParentMeetingApiController::class, 'show'])
            ->whereNumber('meeting')->name('parent-meetings.show');
        Route::put('parent-meetings/{meeting}', [ParentMeetingApiController::class, 'update'])
            ->whereNumber('meeting')->name('parent-meetings.update');
        Route::post('parent-meetings/{meeting}/complete', [ParentMeetingApiController::class, 'complete'])
            ->whereNumber('meeting')->name('parent-meetings.complete');
        Route::post('parent-meetings/{meeting}/reopen', [ParentMeetingApiController::class, 'reopen'])
            ->whereNumber('meeting')->name('parent-meetings.reopen');
        Route::post('parent-meetings/{meeting}/cancel', [ParentMeetingApiController::class, 'cancel'])
            ->whereNumber('meeting')->name('parent-meetings.cancel');
        Route::delete('parent-meetings/{meeting}', [ParentMeetingApiController::class, 'destroy'])
            ->whereNumber('meeting')->name('parent-meetings.destroy');
        Route::post('parent-meetings/{meeting}/agreements/{agreement}/transfer', [ParentMeetingApiController::class, 'transfer'])
            ->whereNumber(['meeting', 'agreement'])->name('parent-meetings.agreements.transfer');
        Route::get('parent-meetings/{meeting}/protocol.pdf', [ParentMeetingApiController::class, 'pdf'])
            ->whereNumber('meeting')->name('parent-meetings.pdf');
    });

    // Dienstplanung: unabhängig vom Pädagogischen Tagebuch, nur Benutzer-Tokens (api.staff).
    // "Mein Dienstplan" für alle, Planung mit "create roster" + Abteilungsbezug (RosterPolicy).
    Route::middleware(['auth:sanctum', 'api.staff', 'idempotent'])->prefix('roster')->name('roster.')->group(function () {
        Route::get('mine', [RosterApiController::class, 'mine'])->name('mine');
        Route::get('rosters/{roster}', [RosterApiController::class, 'show'])->whereNumber('roster')->name('show');

        Route::middleware('permission:create roster')->group(function () {
            Route::get('departments', [RosterApiController::class, 'departments'])->name('departments');
            Route::get('departments/{department}/rosters', [RosterApiController::class, 'index'])
                ->whereNumber('department')->name('index');
            Route::post('rosters', [RosterApiController::class, 'store'])->name('store');
            Route::delete('rosters/{roster}', [RosterApiController::class, 'destroy'])->whereNumber('roster')->name('destroy');
            Route::post('rosters/{roster}/copy', [RosterApiController::class, 'copy'])->whereNumber('roster')->name('copy');
            Route::post('rosters/{roster}/publish', [RosterApiController::class, 'publish'])->whereNumber('roster')->name('publish');
            Route::post('rosters/{roster}/unpublish', [RosterApiController::class, 'unpublish'])->whereNumber('roster')->name('unpublish');
            Route::post('rosters/{roster}/notify-changes', [RosterApiController::class, 'notifyChanges'])->whereNumber('roster')->name('notify-changes');
            Route::post('rosters/{roster}/revert', [RosterApiController::class, 'revert'])->whereNumber('roster')->name('revert');
            Route::put('rosters/{roster}/working-times', [RosterApiController::class, 'setWorkingTime'])->whereNumber('roster')->name('working-times.set');
            Route::post('rosters/{roster}/events', [RosterApiController::class, 'storeEvent'])->whereNumber('roster')->name('events.store');
            Route::get('rosters/{roster}/calendar-import', [RosterApiController::class, 'calendarImportPreview'])->whereNumber('roster')->name('calendar-import.preview');
            Route::post('rosters/{roster}/calendar-import', [RosterApiController::class, 'calendarImport'])->whereNumber('roster')->name('calendar-import.store');
            Route::delete('rosters/{roster}/days/{date}', [RosterApiController::class, 'clearDay'])->whereNumber('roster')
                ->where('date', '\d{4}-\d{2}-\d{2}')->name('days.clear');
            Route::post('rosters/{roster}/notes', [RosterApiController::class, 'storeNote'])->whereNumber('roster')->name('notes.store');
            Route::delete('notes/{note}', [RosterApiController::class, 'destroyNote'])->whereNumber('note')->name('notes.destroy');
            Route::put('events/{rosterEvent}', [RosterApiController::class, 'updateEvent'])->whereNumber('rosterEvent')->name('events.update');
            Route::delete('events/{rosterEvent}', [RosterApiController::class, 'destroyEvent'])->whereNumber('rosterEvent')->name('events.destroy');
        });
    });

    // Arbeitszeitnachweise: eigene sowie die der unterstellten Mitarbeitenden (TimesheetPolicy/ZeitZugriff),
    // nur Benutzer-Tokens (api.staff). Rechte werden je Person bzw. Nachweis geprüft.
    Route::middleware(['auth:sanctum', 'api.staff', 'idempotent'])->prefix('timesheets')->name('timesheets.')->group(function () {
        Route::get('employees', [TimesheetApiController::class, 'employees'])->name('employees');
        Route::get('employees/{employe}', [TimesheetApiController::class, 'overview'])->whereNumber('employe')->name('overview');
        Route::get('employees/{employe}/months/{month}', [TimesheetApiController::class, 'month'])
            ->whereNumber('employe')->where('month', '\d{4}-\d{2}')->name('month');
        Route::post('employees/{employe}/recalculate', [TimesheetApiController::class, 'recalculateAll'])->whereNumber('employe')->name('recalculate-all');

        // Eigenes Kommen/Gehen (Standort-Erinnerung der Dienstplan-App)
        Route::get('clock', [TimesheetApiController::class, 'clockStatus'])->name('clock.status');
        Route::post('clock', [TimesheetApiController::class, 'clock'])->middleware('throttle:10,1')->name('clock');

        Route::get('{timesheet}', [TimesheetApiController::class, 'show'])->whereNumber('timesheet')->name('show');
        Route::post('{timesheet}/recalculate', [TimesheetApiController::class, 'recalculate'])->whereNumber('timesheet')->name('recalculate');

        Route::get('{timesheet}/pdf', [TimesheetApiController::class, 'pdf'])->whereNumber('timesheet')->name('pdf');
        Route::post('{timesheet}/days', [TimesheetApiController::class, 'storeDay'])->whereNumber('timesheet')->name('days.store');
        Route::post('{timesheet}/credits', [TimesheetApiController::class, 'storeCredit'])->whereNumber('timesheet')->name('credits.store');
        Route::post('{timesheet}/apply-roster', [TimesheetApiController::class, 'applyRoster'])->whereNumber('timesheet')->name('apply-roster');
        Route::put('days/{timesheetDay}', [TimesheetApiController::class, 'updateDay'])->whereNumber('timesheetDay')->name('days.update');
        Route::delete('days/{timesheetDay}', [TimesheetApiController::class, 'destroyDay'])->whereNumber('timesheetDay')->name('days.destroy');

        Route::post('{timesheet}/submit', [TimesheetApiController::class, 'submit'])->whereNumber('timesheet')->name('submit');
        Route::post('{timesheet}/lock', [TimesheetApiController::class, 'lock'])->whereNumber('timesheet')->name('lock');
        Route::post('{timesheet}/return', [TimesheetApiController::class, 'returnToEmploye'])->whereNumber('timesheet')->name('return');
        Route::post('{timesheet}/unlock', [TimesheetApiController::class, 'unlock'])->whereNumber('timesheet')->name('unlock');
    });

    // Urlaub: eigenes Konto/eigene Anträge, Anträge für Unterstellte und Entscheidungen (HolidayPolicy/ZeitZugriff).
    Route::middleware(['auth:sanctum', 'api.staff', 'idempotent', 'permission:has holidays|approve holidays'])
        ->prefix('holidays')->name('holidays.')->group(function () {
            Route::get('/', [HolidayApiController::class, 'index'])->name('index');
            Route::get('decisions', [HolidayApiController::class, 'decisions'])->name('decisions');
            Route::get('preview', [HolidayApiController::class, 'preview'])->name('preview');
            Route::post('/', [HolidayApiController::class, 'store'])->name('store');
            Route::post('{holiday}/approve', [HolidayApiController::class, 'approve'])->whereNumber('holiday')->name('approve');
            Route::post('{holiday}/reject', [HolidayApiController::class, 'reject'])->whereNumber('holiday')->name('reject');
            Route::post('{holiday}/cancel', [HolidayApiController::class, 'requestCancellation'])->whereNumber('holiday')->name('cancel');
            Route::post('{holiday}/cancel-decision', [HolidayApiController::class, 'decideCancellation'])->whereNumber('holiday')->name('cancel-decision');
            Route::delete('{holiday}', [HolidayApiController::class, 'destroy'])->whereNumber('holiday')->name('destroy');
        });

    // Abwesenheiten (wie die Abwesenheiten-Karte im Web): "view absences", für andere "create absences".
    Route::middleware(['auth:sanctum', 'api.staff', 'idempotent', 'permission:view absences'])
        ->prefix('absences')->name('absences.')->group(function () {
            Route::get('/', [AbsenceApiController::class, 'index'])->name('index');
            Route::post('/', [AbsenceApiController::class, 'store'])->name('store');
            Route::delete('{absence}', [AbsenceApiController::class, 'destroy'])->whereNumber('absence')->name('destroy');
        });
});
