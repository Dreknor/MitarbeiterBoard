<?php

use App\Http\Controllers\API\v1\AuthApiController;
use App\Http\Controllers\API\v1\ClassApiController;
use App\Http\Controllers\API\v1\DiagnosticApiController;
use App\Http\Controllers\API\v1\DossierApiController;
use App\Http\Controllers\API\v1\GradingApiController;
use App\Http\Controllers\API\v1\PaedDiaryApiController;
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

    // Token ausstellen (Login mit lokalem Passwort)
    Route::post('auth/token', [AuthApiController::class, 'issueToken'])
        ->middleware('throttle:6,1')
        ->name('auth.token');

    Route::middleware(['auth:sanctum', 'permission:view paed diary'])->group(function () {

        Route::get('auth/me', [AuthApiController::class, 'me'])->name('auth.me');
        Route::delete('auth/token', [AuthApiController::class, 'revokeToken'])->name('auth.revoke');

        // Bereich 0: Klassen-Kontext & Schülerauswahl
        Route::get('classes', [ClassApiController::class, 'index'])->name('classes.index');
        Route::get('classes/{klasse}/students', [ClassApiController::class, 'students'])
            ->whereNumber('klasse')->name('classes.students');

        // Bereich 1: Zentrale Schüler-View
        Route::get('students/{schueler}/view', [StudentViewApiController::class, 'show'])
            ->whereNumber('schueler')->name('students.view');

        // Bereich 2: Pädagogisches Tagebuch
        Route::get('paed-diary/categories', [PaedDiaryApiController::class, 'categories'])->name('paed-diary.categories');
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

        // Bereich 3: Graduierung
        Route::get('grading/stages', [GradingApiController::class, 'stages'])->name('grading.stages');
        Route::get('students/{schueler}/grading/history', [GradingApiController::class, 'history'])
            ->whereNumber('schueler')->name('grading.history');
        Route::post('grading/sessions', [GradingApiController::class, 'storeSession'])->name('grading.sessions.store');
        Route::get('grading/sessions/{session}', [GradingApiController::class, 'showSession'])
            ->whereNumber('session')->name('grading.sessions.show');
        Route::post('grading/sessions/{session}/assessments', [GradingApiController::class, 'storeAssessments'])
            ->whereNumber('session')->name('grading.sessions.assessments');

        // Bereich 4: Diagnose & Entwicklungsziele
        Route::middleware('permission:view diagnostics')->group(function () {
            Route::get('diagnostic/areas', [DiagnosticApiController::class, 'areas'])->name('diagnostic.areas');
            Route::get('students/{schueler}/diagnostic/history', [DiagnosticApiController::class, 'history'])
                ->whereNumber('schueler')->name('diagnostic.history');
            Route::post('diagnostic/sessions', [DiagnosticApiController::class, 'storeSession'])->name('diagnostic.sessions.store');
            Route::put('diagnostic/goals/{goal}', [DiagnosticApiController::class, 'updateGoal'])
                ->whereNumber('goal')->name('diagnostic.goals.update');
            Route::delete('diagnostic/goals/{goal}', [DiagnosticApiController::class, 'destroyGoal'])
                ->whereNumber('goal')->name('diagnostic.goals.destroy');
        });

        // Bereich 5: Dossier-Export
        Route::get('students/{schueler}/dossier', [DossierApiController::class, 'show'])
            ->whereNumber('schueler')->name('students.dossier');
    });
});
