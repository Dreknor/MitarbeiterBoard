<?php

use App\Http\Controllers\API\v1\AuthApiController;
use App\Http\Controllers\API\v1\ClassApiController;
use App\Http\Controllers\API\v1\ClassOverviewApiController;
use App\Http\Controllers\API\v1\DiagnosticApiController;
use App\Http\Controllers\API\v1\DossierApiController;
use App\Http\Controllers\API\v1\GradingApiController;
use App\Http\Controllers\API\v1\GradingJoinApiController;
use App\Http\Controllers\API\v1\InstanceApiController;
use App\Http\Controllers\API\v1\PaedDiaryApiController;
use App\Http\Controllers\API\v1\PaedDiaryPlanningApiController;
use App\Http\Controllers\API\v1\PaedDiaryWeekApiController;
use App\Http\Controllers\API\v1\SsoApiController;
use App\Http\Controllers\API\v1\StudentGradingApiController;
use App\Http\Controllers\API\v1\StudentViewApiController;
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

    // Lehrkraft-Endpunkte: nur Benutzer-Tokens (api.staff), schreibende Aufrufe idempotent per Idempotency-Key
    Route::middleware(['auth:sanctum', 'api.staff', 'permission:view paed diary', 'idempotent'])->group(function () {

        Route::get('auth/me', [AuthApiController::class, 'me'])->name('auth.me');
        Route::delete('auth/token', [AuthApiController::class, 'revokeToken'])->name('auth.revoke');
        Route::get('auth/devices', [AuthApiController::class, 'devices'])->name('auth.devices');
        Route::delete('auth/devices/{device}', [AuthApiController::class, 'destroyDevice'])
            ->whereNumber('device')->name('auth.devices.destroy');

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
    });
});
