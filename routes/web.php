<?php

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Auth\ExpiredPasswordController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DailyNewsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\Inventory\ItemsController;
use App\Http\Controllers\Inventory\LocationController;
use App\Http\Controllers\Inventory\LocationTypeController;
use App\Http\Controllers\KlasseController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\PeadDiaryWeekController;
use App\Http\Controllers\Personal\AddressController;
use App\Http\Controllers\Personal\EmployeController;
use App\Http\Controllers\Personal\EmploymentController;
use App\Http\Controllers\Personal\HolidayController;
use App\Http\Controllers\Personal\RosterCheckController;
use App\Http\Controllers\Personal\RosterController;
use App\Http\Controllers\Personal\RosterEventsController;
use App\Http\Controllers\Personal\RosterNewsController;
use App\Http\Controllers\Personal\TimeRecordingController;
use App\Http\Controllers\Personal\TimesheetController;
use App\Http\Controllers\Personal\HortPlanungController;
use App\Http\Controllers\Personal\WorkingTimeController;
use App\Http\Controllers\PositionsController;
use App\Http\Controllers\PostsController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RecurringProcedureController;
use App\Http\Controllers\RecurringThemeController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\RoomCalendarController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TerminListen\ListenController;
use App\Http\Controllers\TerminListen\ListenTerminController;

use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VertretungController;
use App\Http\Controllers\VertretungsplanAbsenceController;
use App\Http\Controllers\VertretungsplanController;
use App\Http\Controllers\VertretungsplanWeekController;
use App\Http\Controllers\WikiController;
use App\Http\Controllers\WochenplanController;
use App\Http\Controllers\WPRowsController;
use App\Http\Controllers\WpTaskController;
use App\Http\Controllers\Wochenplan\WpPlanController;
use App\Http\Controllers\Wochenplan\WpAufgabeController;
use App\Http\Controllers\Wochenplan\WpExportController;
use App\Http\Controllers\Wochenplan\WpVorlageController;
use App\Http\Controllers\Wochenplan\WpFormatvorlageController;
use App\Http\Controllers\Wochenplan\WpFachController;
use App\Http\Controllers\Wochenplan\WpSyncController;
use App\Http\Controllers\Wochenplan\WpTaeglicheUebungController;
use App\Http\Controllers\SchuelerController; // hinzugefügt
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShareController;
use Laravel\Socialite\Facades\Socialite;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/auth/redirect', [\App\Http\Controllers\Auth\KeycloakLoginController::class,'login']);

Route::get('display-week/{secret?}', [PeadDiaryWeekController::class, 'displayWeek']);

Route::get('/auth/callback', [\App\Http\Controllers\Auth\KeycloakLoginController::class,'auth']);

if (config('config.auth.auth_local')){
    Auth::routes(['register' => false]);
} else {

    Auth::routes(['register' => false]);


    Route::post('login', function(){
        return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Login nicht gestattet']);
    });
}

Route::get('image/{media_id}', [ImageController::class, 'getImage'])->name('image.get');

Route::get('/vertretungsplan/withkey/{key}', [VertretungsplanController::class, 'allowAllIndex']);

// Öffentliche Schüler-Dokumentation via QR-Code (ohne Anmeldung)
Route::get('/paed-diary/documentation/public/{token}', [\App\Http\Controllers\GradingDocumentationController::class, 'showPublicStudentSession'])->name('gradingDocumentation.publicStudentSession');
Route::post('/paed-diary/documentation/public/student-answer', [\App\Http\Controllers\GradingDocumentationController::class, 'savePublicStudentAnswer'])->name('gradingDocumentation.savePublicStudentAnswer');


Route::get('/vertretungsplan/{key}/{gruppen?}', [VertretungsplanController::class, 'index'])->where('gruppen','.+');
Route::get('/vertretungsplan/{gruppen?}', [VertretungsplanController::class, 'index'])->where('gruppen','.+');
Route::get('/api/absences/{key}/', [VertretungsplanController::class, 'absencesToJSON']);
Route::get('/api/vertretungsplan/{key}/{gruppen?}', [VertretungsplanController::class, 'toJSON'])->where('gruppen','.+');

Route::get('share/{uuid}', [\App\Http\Controllers\ShareController::class,'getShare']);
Route::post('share/{share}/protocol', [ShareController::class,'protocol']);

Route::get('inventory/item/{uuid}', [ItemsController::class,'scan']);
Route::post('inventory/item/{uuid}', [ItemsController::class,'scanUpdate']);

/*
* digitale Arbeitszeiterfassung
*/
Route::prefix('time_recording')->group(callback: function (){
    Route::get('start', [TimeRecordingController::class, 'start'])->name('time_recording.start');
    Route::post('start', [TimeRecordingController::class, 'read_key'])->name('time_recording.read_key');
    Route::post('check_secret/', [TimeRecordingController::class, 'check_secret'])->name('time_recording.check_secret');
    Route::post('login', [TimeRecordingController::class, 'login'])->name('time_recording.login');
    Route::get('logout', [TimeRecordingController::class, 'logout'])->name('time_recording.logout');

    Route::post('storeSecret', [TimeRecordingController::class, 'storeSecret'])->name('time_recording.storeSecret');
});


Route::group([
    'middleware' => ['auth'],
],
    function () {
        Route::get('password/expired', [ExpiredPasswordController::class,'expired'])
            ->name('password.expired');
        Route::post('password/post_expired', [ExpiredPasswordController::class,'postExpired'])
            ->name('password.post_expired');

        Route::group([
            'middleware' => ['password_expired'],
        ],
            function () {

                /*
                 * Routes for edit dashboard
                 */
                Route::get('dashboard/{dashBoardUser}/up', [DashboardController::class, 'up']);
                Route::get('dashboard/{dashBoardUser}/down', [DashboardController::class, 'down']);
                Route::get('dashboard/{dashBoardUser}/left', [DashboardController::class, 'left']);
                Route::get('dashboard/{dashBoardUser}/right', [DashboardController::class, 'right']);
                Route::get('dashboard/{dashBoardUser}/toggle', [DashboardController::class, 'toggle']);

                /*
                 * Dashboard v2 API-Routen
                 */
                Route::put('dashboard/layout', [DashboardController::class, 'updateLayout']);
                Route::post('dashboard/layout/reset', [DashboardController::class, 'resetLayout']);
                Route::get('dashboard/hilfe', [DashboardController::class, 'hilfe'])->name('dashboard.hilfe');
                Route::get('dashboard/card/{dashBoardUser}', [DashboardController::class, 'loadCard']);
                Route::post('notifications/mark-all-read', [DashboardController::class, 'markNotificationsRead'])->name('notifications.markAllRead');

                /*
                 * Dashboard v2 – Schnellzugriff (Quicklinks)
                 */
                Route::post('dashboard/quicklinks', [DashboardController::class, 'storeQuicklink'])->name('dashboard.quicklinks.store');
                Route::delete('dashboard/quicklinks/{quicklink}', [DashboardController::class, 'destroyQuicklink'])->name('dashboard.quicklinks.destroy');

                /*
                 * Routes for Wiki
                 */
                Route::middleware(['permission:view wiki'])->group(function () {
                    Route::post('wiki', [WikiController::class, 'store']);
                    Route::post('wiki/add', [WikiController::class, 'new']);
                    Route::get('wiki/all', [WikiController::class, 'all_sites']);
                    Route::post('wiki/search', [WikiController::class, 'search']);
                    Route::get('wiki/create/{slug}', [WikiController::class, 'create']);
                    Route::get('wiki/{slug?}/{version?}', [WikiController::class, 'index'])->name('wiki');

                });

                /*
                 * Routes for Ticketsystem
                 */
                Route::middleware(['permission:view tickets'])->group(callback: function () {
                    Route::get('/Ticketsystem/themes', [\App\Http\Controllers\Ticketsystem\TicketController::class, 'index']);

                    Route::prefix('tickets')->group(function () {
                        Route::resource('categories', \App\Http\Controllers\Ticketsystem\TicketCategoryController::class)->middleware('permission:edit tickets')->only(['index', 'store', 'destroy']);
                        Route::post('comments/{ticket}', [\App\Http\Controllers\Ticketsystem\TicketCommentController::class, 'store'])->name('tickets.comments.store');
                    });
                    Route::get('import/tickets/group/{group}', [\App\Http\Controllers\Ticketsystem\TicketController::class, 'createTicketsFromThemes']);
                    Route::get('tickets/archiv', [\App\Http\Controllers\Ticketsystem\TicketController::class, 'archived'])->name('tickets.archive');
                    Route::get('tickets/archiv/{ticket}', [\App\Http\Controllers\Ticketsystem\TicketController::class, 'showClosedTicket'])->name('tickets.archiveTicket');
                    Route::resource('tickets', \App\Http\Controllers\Ticketsystem\TicketController::class)->except('create', 'edit');
                    Route::get('tickets/{ticket}/close', [\App\Http\Controllers\Ticketsystem\TicketController::class, 'close'])->name('tickets.close');
                    Route::get('tickets/{ticket}/assign/{user}', [\App\Http\Controllers\Ticketsystem\TicketController::class, 'assign'])->name('tickets.assign');
                    /*Pin a Ticket*/
                    Route::get('tickets/{ticket}/pin', [\App\Http\Controllers\Ticketsystem\TicketController::class, 'pin'])->name('tickets.pin');
                });


                /*
                 * Edit Employes
                 */
                Route::get('/employes/self', [EmployeController::class, 'show_self'])->name('employes.self');
                Route::put('/employes/self', [EmployeController::class, 'update_self'])->name('employes.self.update');
                Route::post('/employes/photo', [EmployeController::class, 'photo'])->name('employes.self.photo');


                Route::middleware(['permission:edit employe'])->group(function () {
                    // Bulk-Update für Urlaubsanspruch nach Gruppen (muss vor resource Route stehen)
                    Route::get('employes/bulk-holiday-claim', [EmployeController::class, 'bulkHolidayClaimForm'])->name('employes.bulk-holiday-claim');
                    Route::post('employes/bulk-holiday-claim', [EmployeController::class, 'bulkUpdateHolidayClaim'])->name('employes.bulk-holiday-claim.update');

                    Route::resource('employes', EmployeController::class)->names([
                        'show' => 'employes.show',
                        'index' => 'employes.index',
                    ])->except('create');
                    Route::put('employes/{employe}/data/update', [EmployeController::class, 'updateData'])->name('employes.data.update');
                });


                //Urlaubsverwaltung
                Route::middleware(['permission:has holidays|approve holidays'])->group(function () {
                    // Spezifische Routen zuerst (vor parametrisierten Routen)
                    Route::get('holidays/manage', [HolidayController::class, 'manage'])->middleware(['permission:approve holidays']);
                    Route::post('holidays/manage/delete/{holiday}', [HolidayController::class, 'manageDelete'])->middleware(['permission:approve holidays']);
                    Route::get('holidays/export/{year?}/{group?}', [HolidayController::class, 'export']);
                    Route::get('holidays/{holiday}/delete', [HolidayController::class, 'delete']);
                    Route::get('holidays/{month?}/{year?}', [HolidayController::class, 'index']);

                    Route::resource('holidays', HolidayController::class);
                });


                //Timesheets
                Route::get('timesheets/update/employe/{user}', [TimesheetController::class, 'updateTimesheets']);
                Route::get('timesheets/{user}/login', [TimeRecordingController::class, 'checkin_checkout'])->middleware(['permission:has timesheet']);
                Route::get('timesheets/{user}/logout', [TimeRecordingController::class, 'checkin_checkout'])->middleware(['permission:has timesheet']);
                Route::get('timesheets/{user}/{timesheet}/lock', [TimesheetController::class, 'lock']);
                Route::get('timesheets/{user}/{timesheet}/unlock', [TimesheetController::class, 'unlock']);
                Route::get('timesheets/{user}/{timesheet}/update', [TimesheetController::class, 'updateSheet']);
                Route::get('timesheets/overview/{user}/', [TimesheetController::class, 'overviewTimesheetsUser']);


                Route::get('timesheets/select/employe', [TimesheetController::class, 'index']);
                Route::get('timesheets/{user}/{date?}', [TimesheetController::class, 'show']);
                Route::get('timesheets/{user}/export/{timesheet}', [TimesheetController::class, 'export']);
                Route::get('timesheets/{user}/{timesheet}/{month}/add', [TimesheetController::class, 'addDay']);
                Route::get('timesheets/day/{timesheetDay}/edit', [TimesheetController::class, 'editDay']);
                Route::put('timesheets/day/{timesheetDay}/edit', [TimesheetController::class, 'updateDay']);
                Route::get('timesheets/{user}/{timesheet}/{date}/addFromAbsence/{absence}', [TimesheetController::class, 'addFromAbsence']);
                Route::post('timesheets/{user}/{timesheet}/{date}/store', [TimesheetController::class, 'storeDay']);
                Route::get('timesheets/{user}/{timesheet}/{timesheetDay}/delete', [TimesheetController::class, 'deleteDay']);
                Route::post('timesheets/{user}/{timesheet}/{date}/apply-roster', [TimesheetController::class, 'applyRosterSuggestion']);

                //Anstellungen
                Route::post('employments/{employe}/add', [EmploymentController::class, 'store']);

                Route::post('addresses/{employe}', [AddressController::class, 'update']);


                Route::get('roster/{roster}/export/pdf', [RosterController::class, 'exportPDF'])->name('roster.export.pdf');

                Route::middleware(['permission:create roster'])->group(function () {
                    //Roster - Dienstpläne
                    Route::resource('roster', RosterController::class)
                        ->except(['create'])
                        ->names([
                            'index' => 'roster.index',
                            'show' => 'roster.show',
                        ]);
                    Route::get('roster/create/{department}', [RosterController::class, 'create'])->name('roster.create');
                    Route::delete('roster/{roster}', [RosterController::class, 'destroy'])->name('roster.delete');
                    Route::get('roster/{roster}/export/mail', [RosterController::class, 'sendRosterMail'])->name('roster.export.mail');
                    Route::get('roster/{roster}/export/nextcloud', [RosterController::class, 'sendRosterToNextcloudTalk'])->name('roster.export.nextcloud');
                    Route::get('roster/{roster}/exportEmploye/{employe}/pdf', [RosterController::class, 'exportPdfEmploye'])->name('roster.export.employe.pdf');
                    Route::get('roster/news/{news}/delete', [RosterNewsController::class, 'destroy'])->name('roster.news.delete');
                    Route::post('roster/{roster}/news/add', [RosterNewsController::class, 'store'])->name('roster.news.add');

                    Route::get('roster/{roster}/toggleView/{day}', [RosterController::class, 'toogleDayView'])->name('toggleDayView');

                    // Auto-Umplanung
                    Route::get('roster/{roster}/auto-plan', [RosterController::class, 'autoPlan'])->name('roster.autoPlan');
                    Route::post('roster/{roster}/auto-plan/apply', [RosterController::class, 'applyAutoPlan'])->name('roster.autoPlan.apply');
                    Route::get('roster/{roster}/auto-plan/undo', [RosterController::class, 'undoAutoPlan'])->name('roster.autoPlan.undo');

                    // Kalender-Import
                    Route::get('roster/{roster}/import-calendar', [RosterEventsController::class, 'importFromCalendarPreview'])->name('roster.importCalendar.preview');
                    Route::post('roster/{roster}/import-calendar', [RosterEventsController::class, 'importFromCalendar'])->name('roster.importCalendar.store');

                    // Task Requirements
                    Route::post('roster/{roster}/task-requirements', [\App\Http\Controllers\Personal\RosterTaskRequirementController::class, 'store'])->name('roster.taskRequirements.store');
                    Route::put('roster/task-requirements/{requirement}', [\App\Http\Controllers\Personal\RosterTaskRequirementController::class, 'update'])->name('roster.taskRequirements.update');
                    Route::delete('roster/task-requirements/{requirement}', [\App\Http\Controllers\Personal\RosterTaskRequirementController::class, 'destroy'])->name('roster.taskRequirements.destroy');

                    //Create Checks
                    Route::post('roster/checks', [RosterCheckController::class, 'storeCheck'])->name('roster.checks.store');
                    //Publish Roster
                    Route::get('roster/{roster}/publish', [RosterController::class, 'publish'])->name('roster.publish');

                    Route::post('working_time', [WorkingTimeController::class, 'store']);
                    Route::delete('roster/{roster}/trashDay', [RosterEventsController::class, 'trashDay']);
                    //events
                    Route::post('tasks/{roster}', [RosterEventsController::class, 'store']);
                    Route::get('tasks/{event}/remember', [RosterEventsController::class, 'remember']);
                    Route::put('tasks/{rosterEvent}', [RosterEventsController::class, 'update']);
                    Route::patch('tasks/update', [RosterEventsController::class, 'dropUpdate']);
                    Route::delete('tasks/{rosterEvent}', [RosterEventsController::class, 'destroy']);
                });

                // ── Hortstunden-Planung ───────────────────────────────────────────────
                Route::prefix('hort-planung')->middleware('permission:view hort planung')->group(function () {
                    Route::get('/', [HortPlanungController::class, 'index'])->name('hort-planung.index');

                    // ⚠ Statische Routen VOR der {planung}-Wildcard!
                    Route::middleware('permission:manage hort planung')->group(function () {
                        Route::get('/create', [HortPlanungController::class, 'create'])->name('hort-planung.create');
                        Route::post('/', [HortPlanungController::class, 'store'])->name('hort-planung.store');
                        Route::post('/import', [HortPlanungController::class, 'importExcel'])->name('hort-planung.import');
                        Route::post('/import/parse', [HortPlanungController::class, 'importParse'])->name('hort-planung.importParse');
                    });

                    // Parametrisierte Lese-Routen
                    Route::get('/{planung}', [HortPlanungController::class, 'show'])->name('hort-planung.show');
                    Route::get('/{planung}/export', [HortPlanungController::class, 'export'])->name('hort-planung.export');
                    Route::get('/{planung}/berechnungen', [HortPlanungController::class, 'berechnungen'])->name('hort-planung.berechnungen');
                    Route::get('/{planung}/trend', [HortPlanungController::class, 'trend'])->name('hort-planung.trend');
                    Route::get('/{planung}/rueckblick', [HortPlanungController::class, 'rueckblick'])->name('hort-planung.rueckblick');
                    Route::get('/{planung}/vertragsaenderungen', [HortPlanungController::class, 'vertragsaenderungen'])->name('hort-planung.vertragsaenderungen');
                    Route::get('/{planung}/vertragsaenderungen/export', [HortPlanungController::class, 'exportVertragsaenderungen'])->name('hort-planung.exportVertragsaenderungen');
                    Route::get('/{planung}/vergleich/{other}', [HortPlanungController::class, 'vergleich'])->name('hort-planung.vergleich');

                    // Schreib-Routen (manage-Permission)
                    Route::middleware('permission:manage hort planung')->group(function () {
                        Route::get('/{planung}/edit', [HortPlanungController::class, 'edit'])->name('hort-planung.edit');
                        Route::put('/{planung}', [HortPlanungController::class, 'update'])->name('hort-planung.update');
                        Route::delete('/{planung}', [HortPlanungController::class, 'destroy'])->name('hort-planung.destroy');
                        Route::put('/{planung}/monat/{monat}', [HortPlanungController::class, 'updateMonat'])->name('hort-planung.updateMonat');
                        Route::put('/{planung}/person/{person}', [HortPlanungController::class, 'updatePerson'])->name('hort-planung.updatePerson');
                        Route::put('/{planung}/person/{user}/bulk', [HortPlanungController::class, 'bulkUpdatePerson'])->name('hort-planung.bulkUpdatePerson');
                        Route::post('/{planung}/person', [HortPlanungController::class, 'addPerson'])->name('hort-planung.addPerson');
                        Route::delete('/{planung}/person/{user}', [HortPlanungController::class, 'removePerson'])->name('hort-planung.removePerson');

                        // Faktoren-CRUD
                        Route::post('/{planung}/faktor', [HortPlanungController::class, 'storeFaktor'])->name('hort-planung.storeFaktor');
                        Route::put('/{planung}/faktor/{faktor}', [HortPlanungController::class, 'updateFaktor'])->name('hort-planung.updateFaktor');
                        Route::delete('/{planung}/faktor/{faktor}', [HortPlanungController::class, 'deleteFaktor'])->name('hort-planung.deleteFaktor');
                        Route::post('/{planung}/faktor/{faktor}/wert', [HortPlanungController::class, 'storeFaktorWert'])->name('hort-planung.storeFaktorWert');
                        Route::delete('/{planung}/faktor-wert/{wert}', [HortPlanungController::class, 'deleteFaktorWert'])->name('hort-planung.deleteFaktorWert');

                        // Zusatzstunden-Typen-CRUD
                        Route::post('/{planung}/zusatztyp', [HortPlanungController::class, 'storeZusatzTyp'])->name('hort-planung.storeZusatzTyp');
                        Route::put('/{planung}/zusatztyp/{typ}', [HortPlanungController::class, 'updateZusatzTyp'])->name('hort-planung.updateZusatzTyp');
                        Route::delete('/{planung}/zusatztyp/{typ}', [HortPlanungController::class, 'deleteZusatzTyp'])->name('hort-planung.deleteZusatzTyp');
                        Route::put('/{planung}/monat/{monat}/zusatz/{typ}', [HortPlanungController::class, 'updateMonatZusatz'])->name('hort-planung.updateMonatZusatz');

                        // Import / Sync / Szenarien
                        Route::post('/{planung}/import-employments', [HortPlanungController::class, 'importEmployments'])->name('hort-planung.importEmployments');
                        Route::post('/{planung}/sync-ist', [HortPlanungController::class, 'syncIstStunden'])->name('hort-planung.syncIstStunden');
                        Route::post('/{planung}/sync-vertrag', [HortPlanungController::class, 'syncVertrag'])->name('hort-planung.syncVertrag');
                        Route::post('/{planung}/duplicate', [HortPlanungController::class, 'duplicate'])->name('hort-planung.duplicate');
                        Route::post('/{planung}/vertragsaenderungen/apply', [HortPlanungController::class, 'applyVertragsaenderungen'])->name('hort-planung.applyVertragsaenderungen');
                        Route::post('/{planung}/snapshot', [HortPlanungController::class, 'snapshot'])->name('hort-planung.snapshot');
                        Route::get('/{planung}/snapshot/{snapshot}/export', [HortPlanungController::class, 'exportSnapshot'])->name('hort-planung.exportSnapshot');
                        Route::post('/{planung}/snapshot/{snapshot}/restore', [HortPlanungController::class, 'restoreSnapshot'])->name('hort-planung.restoreSnapshot');
                        Route::delete('/{planung}/snapshot/{snapshot}', [HortPlanungController::class, 'deleteSnapshot'])->name('hort-planung.deleteSnapshot');
                    });
                });
                Route::prefix('rooms')->middleware('permission:view roomBooking')->group(function () {
                    Route::get('rooms/{room}/edit', [RoomController::class, 'edit'])->middleware('permission:manage rooms');
                    Route::get('rooms/{room}/export', [RoomController::class, 'export']);
                    Route::get('rooms/{room}/{week?}/{date?}', [RoomController::class, 'show'])->name('rooms.show.week');
                    Route::post('bookings', [RoomController::class, 'storeBooking']);
                    Route::get('availability/{room}', [RoomController::class, 'availability'])->name('rooms.availability');
                    Route::get('booking/{booking}', [RoomController::class, 'editBooking']);
                    Route::delete('booking/{booking}', [RoomController::class, 'deleteBooking']);
                    Route::put('bookings/{booking}', [RoomController::class, 'updateBooking']);
                    Route::post('import', [RoomController::class, 'import'])->middleware('permission:manage rooms');

                    // Resource-Route zuletzt
                    Route::resource('rooms', RoomController::class)->except('create', 'show');

                    // Admin: generate/revoke calendar feed token for a room
                    Route::post('rooms/{room}/feed/generate', [RoomController::class, 'generateFeedToken'])->middleware('permission:manage rooms')->name('rooms.feed.generate');
                    Route::post('rooms/{room}/feed/revoke', [RoomController::class, 'revokeFeedToken'])->middleware('permission:manage rooms')->name('rooms.feed.revoke');
                });


                //Wochenplan
                Route::group(['middleware' => ['permission:create Wochenplan']], function () {
                    Route::resource('{groupname}/wochenplan', WochenplanController::class);
                    Route::post('wochenplan/{wochenplan}/addfile', [WochenplanController::class, 'addFile']);
                    Route::post('wprow/{wochenplan}', [WPRowsController::class, 'store']);
                    Route::delete('wprow/{wprow}/remove', [WPRowsController::class, 'destroy']);
                    Route::delete('wochenplan/media/{media}/remove', [WochenplanController::class, 'removeFile']);
                    Route::delete('wochenplan/{wochenplan}/remove', [WochenplanController::class, 'destroy']);
                    Route::delete('wptask/{wptask}/remove', [WpTaskController::class, 'destroy']);
                    Route::post('wptask/{wprow}/addTask', [WpTaskController::class, 'store']);
                    Route::get('wptask/{wprow}/addTask', [WpTaskController::class, 'create']);
                    Route::get('wptask/{wptask}/edit', [WpTaskController::class, 'edit']);
                    Route::put('wptask/{wpTask}/edit', [WpTaskController::class, 'update']);
                    Route::get('wochenplan/{wochenplan}/export', [WochenplanController::class, 'export']);
                });

                // ─── Neues Wochenplan-System (/wp) ────────────────────────────────────────
                Route::prefix('wp')->name('wp.')->group(function () {

                    // Lesbar für view wochenplan UND create wochenplan (auch großes W für Rückwärtskompatibilität)
                    Route::middleware('permission:view wochenplan|create wochenplan|create Wochenplan')->group(function () {
                        Route::get('/', [WpPlanController::class, 'index'])->name('index');
                        Route::get('/klasse/{klasse}', [WpPlanController::class, 'indexKlasse'])->name('index.klasse');
                        Route::get('/hilfe', [WpPlanController::class, 'hilfe'])->name('hilfe');
                        // Export
                        Route::prefix('export')->name('export.')->group(function () {
                            Route::get('{wpPlan}/pdf', [WpExportController::class, 'pdf'])->name('pdf');
                            Route::get('{wpPlan}/word', [WpExportController::class, 'word'])->name('word');
                            Route::get('{wpPlan}/vorschau', [WpExportController::class, 'vorschau'])->name('vorschau');
                        });
                    });

                    // Schreibzugriff (create wochenplan oder altes create Wochenplan)
                    Route::middleware('permission:create wochenplan|create Wochenplan')->group(function () {
                        Route::get('/create', [WpPlanController::class, 'create'])->name('create');
                        Route::post('/', [WpPlanController::class, 'store'])->name('store');
                        Route::get('/{wpPlan}/edit', [WpPlanController::class, 'edit'])->name('edit');
                        Route::put('/{wpPlan}', [WpPlanController::class, 'update'])->name('update');
                        Route::delete('/{wpPlan}', [WpPlanController::class, 'destroy'])->name('destroy');

                        // Plan-Aktionen
                        Route::post('/{wpPlan}/duplizieren', [WpPlanController::class, 'duplizieren'])->name('duplizieren');
                        Route::get('/{wpPlan}/schuelerplan/create', [WpPlanController::class, 'createSchuelerplan'])->name('schuelerplan.create');
                        Route::post('/{wpPlan}/schuelerplan', [WpPlanController::class, 'storeSchuelerplan'])->name('schuelerplan.store');

                        // Fächer im Plan
                        Route::post('/{wpPlan}/fach', [WpPlanController::class, 'addFach'])->name('fach.add');
                        Route::delete('/fach/{wpPlanFach}', [WpPlanController::class, 'removeFach'])->name('fach.remove');
                        Route::post('/fach/reorder', [WpPlanController::class, 'reorderFaecher'])->name('fach.reorder');

                        // Aufgaben
                        Route::post('/fach/{wpPlanFach}/aufgabe', [WpAufgabeController::class, 'store'])->name('aufgabe.store');
                        Route::post('/aufgabe/aus-tagebuch/{wpPlanFach}', [WpAufgabeController::class, 'storeFromDiaryTask'])->name('aufgabe.from-diary');
                        Route::post('/aufgabe/reorder', [WpAufgabeController::class, 'reorder'])->name('aufgabe.reorder');
                        Route::put('/aufgabe/{wpAufgabe}', [WpAufgabeController::class, 'update'])->name('aufgabe.update');
                        Route::delete('/aufgabe/{wpAufgabe}', [WpAufgabeController::class, 'destroy'])->name('aufgabe.destroy');

                        // Tägliche Übungen
                        Route::post('/{wpPlan}/taegliche-uebungen/toggle', [WpTaeglicheUebungController::class, 'toggle'])->name('taegliche-uebungen.toggle');
                        Route::post('/{wpPlan}/taegliche-uebungen', [WpTaeglicheUebungController::class, 'store'])->name('taegliche-uebungen.store');
                        Route::put('/taegliche-uebungen/{wpTaeglicheUebung}', [WpTaeglicheUebungController::class, 'update'])->name('taegliche-uebungen.update');
                        Route::delete('/taegliche-uebungen/{wpTaeglicheUebung}', [WpTaeglicheUebungController::class, 'destroy'])->name('taegliche-uebungen.destroy');

                        // Synchronisation
                        Route::post('/{wpPlan}/sync/fach/{fachId}', [WpSyncController::class, 'syncFach'])->name('sync.fach');
                        Route::post('/{wpPlan}/sync/all', [WpSyncController::class, 'syncAll'])->name('sync.all');

                        // Medien (Arbeitsblätter)
                        Route::post('/{wpPlan}/media', [WpPlanController::class, 'addMedia'])->name('media.add');
                        Route::delete('/media/{media}', [WpPlanController::class, 'removeMedia'])->name('media.remove');
                        Route::post('/{wpPlan}/media/sync', [WpPlanController::class, 'syncMedia'])->name('media.sync');

                        // Vorlagen
                        Route::prefix('vorlagen')->name('vorlagen.')->group(function () {
                            Route::get('/', [WpVorlageController::class, 'index'])->name('index');
                            Route::post('/{wpPlan}/speichern', [WpVorlageController::class, 'alsVorlageSpeichern'])->name('speichern');
                            Route::post('/{wpPlan}/erstellen', [WpVorlageController::class, 'vonVorlageErstellen'])->name('erstellen');
                            Route::delete('/{wpPlan}', [WpVorlageController::class, 'destroy'])->name('destroy');
                        });
                    });

                    // Fächer-Katalog (manage wochenplan-faecher)
                    Route::middleware('permission:manage wochenplan-faecher')
                        ->prefix('faecher')->name('faecher.')->group(function () {
                            Route::get('/', [WpFachController::class, 'index'])->name('index');
                            Route::post('/', [WpFachController::class, 'store'])->name('store');
                            Route::post('/{wpFach}', [WpFachController::class, 'update'])->name('update');
                            Route::delete('/{wpFach}', [WpFachController::class, 'destroy'])->name('destroy');
                        });

                    // Formatvorlagen (manage wochenplan-formatvorlagen)
                    Route::middleware('permission:manage wochenplan-formatvorlagen')
                        ->prefix('formatvorlagen')->name('formatvorlagen.')->group(function () {
                            Route::get('/', [WpFormatvorlageController::class, 'index'])->name('index');
                            Route::get('/create', [WpFormatvorlageController::class, 'create'])->name('create');
                            Route::post('/', [WpFormatvorlageController::class, 'store'])->name('store');
                            Route::get('/{wpFormatvorlage}/edit', [WpFormatvorlageController::class, 'edit'])->name('edit');
                            Route::put('/{wpFormatvorlage}', [WpFormatvorlageController::class, 'update'])->name('update');
                            Route::delete('/{wpFormatvorlage}', [WpFormatvorlageController::class, 'destroy'])->name('destroy');
                            Route::get('/{wpFormatvorlage}/vorschau', [WpFormatvorlageController::class, 'vorschau'])->name('vorschau');
                            Route::post('/vorschau-html', [WpFormatvorlageController::class, 'vorschauHtml'])->name('vorschau-html');
                            Route::post('/{wpFormatvorlage}/kopieren', [WpFormatvorlageController::class, 'kopieren'])->name('kopieren');
                        });
                });

                //Klassen
                Route::group(['middleware' => ['permission:edit klassen']], function () {
                    Route::resource('klassen', KlasseController::class);
                    // Schüler Verwaltung
                    Route::get('schueler/import', [SchuelerController::class, 'importForm'])->name('schueler.import.form');
                    Route::post('schueler/import', [SchuelerController::class, 'import'])->name('schueler.import');
                    Route::post('klassen/{klasse}/schueler', [SchuelerController::class, 'store'])->name('schueler.store');
                    Route::get('schueler/{schueler}/edit', [SchuelerController::class, 'edit'])->name('schueler.edit');
                    Route::put('schueler/{schueler}', [SchuelerController::class, 'update'])->name('schueler.update');
                    Route::delete('schueler/{schueler}', [SchuelerController::class, 'destroy'])->name('schueler.destroy');
                });

                // Zeitraster-Verwaltung (TODO-10)
                Route::middleware('permission:manage zeitraster')->group(function () {
                    Route::resource('zeitraster', \App\Http\Controllers\ZeitrasterController::class)
                        ->except('show');
                    Route::post('zeitraster/{zeitraster}/standard',
                        [\App\Http\Controllers\ZeitrasterController::class, 'markStandard'])
                        ->name('zeitraster.markStandard');
                });

                //absences
                Route::middleware(['permission:view absences'])->group(function () {
                    Route::get('absences', [AbsenceController::class, 'index'])->middleware(['permission:view old absences']);
                    Route::post('absences', [AbsenceController::class, 'store']);
                    Route::get('absences/export', [AbsenceController::class, 'export'])->middleware(['permission:export absence']);
                    Route::get('absences/{absence}/delete', [AbsenceController::class, 'delete']);
                    Route::get('absences/abo/{type}', [AbsenceController::class, 'abo']);
                });

                Route::middleware(['permission:manage sick_notes'])->group(function () {
                    Route::get('sick_notes', [AbsenceController::class, 'sick_notes_index']);
                    Route::get('sick_notes/export', [AbsenceController::class, 'sick_notes_export']);
                    Route::get('sick_notes/export/user/{user}', [AbsenceController::class, 'sick_notes_export_user']);
                    Route::get('sick_notes/{absence}/set_note_date', [AbsenceController::class, 'sick_notes_update']);
                    Route::get('sick_notes/{absence}/sick_note_remove', [AbsenceController::class, 'sick_notes_remove']);
                });

                //Inventar
                Route::prefix('inventory')->middleware(['permission:edit inventar'])->group(function () {
                    Route::get('locations/import', [LocationController::class, 'showImport']);
                    Route::post('locations/import', [LocationController::class, 'import']);
                    Route::post('locations/print', [LocationController::class, 'print']);

                    Route::post('items/search', [ItemsController::class, 'index']);
                    Route::post('items/print', [ItemsController::class, 'print']);
                    Route::post('items/import', [ItemsController::class, 'import']);

                    Route::get('items/import', [ItemsController::class, 'showImport']);

                    Route::resource('locations', LocationController::class);
                    Route::resource('lieferanten', \App\Http\Controllers\Inventory\LieferantController::class);
                    Route::resource('items', ItemsController::class);
                    Route::resource('categories', \App\Http\Controllers\Inventory\CategoryController::class)->names([
                        'index' => 'inventory.categories.index',
                        'store' => 'inventory.categories.store',
                        'show' => 'inventory.categories.show',
                        'update' => 'inventory.categories.update',
                        'destroy' => 'inventory.categories.destroy',
                    ]);
                    Route::resource('locationtype', LocationTypeController::class);

                });

                //Vertretungen planen
                Route::group(['middleware' => ['permission:edit vertretungen']], function () {
                    Route::get('vertretungen', [VertretungController::class, 'edit']);
                    Route::get('vertretungen/archiv/{dateStart?}/{dateEnd?}', [VertretungController::class, 'archiv']);
                    Route::post('vertretungen', [VertretungController::class, 'store']);
                    Route::post('vertretungen/createPDF', [VertretungController::class, 'exportPDF']);
                    Route::post('export/vertretungen', [VertretungController::class, 'export']);
                    Route::get('vertretungen/{vertretung}/copy', [VertretungController::class, 'copy']);
                    Route::get('vertretungen/{vertretung}/edit', [VertretungController::class, 'edit']);
                    Route::put('vertretungen/{vertretung}', [VertretungController::class, 'update']);
                    Route::delete('vertretungen/{vertretung}', [VertretungController::class, 'destroy']);
                    Route::get('vertretungen/{date}/generate-doc', [VertretungController::class, 'generateDoc']);
                    Route::get('vertretungen/{startDate}/generate-pdf/{endDate?}', [VertretungController::class, 'generatePDF']);
                    Route::post('dailyNews', [DailyNewsController::class, 'store']);
                    Route::get('dailyNews', [DailyNewsController::class, 'index']);
                    Route::delete('dailyNews/{dailyNews}', [DailyNewsController::class, 'destroy']);
                    Route::get('weeks', [VertretungsplanWeekController::class, 'index']);
                    Route::get('weeks/change/{week}', [VertretungsplanWeekController::class, 'update']);
                    Route::delete('weeks/delete/{week}', [VertretungsplanWeekController::class, 'destroy']);

                    //Abwesenheiten Vertretungsplan
                    Route::get('abwesenheiten', [VertretungsplanAbsenceController::class, 'index'])->name('vertretungsplan.absences.index');
                    Route::post('abwesenheiten', [VertretungsplanAbsenceController::class, 'store']);
                    Route::delete('vertretungsplan/abwesenheit/{absence}/delete', [VertretungsplanAbsenceController::class, 'destroy']);
                });

                //Subscriptions
                Route::get('subscription/{type}/{id}', [SubscriptionController::class, 'add']);
                Route::get('subscription/{type}/{id}/remove', [SubscriptionController::class, 'remove']);

                Route::get('/home', [HomeController::class, 'index'])->name('home');
                Route::get('/', [HomeController::class, 'index']);
                Route::post('cards/disable', [DashboardController::class, 'disableCard']);

                //Posts
                Route::resource('posts', PostsController::class);
                Route::get('posts/{post}/release', [PostsController::class, 'release']);
                Route::get('posts/{post}/archive', [PostsController::class, 'archive'])->middleware('permission:create posts');


                //globale Suche
                Route::post('search/search', [SearchController::class, 'searchGlobal']);
                Route::get('search', [SearchController::class, 'globalSearch']);


                //recurring Themes
                Route::middleware('permission:manage recurring themes')->group(function () {
                    Route::resource('{groupname}/themes/recurring', RecurringThemeController::class)->except('show');
                    Route::get('{groupname}/themes/recurring/file/{media}/delete', [ImageController::class, 'removeImage']);
                    Route::get('themes/recurring/start/{now?}', [RecurringThemeController::class, 'createNewThemes']);
                });

                //Meetings
                Route::get('{group}/meetings', [MeetingController::class, 'index'])->name('meetings.index');
                Route::post('{group}/meetings/store', [MeetingController::class, 'store'])->name('meetings.store');
                Route::get('{group}/meetings/{meeting}/edit', [MeetingController::class, 'edit'])->name('meetings.edit');
                Route::put('{group}/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
                Route::post('{group}/meetings/{meeting}/cancel', [MeetingController::class, 'cancelMeeting'])->name('meetings.cancel');
                Route::post('{group}/meetings/{meeting}/reactivate', [MeetingController::class, 'reactivateMeeting'])->name('meetings.reactivate');
                Route::delete('{group}/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
                Route::get('{groupname}/meetings/past', [\App\Http\Controllers\MeetingController::class, 'past'])->name('meetings.past');

                //Meeting-Themen anlegen/zuweisen
                Route::post('{group}/meetings/{meeting}/themes', [App\Http\Controllers\MeetingController::class, 'storeTheme'])->name('meetings.themes.store');
                // Thema von Meeting entfernen
                Route::delete('{group}/meetings/{meeting}/themes/{theme}', [App\Http\Controllers\MeetingController::class, 'removeTheme'])->name('meetings.themes.remove');
                Route::post('{group}/meetings/{meeting}/invite', [App\Http\Controllers\MeetingController::class, 'sendInvitation'])->name('meetings.invite');

                // Aufgaben-Management für Meetings
                Route::get('{group}/meetings/{meeting}/tasks', [MeetingController::class, 'tasks'])->name('meetings.tasks');
                Route::post('{group}/meetings/{meeting}/tasks', [MeetingController::class, 'addTask'])->name('meetings.tasks.add');
                Route::put('{group}/meetings/{meeting}/tasks/{task}', [MeetingController::class, 'updateTask'])->name('meetings.tasks.update');
                Route::delete('{group}/meetings/{meeting}/tasks/{task}', [MeetingController::class, 'deleteTask'])->name('meetings.tasks.delete');
                Route::post('{group}/assign-themes/{meeting}', [\App\Http\Controllers\MeetingController::class, 'assignAllThemesForDate'])->name('meetings.assignThemes');


                //Themes
                // Verwaltung archivierter (soft-gelöschter) Theme-Dateien (vor der Resource registrieren!)
                Route::middleware('permission:unarchive theme')->group(function () {
                    Route::get('themes/archived-files', [ThemeController::class, 'archivedFiles'])->name('themes.archivedFiles');
                    Route::put('themes/archived-files/{media}/restore', [ThemeController::class, 'restoreFile'])->name('themes.files.restore');
                    Route::delete('themes/archived-files/{media}', [ThemeController::class, 'forceDeleteFile'])->name('themes.files.forceDelete');
                });
                Route::resource('{groupname}/themes', ThemeController::class);
                Route::get('{groupname}/themes/create/{speicher?}', [ThemeController::class, 'create']);
                Route::post('{groupname}/move/themes', [ThemeController::class, 'moveAllThemes']);
                Route::get('{groupname}/move/theme/{theme}/{newDate}/{redirect}', [ThemeController::class, 'move']);
                Route::get('{groupname}/memory/{theme}', [ThemeController::class, 'memoryTheme']);
                Route::get('{groupname}/memory', [ThemeController::class, 'memory']);
                Route::get('{groupname}/view/{viewType}', [ThemeController::class, 'setView']);
                Route::get('{groupname}/archive/{month?}', [ThemeController::class, 'archive']);
                Route::get('unarchiv/{theme}', [ThemeController::class, 'unArchive'])->middleware('permission:unarchive theme');
                Route::get('{groupname}/themes/{theme}/close', [ThemeController::class, 'closeTheme']);
                Route::get('{groupname}/themes/{theme}/activate', [ThemeController::class, 'activate']);
                // Datei eines Themas archivieren (Soft-Löschen) + Protokollvermerk
                Route::delete('{groupname}/themes/{theme}/files/{media}', [ThemeController::class, 'archiveFile'])->name('themes.files.archive');
                Route::post('share/{theme}', [ShareController::class, 'shareTheme']);
                Route::get('theme/{theme}/assign/{user}', [ThemeController::class, 'assgin_to']);
                Route::get('theme/{theme}/change/group/{group}', [ThemeController::class, 'change_group']);
                Route::delete('share/{theme}', [ShareController::class, 'removeShare']);

                //Surveys
                Route::get('{groupname}/themes/{theme}/survey/create', [SurveyController::class, 'create']);
                Route::post('{groupname}/themes/{theme}/survey/store', [SurveyController::class, 'store'])->name('survey.store');
                Route::get('/survey/{survey}/edit', [SurveyController::class, 'edit'])->name('survey.edit');
                Route::put('/survey/{survey}', [SurveyController::class, 'update'])->name('survey.update');
                Route::delete('/survey/{survey}', [SurveyController::class, 'destroy'])->name('survey.destroy');
                Route::get('{groupname}/themes/{theme}/survey/{survey}', [SurveyController::class, 'show'])->name('survey.show');
                Route::post('survey/{survey}/store/question', [SurveyController::class, 'storeQuestion'])->name('survey.question.store');
                Route::delete('survey/{survey}/delete/question/{question}', [SurveyController::class, 'destroyQuestion'])->name('survey.question.destroy');
                Route::post('survey/{survey}/question/{question}/add/answer', [SurveyController::class, 'storeAnswer'])->name('survey.answer.store');
                Route::post('survey/{survey}/answer', [SurveyController::class, 'answer'])->name('survey.submit');

                //Anwesenheit
                Route::get('{groupname}/presence/{date?}', [PresenceController::class, 'index']);
                Route::post('{groupname}/presences/add', [PresenceController::class, 'store']);
                Route::post('{groupname}/presences/addGuest', [PresenceController::class, 'addGuest']);
                Route::get('{groupname}/presences/{presence}/deleteGuest', [PresenceController::class, 'deleteGuest']);

                //Prioritäten
                Route::post('priorities', [PriorityController::class, 'store']);
                Route::get('priorities/{theme}', [PriorityController::class, 'delete'])->name('priorities.delete');

                //Protocols
                Route::get('{groupname}/protocols/{theme}', [ProtocolController::class, 'create']);
                Route::post('{groupname}/protocols/{theme}', [ProtocolController::class, 'store']);
                Route::get('{groupname}/protocols/{protocol}/edit', [ProtocolController::class, 'edit']);
                Route::get('{groupname}/export/{date?}/', [ProtocolController::class, 'showDailyProtocol']);
                Route::post('{groupname}/export/{date}/download', [ProtocolController::class, 'createSheet']);
                Route::post('{groupname}/export/{date}/pdf', [ProtocolController::class, 'exportPdf']);
                Route::put('{groupname}/protocols/{protocol}/', [ProtocolController::class, 'update']);

                Route::post('{groupname}/search', [SearchController::class, 'search']);
                Route::get('{groupname}/search', [SearchController::class, 'show']);


                Route::get('image/remove/{groupname}/{media}', [ImageController::class, 'removeImage']);
                Route::delete('image/{media}', [ImageController::class, 'removeImageFromPost']);

                //Roles and permissions
                Route::group(['middleware' => ['permission:edit permissions']], function () {
                    Route::get('roles', [RolesController::class, 'edit']);
                    Route::get('roles/{role_id}/remove/{rolename}', [RolesController::class, 'delete']);
                    Route::put('roles', [RolesController::class, 'update']);
                    Route::post('roles', [RolesController::class, 'store']);
                    Route::post('roles/permission', [RolesController::class, 'storePermission']);
                    Route::post('roles/assign-to-group', [RolesController::class, 'assignToGroup']);

                    Route::get('user', [UserController::class, 'index']);
                });

                //themeTypes
                Route::group(['middleware' => ['permission:create types']], function () {
                    Route::get('types', [\App\Http\Controllers\TypController::class, 'index']);
                    Route::post('types', [\App\Http\Controllers\TypController::class, 'store']);
                });

                //User-Route
                Route::resource('users', UserController::class);
                Route::get('importuser', [UserController::class, 'importFromElternInfoBoard']);
                Route::post('import/users', [UserController::class, 'importFromXLS']);
                Route::get('import/users/file', [UserController::class, 'downloadImportFile']);
                Route::get('import/users', [UserController::class, 'import']);

                Route::get('users/restore/{user_id}', [UserController::class, 'restore']);


                //Gruppen-Route
                Route::get('groups', [GroupController::class, 'index']);
                Route::get('groups/{group}/edit', [GroupController::class, 'edit']);
                Route::post('groups', [GroupController::class, 'store']);
                Route::patch('groups/{group}', [GroupController::class, 'update']);
                Route::put('{groupname}/addUser', [GroupController::class, 'addUser']);
                Route::delete('{groupname}/removeUser', [GroupController::class, 'removeUser']);


                //Tasks
                Route::post('{groupname}/{theme}/tasks', [TaskController::class, 'store']);
                Route::get('tasks/{task}/complete', [TaskController::class, 'complete']);

                //Push-Notification
                Route::post('{groupname?}/push', [PushController::class, 'store']);
                Route::post('push', [PushController::class, 'store']);

                Route::group(['middlewareGroups' => ['role:Admin']], function () {
                    Route::get('showUser/{id}', [UserController::class, 'loginAsUser']);
                });

                Route::get('logoutAsUser', function () {
                    if (session()->has('ownID')) {
                        \Illuminate\Support\Facades\Auth::loginUsingId(session()->pull('ownID'));
                    }

                    return redirect(url('/'));
                });

                //Terminlisten
                Route::get('listen', [ListenController::class, 'index']);
                Route::post('listen', [ListenController::class, 'store']);
                Route::get('listen/create', [ListenController::class, 'create']);
                Route::get('listen/{terminListe}', [ListenController::class, 'show']);
                Route::get('listen/{terminListe}/edit', [ListenController::class, 'edit']);
                Route::put('listen/{terminListe}', [ListenController::class, 'update']);
                Route::get('listen/{liste}/activate', [ListenController::class, 'activate']);
                Route::get('listen/{liste}/refresh', [ListenController::class, 'refresh']);
                Route::get('listen/{liste}/archiv', [ListenController::class, 'archiv']);
                Route::get('listen/{liste}/deactivate', [ListenController::class, 'deactivate']);
                Route::get('listen/{liste}/export', [ListenController::class, 'pdf']);
                Route::get('listen/{terminListe}/auswahl', [ListenController::class, 'auswahl']);
                Route::post('eintragungen/{liste}/store', [ListenTerminController::class, 'store']);
                Route::put('eintragungen/{listen_termine}', [ListenTerminController::class, 'update']);
                Route::delete('eintragungen/{listen_termine}', [ListenTerminController::class, 'destroy']);
                Route::delete('eintragungen/absagen/{listen_termine}', [ListenTerminController::class, 'absagen']);

                //Prozesse
                Route::prefix('procedure')->group(function () {
                    Route::get('/', [ProcedureController::class, 'index']);

                    // ─── Phase 4: Legacy-URLs → HTTP-Redirect (kein JS-Umweg mehr) ───
                    Route::get('/template',   fn() => redirect(url('procedure') . '#templates'));
                    Route::get('/recurring',  fn() => redirect(url('procedure') . '#automation'));
                    Route::get('/positions',  fn() => redirect(url('procedure') . '#automation'));

                    Route::post('/recurring', [RecurringProcedureController::class, 'store']);
                    Route::delete('/recurring/{recurringProcedure}', [RecurringProcedureController::class, 'destroy']);
                    Route::get('/recurring/{recurringProcedure}/start/{redirect?}', [RecurringProcedureController::class, 'start']);

                    //Procedures
                    Route::post('create/template', [ProcedureController::class, 'storeTemplate']);
                    Route::get('{procedure}/edit', [ProcedureController::class, 'edit']);
                    Route::get('{procedure}/start', [ProcedureController::class, 'start']);
                    // ─── Phase 4: GET-Mutation entfernt – jetzt POST ─────────────────
                    Route::post('{procedure}/end', [ProcedureController::class, 'endProcedure']);
                    Route::post('{procedure}/start', [ProcedureController::class, 'startNow']);
                    Route::put('{procedure}/update', [ProcedureController::class, 'updateProcedure']);
                    Route::get('step/{step}/edit', [ProcedureController::class, 'editStep']);
                    Route::delete('step/{step}/delete', [ProcedureController::class, 'destroy']);
                    Route::put('step/{step}', [ProcedureController::class, 'storeStep']);
                    Route::post('step/addUser', [ProcedureController::class, 'addUser']);

                    //Step
                    Route::post('{procedure}/step', [ProcedureController::class, 'addStep']);
                    Route::put('step/{step}/done', [ProcedureController::class, 'done']);
                    // Phase 4: GET /stepMail entfernt – Erinnerung läuft nur via Scheduler

                    // REST-konforme Routen (Phase 1 – jetzt primär)
                    Route::delete('{procedure}', [ProcedureController::class, 'delete']);
                    Route::delete('step/{step}/users/{user}', [ProcedureController::class, 'removeUser']);
                    Route::post('recurring/{recurringProcedure}/trigger', [RecurringProcedureController::class, 'start']);

                    //positions – GET /positions ist jetzt Redirect (oben definiert)
                    Route::post('/positions/{position}/add', [PositionsController::class, 'addUser']);
                    // Phase 4: GET /positions/remove → DELETE
                    Route::delete('/positions/{positions}/remove/{users}', [PositionsController::class, 'removeUser']);

                    //Categories
                    Route::post('categories', [CategoryController::class, 'store']); //Categories
                    Route::post('position', [PositionsController::class, 'store']);

                    // ─── Phase 1: JSON-API (Read-only, für neues Tailwind/Alpine-Frontend) ───
                    Route::prefix('api')->group(function () {
                        Route::get('templates',  [\App\Http\Controllers\Procedure\ProcedureApiController::class, 'templates']);
                        Route::get('active',     [\App\Http\Controllers\Procedure\ProcedureApiController::class, 'active']);
                        Route::get('categories', [\App\Http\Controllers\Procedure\ProcedureApiController::class, 'categories']);
                        Route::get('positions',  [\App\Http\Controllers\Procedure\ProcedureApiController::class, 'positions']);
                        Route::get('recurring',  [\App\Http\Controllers\Procedure\ProcedureApiController::class, 'recurring']);
                        Route::get('steps/{step}/history', [\App\Http\Controllers\Procedure\ProcedureApiController::class, 'stepHistory']);
                    });

                    // ─── Phase 1: Kommentare an Schritten (§8.3) ───────────────
                    Route::get('steps/{step}/comments',         [\App\Http\Controllers\Procedure\ProcedureStepCommentController::class, 'index']);
                    Route::post('steps/{step}/comments',        [\App\Http\Controllers\Procedure\ProcedureStepCommentController::class, 'store']);
                    Route::delete('steps/{step}/comments/{comment}', [\App\Http\Controllers\Procedure\ProcedureStepCommentController::class, 'destroy']);

                    // ─── Phase 1: Kategorie-Verwaltung (B-23/B-24) ─────────────
                    Route::put('categories/{category}',    [\App\Http\Controllers\Procedure\ProcedureCategoryController::class, 'update']);
                    Route::delete('categories/{category}', [\App\Http\Controllers\Procedure\ProcedureCategoryController::class, 'destroy']);

                    // ─── Phase 1: Wiederkehrende Prozesse – Pause/Aktivieren (B-30) ───
                    Route::patch('recurring/{recurringProcedure}/toggle', [RecurringProcedureController::class, 'toggle']);

                    // ─── Phase 3: Schritt AJAX (complete/reopen/move/reorder) ──────
                    Route::post('steps/{step}/complete', [\App\Http\Controllers\Procedure\ProcedureStepController::class, 'complete']);
                    Route::post('steps/{step}/reopen',   [\App\Http\Controllers\Procedure\ProcedureStepController::class, 'reopen']);
                    Route::patch('steps/{step}/move',    [\App\Http\Controllers\Procedure\ProcedureStepController::class, 'move']);
                    Route::post('steps/reorder',         [\App\Http\Controllers\Procedure\ProcedureStepController::class, 'reorder']);

                    // ─── Phase 3: Vorlage duplizieren (B-05) ─────────────────────
                    Route::post('templates/{procedure}/clone', [\App\Http\Controllers\Procedure\ProcedureTemplateController::class, 'clone']);
                });

                /*
                 * Edit Settings
                 */
                Route::middleware(['permission:edit settings'])->group(callback: function () {
                    Route::get('settings/{modulname}', [SettingController::class, 'index']);


                    Route::resource('settings', SettingController::class)->only(['index', 'store']);

                    Route::put('employes/{employe}/data/update', [EmployeController::class, 'updateData'])->name('employes.data.update');
                });


                /*
                 * Routes for Logs
                 */
                Route::middleware(['permission:view logs'])->group(function () {
                    Route::get('logs', [LogController::class, 'index']);
                    Route::get('logs/download', [LogController::class, 'download'])->name('logs.download');
                    Route::get('logs/set_filter/{filter}', [LogController::class, 'set_filter'])->name('logs.set_filter');
                });

                // Pädagogisches Tagebuch
                Route::middleware(['permission:view paed diary'])->group(function () {
                    //V1 - view
                    Route::get('paed-diary/v1', [\App\Http\Controllers\PaedDiaryController::class, 'index'])->name('paedDiary.v1.index');

                    // v2: Blade + Alpine.js Frontend (parallel zum bestehenden)
                    Route::get('paed-diary/', [\App\Http\Controllers\PaedDiaryController::class, 'indexV2'])->name('paedDiary.index');

                   Route::get('paed-diary/week', [\App\Http\Controllers\PaedDiaryController::class, 'weekData'])->name('paedDiary.week');
                    Route::get('paed-diary/cell-entries', [\App\Http\Controllers\PaedDiaryController::class, 'cellEntries'])->name('paedDiary.cell');
                    Route::get('paed-diary/schueler/{schueler}', [\App\Http\Controllers\PaedDiaryController::class, 'schuelerView'])->name('paedDiary.schueler.view');
                    Route::get('paed-diary/schueler/{schueler}/data', [\App\Http\Controllers\PaedDiaryController::class, 'schuelerData'])->name('paedDiary.schueler.data');
                    Route::get('paed-diary/schueler/{schueler}/export/word', [\App\Http\Controllers\PaedDiaryController::class, 'exportSchuelerWord'])->name('paedDiary.schueler.export.word');
                    Route::post('paed-diary/entry', [\App\Http\Controllers\PaedDiaryController::class, 'storeEntry'])->name('paedDiary.entry.store');
                    Route::post('paed-diary/entry/{entry}', [\App\Http\Controllers\PaedDiaryController::class, 'updateEntry'])->name('paedDiary.entry.update');
                    Route::post('paed-diary/entry/{entry}/complete', [\App\Http\Controllers\PaedDiaryController::class, 'completeEntry'])->name('paedDiary.entry.complete');
                    Route::post('paed-diary/entry/{entry}/pause-day', [\App\Http\Controllers\PaedDiaryController::class, 'pauseEntryDay'])->name('paedDiary.entry.pause');
                    Route::post('paed-diary/entry/{entry}/unpause-day', [\App\Http\Controllers\PaedDiaryController::class, 'unpauseEntryDay'])->name('paedDiary.entry.unpause');
                    Route::delete('paed-diary/entry/{entry}', [\App\Http\Controllers\PaedDiaryController::class, 'destroyEntry'])->name('paedDiary.entry.destroy');

                    // Ziele ("Ziel an dem ich arbeiten möchte") mit Historie
                    Route::post('paed-diary/schueler/{schueler}/goals', [\App\Http\Controllers\PaedDiaryController::class, 'storeGoal'])->name('paedDiary.goal.store');
                    Route::put('paed-diary/goals/{goal}/achieve', [\App\Http\Controllers\PaedDiaryController::class, 'achieveGoal'])->name('paedDiary.goal.achieve');

                    // Tages-Pause für gesamte Klasse/Gruppe (Veranstaltungen, Ferienüberschreibung)
                    Route::post('paed-diary/day/pause', [\App\Http\Controllers\PaedDiaryController::class, 'pauseClassDay'])->name('paedDiary.day.pause');
                    Route::post('paed-diary/day/unpause', [\App\Http\Controllers\PaedDiaryController::class, 'unpauseClassDay'])->name('paedDiary.day.unpause');

                    // Spalten-Verwaltung → PaedDiaryColumnController
                    Route::post('paed-diary/column', [\App\Http\Controllers\PaedDiaryColumnController::class, 'storeColumn'])->name('paedDiary.column.store');
                    Route::delete('paed-diary/column/{column}', [\App\Http\Controllers\PaedDiaryColumnController::class, 'destroyColumn'])->name('paedDiary.column.destroy');
                    Route::post('paed-diary/column/value', [\App\Http\Controllers\PaedDiaryColumnController::class, 'storeColumnValue'])->name('paedDiary.column.value');
                    Route::post('paed-diary/column/{column}/category', [\App\Http\Controllers\PaedDiaryColumnController::class, 'updateColumnCategory'])->name('paedDiary.column.updateCategory');
                    Route::post('paed-diary/column/{column}/restore', [\App\Http\Controllers\PaedDiaryColumnController::class, 'restoreColumn'])->name('paedDiary.column.restore');
                    Route::post('paed-diary/column/{column}/copy', [\App\Http\Controllers\PaedDiaryColumnController::class, 'copyColumn'])->name('paedDiary.column.copy');
                    Route::get('paed-diary/columns/all', [\App\Http\Controllers\PaedDiaryColumnController::class, 'columnsAll'])->name('paedDiary.columns.all');

                    Route::post('paed-diary/change-stage', [\App\Http\Controllers\PaedDiaryController::class, 'changeSchuelerStage'])->middleware('permission:manage grading systems')->name('paedDiary.changeStage');
                    Route::get('paed-diary/klasse/{klasse}/stages', [\App\Http\Controllers\PaedDiaryController::class, 'getClassStages'])->name('paedDiary.klasse.stages');
                    Route::get('paed-diary/klasse/{klasse}/schueler', [\App\Http\Controllers\PaedDiaryController::class, 'getClassSchueler'])->name('paedDiary.klasse.schueler');

                    // Aufgaben → PaedDiaryTaskController
                    Route::post('paed-diary/task', [\App\Http\Controllers\PaedDiaryTaskController::class, 'store'])->name('paedDiary.task.store');
                    Route::put('paed-diary/task/{task}', [\App\Http\Controllers\PaedDiaryTaskController::class, 'updateTask'])->name('paedDiary.task.update');
                    Route::post('paed-diary/task/{task}/close', [\App\Http\Controllers\PaedDiaryTaskController::class, 'closeTask'])->name('paedDiary.task.close');

                    Route::get('export/paed-diary/excel', [\App\Http\Controllers\PaedDiaryController::class, 'exportExcel'])->name('paedDiary.export.excel');
                    Route::post('paed-diary/absence', [\App\Http\Controllers\PaedDiaryController::class, 'toggleAbsence'])->name('paedDiary.absence.toggle');

                    // Kategorieverwaltung – neuer PaedDiaryCategoryController
                    // Spezifische Routen MÜSSEN vor parametrisierten stehen
                    Route::get('paed-diary/categories/manage', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'manageView'])->name('paedDiary.categories.manage');
                    Route::get('paed-diary/categories/hidden', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'getHiddenCategories'])->name('paedDiary.categories.hidden');
                    Route::get('paed-diary/categories', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'getCategories'])->name('paedDiary.categories.index');
                    Route::post('paed-diary/categories', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'storeCategory'])->name('paedDiary.categories.store');
                    Route::put('paed-diary/categories/{category}/rename', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'renameCategory'])->name('paedDiary.categories.rename');
                    Route::delete('paed-diary/categories/{category}', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'deleteCategory'])->name('paedDiary.categories.delete');
                    Route::post('paed-diary/categories/{category}/toggle-hidden', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'toggleHidden'])->name('paedDiary.categories.toggleHidden');
                    Route::get('paed-diary/column-groups', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'getColumnGroups'])->name('paedDiary.columnGroups.index');
                    // Globale Kategorien + Spaltengruppen-Rename brauchen zusätzliche Permission
                    Route::middleware(['permission:manage global paed diary categories'])->group(function () {
                        Route::post('paed-diary/categories/global', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'storeGlobalCategory'])->name('paedDiary.categories.storeGlobal');
                        Route::put('paed-diary/categories/global/{category}', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'updateGlobalCategory'])->name('paedDiary.categories.updateGlobal');
                        Route::delete('paed-diary/categories/global/{category}', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'deleteGlobalCategory'])->name('paedDiary.categories.deleteGlobal');
                        Route::post('paed-diary/column-groups/rename', [\App\Http\Controllers\PaedDiaryCategoryController::class, 'renameColumnGroup'])->name('paedDiary.columnGroups.rename');
                    });
                    Route::post('paed-diary/settings/show-categories', [\App\Http\Controllers\PaedDiaryController::class, 'updateShowCategoriesSetting'])->name('paedDiary.settings.showCategories');

                    // Klassen-Gruppen → PaedDiaryClassGroupController (TODO 16)
                    Route::get('paed-diary/class-groups', [\App\Http\Controllers\PaedDiaryClassGroupController::class, 'index'])->name('paedDiary.classGroups.index');
                    Route::post('paed-diary/class-groups', [\App\Http\Controllers\PaedDiaryClassGroupController::class, 'store'])->name('paedDiary.classGroups.store');
                    Route::put('paed-diary/class-groups/{group}', [\App\Http\Controllers\PaedDiaryClassGroupController::class, 'update'])->name('paedDiary.classGroups.update');
                    Route::delete('paed-diary/class-groups/{group}', [\App\Http\Controllers\PaedDiaryClassGroupController::class, 'destroy'])->name('paedDiary.classGroups.destroy');

                    // Termine → PaedDiaryAppointmentController (TODO 16)
                    Route::get('paed-diary/appointments', [\App\Http\Controllers\PaedDiaryAppointmentController::class, 'index'])->name('paedDiary.appointments.index');
                    Route::post('paed-diary/appointments', [\App\Http\Controllers\PaedDiaryAppointmentController::class, 'store'])->name('paedDiary.appointments.store');
                    Route::put('paed-diary/appointments/{appointment}', [\App\Http\Controllers\PaedDiaryAppointmentController::class, 'update'])->name('paedDiary.appointments.update');
                    Route::post('paed-diary/appointments/{appointment}/toggle-pause', [\App\Http\Controllers\PaedDiaryAppointmentController::class, 'togglePause'])->name('paedDiary.appointments.togglePause');
                    Route::delete('paed-diary/appointments/{appointment}', [\App\Http\Controllers\PaedDiaryAppointmentController::class, 'destroy'])->name('paedDiary.appointments.destroy');


                    // Admin: Verwaltung der Graduierungssysteme und Stufen
                    Route::middleware(['permission:manage grading systems'])->prefix('admin/grading')->group(function () {
                        Route::get('/', [\App\Http\Controllers\GradingAdminController::class, 'index'])->name('admin.grading.index');
                        Route::post('/system', [\App\Http\Controllers\GradingAdminController::class, 'storeSystem'])->name('admin.grading.system.store');
                        Route::post('/system/{system}/delete', [\App\Http\Controllers\GradingAdminController::class, 'destroySystem'])->name('admin.grading.system.delete');
                        Route::post('/system/{system}/stage', [\App\Http\Controllers\GradingAdminController::class, 'storeStage'])->name('admin.grading.stage.store');
                        Route::post('/stage/{stage}/update', [\App\Http\Controllers\GradingAdminController::class, 'updateStage'])->name('admin.grading.stage.update');
                        Route::post('/stage/{stage}/delete', [\App\Http\Controllers\GradingAdminController::class, 'destroyStage'])->name('admin.grading.stage.delete');
                        // Reorder stages via AJAX
                        Route::post('/system/{system}/stages/order', [\App\Http\Controllers\GradingAdminController::class, 'reorderStages'])->name('admin.grading.stage.reorder');


                        // Fragen-Verwaltung
                        Route::post('/system/{system}/question', [\App\Http\Controllers\GradingAdminController::class, 'storeQuestion'])->name('admin.grading.question.store');
                        Route::post('/question/{question}/update', [\App\Http\Controllers\GradingAdminController::class, 'updateQuestion'])->name('admin.grading.question.update');
                        Route::post('/question/{question}/delete', [\App\Http\Controllers\GradingAdminController::class, 'destroyQuestion'])->name('admin.grading.question.delete');
                        Route::post('/system/{system}/questions/order', [\App\Http\Controllers\GradingAdminController::class, 'reorderQuestions'])->name('admin.grading.question.reorder');
                    });

                    // Graduierungssystem-Dokumentation
                    Route::prefix('paed-diary/documentation')->name('gradingDocumentation.')->group(function () {
                        Route::get('/', [\App\Http\Controllers\GradingDocumentationController::class, 'index'])->name('index');
                        Route::post('start-group', [\App\Http\Controllers\GradingDocumentationController::class, 'startGroupSession'])->name('startGroup');
                        Route::post('start-individual', [\App\Http\Controllers\GradingDocumentationController::class, 'startIndividualSession'])->name('startIndividual');
                        Route::get('session/{session}/group', [\App\Http\Controllers\GradingDocumentationController::class, 'showGroupSession'])->name('groupSession');
                        Route::get('session/{session}/individual', [\App\Http\Controllers\GradingDocumentationController::class, 'showIndividualSession'])->name('individualSession');
                        Route::get('session/{session}/teacher-assessment', [\App\Http\Controllers\GradingDocumentationController::class, 'showTeacherAssessment'])->name('teacherAssessment');
                        Route::post('student-answer', [\App\Http\Controllers\GradingDocumentationController::class, 'saveStudentAnswer'])->name('saveStudentAnswer');
                        Route::post('teacher-assessment', [\App\Http\Controllers\GradingDocumentationController::class, 'saveTeacherAssessment'])->name('saveTeacherAssessment');
                        Route::post('coaching-note', [\App\Http\Controllers\GradingDocumentationController::class, 'saveCoachingNote'])->name('saveCoachingNote');
                        Route::post('session/{session}/complete', [\App\Http\Controllers\GradingDocumentationController::class, 'completeSession'])->name('completeSession');
                        Route::post('session/{session}/cancel', [\App\Http\Controllers\GradingDocumentationController::class, 'cancelSession'])->name('cancelSession');
                        Route::post('session/{session}/reopen', [\App\Http\Controllers\GradingDocumentationController::class, 'reopenSession'])->name('reopenSession');
                        Route::get('session/{session}/data', [\App\Http\Controllers\GradingDocumentationController::class, 'getSessionData'])->name('sessionData');
                        Route::get('schueler/{schueler}/documentations', [\App\Http\Controllers\GradingDocumentationController::class, 'showSchuelerDocumentations'])->name('schuelerDocumentations');

                        // QR-Code Token für öffentlichen Schüler-Zugriff
                        Route::post('session/{session}/student/{schueler}/qr-token', [\App\Http\Controllers\GradingDocumentationController::class, 'generateStudentQRToken'])->name('generateQRToken');
                    });

                    // Diagnosebögen System
                    Route::middleware(['permission:view diagnostics'])->prefix('diagnostics')->name('diagnostic.')->group(function () {
                        Route::get('/', [\App\Http\Controllers\DiagnosticController::class, 'index'])->name('index');
                        Route::get('/klasse/{klasse}/students', [\App\Http\Controllers\DiagnosticController::class, 'selectStudent'])->name('students');
                        Route::get('/schueler/{schueler}/areas', [\App\Http\Controllers\DiagnosticController::class, 'selectArea'])->name('areas');
                        Route::post('/schueler/{schueler}/area/{area}/start', [\App\Http\Controllers\DiagnosticController::class, 'start'])->name('start');
                        Route::get('/session/{session}', [\App\Http\Controllers\DiagnosticController::class, 'showSession'])->name('session');
                        Route::post('/session/{session}/assess', [\App\Http\Controllers\DiagnosticController::class, 'saveAssessment'])->name('assess');
                        Route::post('/session/{session}/stage/{stage}/note', [\App\Http\Controllers\DiagnosticController::class, 'saveStageNote'])->name('stage-note');
                        Route::post('/session/{session}/complete', [\App\Http\Controllers\DiagnosticController::class, 'complete'])->name('complete');
                        Route::get('/schueler/{schueler}/area/{area}/history', [\App\Http\Controllers\DiagnosticController::class, 'history'])->name('history');
                        Route::get('/schueler/{schueler}/goals', [\App\Http\Controllers\DiagnosticController::class, 'currentGoals'])->name('current-goals');
                        Route::post('/assessment/{assessment}/toggle-current', [\App\Http\Controllers\DiagnosticController::class, 'toggleCurrentGoal'])->name('toggle-current-goal');

                        // Kommentare zu Zielen
                        Route::post('/goal/{goal}/schueler/{schueler}/comment', [\App\Http\Controllers\DiagnosticController::class, 'storeGoalComment'])->name('goal-comment.store');
                        Route::put('/comment/{comment}', [\App\Http\Controllers\DiagnosticController::class, 'updateGoalComment'])->name('goal-comment.update');
                        Route::delete('/comment/{comment}', [\App\Http\Controllers\DiagnosticController::class, 'deleteGoalComment'])->name('goal-comment.delete');

                        // Reopen nur für Admins
                        Route::post('/session/{session}/reopen', [\App\Http\Controllers\DiagnosticController::class, 'reopen'])
                            ->name('reopen')
                            ->middleware('permission:manage diagnostics');

                        // PDF-Export
                        Route::get('/session/{session}/export-pdf', [\App\Http\Controllers\DiagnosticExportController::class, 'exportSessionPdf'])->name('export-session-pdf');
                        Route::get('/schueler/{schueler}/area/{area}/export-pdf', [\App\Http\Controllers\DiagnosticExportController::class, 'exportStudentAreaPdf'])->name('export-area-pdf');
                        Route::get('/area/{area}/blank-form-pdf', [\App\Http\Controllers\DiagnosticExportController::class, 'exportBlankFormPdf'])->name('export-blank-form-pdf');

                        // Admin Section
                        Route::middleware(['permission:manage diagnostics'])->prefix('admin')->name('admin.')->group(function () {
                            Route::get('/', [\App\Http\Controllers\DiagnosticAdminController::class, 'index'])->name('index');

                            // Areas
                            Route::post('/areas', [\App\Http\Controllers\DiagnosticAdminController::class, 'storeArea'])->name('areas.store');
                            Route::put('/areas/{area}', [\App\Http\Controllers\DiagnosticAdminController::class, 'updateArea'])->name('areas.update');
                            Route::delete('/areas/{area}', [\App\Http\Controllers\DiagnosticAdminController::class, 'destroyArea'])->name('areas.destroy');
                            Route::post('/areas/reorder', [\App\Http\Controllers\DiagnosticAdminController::class, 'reorderAreas'])->name('areas.reorder');

                            // Stages
                            Route::post('/areas/{area}/stages', [\App\Http\Controllers\DiagnosticAdminController::class, 'storeStage'])->name('stages.store');
                            Route::put('/stages/{stage}', [\App\Http\Controllers\DiagnosticAdminController::class, 'updateStage'])->name('stages.update');
                            Route::delete('/stages/{stage}', [\App\Http\Controllers\DiagnosticAdminController::class, 'destroyStage'])->name('stages.destroy');
                            Route::post('/areas/{area}/stages/reorder', [\App\Http\Controllers\DiagnosticAdminController::class, 'reorderStages'])->name('stages.reorder');

                            // Goals
                            Route::post('/stages/{stage}/goals', [\App\Http\Controllers\DiagnosticAdminController::class, 'storeGoal'])->name('goals.store');
                            Route::put('/goals/{goal}', [\App\Http\Controllers\DiagnosticAdminController::class, 'updateGoal'])->name('goals.update');
                            Route::delete('/goals/{goal}', [\App\Http\Controllers\DiagnosticAdminController::class, 'destroyGoal'])->name('goals.destroy');
                            Route::post('/stages/{stage}/goals/reorder', [\App\Http\Controllers\DiagnosticAdminController::class, 'reorderGoals'])->name('goals.reorder');
                        });

                        // Legacy routes for backward compatibility (deprecated)
                        Route::post('/areas', [\App\Http\Controllers\DiagnosticAdminController::class, 'storeArea'])->name('areas.store');
                        Route::put('/areas/{area}', [\App\Http\Controllers\DiagnosticAdminController::class, 'updateArea'])->name('areas.update');
                        Route::delete('/areas/{area}', [\App\Http\Controllers\DiagnosticAdminController::class, 'destroyArea'])->name('areas.destroy');
                        Route::post('/areas/reorder', [\App\Http\Controllers\DiagnosticAdminController::class, 'reorderAreas'])->name('areas.reorder');

                        // Stages
                        Route::post('/areas/{area}/stages', [\App\Http\Controllers\DiagnosticAdminController::class, 'storeStage'])->name('stages.store');
                        Route::put('/stages/{stage}', [\App\Http\Controllers\DiagnosticAdminController::class, 'updateStage'])->name('stages.update');
                        Route::delete('/stages/{stage}', [\App\Http\Controllers\DiagnosticAdminController::class, 'destroyStage'])->name('stages.destroy');
                        Route::post('/areas/{area}/stages/reorder', [\App\Http\Controllers\DiagnosticAdminController::class, 'reorderStages'])->name('stages.reorder');

                        // Goals
                        Route::post('/stages/{stage}/goals', [\App\Http\Controllers\DiagnosticAdminController::class, 'storeGoal'])->name('goals.store');
                        Route::put('/goals/{goal}', [\App\Http\Controllers\DiagnosticAdminController::class, 'updateGoal'])->name('goals.update');
                        Route::delete('/goals/{goal}', [\App\Http\Controllers\DiagnosticAdminController::class, 'destroyGoal'])->name('goals.destroy');
                        Route::post('/stages/{stage}/goals/reorder', [\App\Http\Controllers\DiagnosticAdminController::class, 'reorderGoals'])->name('goals.reorder');
                    });
                });

            });
    });



// Public room calendar feed (token protected)
Route::get('/rooms/{room}/calendar/{token}.ics', [RoomCalendarController::class, 'feed'])->name('rooms.calendar.feed');

// ============================================================
// Kalender-Modul (OX-Integration)
// ============================================================
Route::prefix('calendar')->middleware(['auth'])->group(function () {
    // Lesen
    Route::middleware('permission:view calendar')->group(function () {
        Route::get('/', [\App\Http\Controllers\CalendarController::class, 'index'])
            ->name('calendar.index');
        Route::get('/events', [\App\Http\Controllers\CalendarController::class, 'events'])
            ->name('calendar.events');
        Route::get('/termin/{termin}', [\App\Http\Controllers\CalendarController::class, 'show'])
            ->name('calendar.show');
        Route::get('/suche', [\App\Http\Controllers\CalendarController::class, 'search'])
            ->name('calendar.search');
        Route::post('/feed/token', [\App\Http\Controllers\CalendarController::class, 'generateFeedToken'])
            ->name('calendar.feed.token');

        // iCal-Feed-Verwaltung – User-spezifisch (TODO 30)
        Route::post('/ical-feeds', [\App\Http\Controllers\CalendarController::class, 'storeIcalFeed'])
            ->name('calendar.ical.store');
        Route::put('/ical-feeds/{feed}', [\App\Http\Controllers\CalendarController::class, 'updateIcalFeed'])
            ->name('calendar.ical.update');
        Route::delete('/ical-feeds/{feed}', [\App\Http\Controllers\CalendarController::class, 'destroyIcalFeed'])
            ->name('calendar.ical.destroy');

        // Kalenderfarben – Hybrid DB/localStorage (TODO 29)
        Route::get('/farben', [\App\Http\Controllers\CalendarController::class, 'getColors'])
            ->name('calendar.colors.index');
        Route::put('/farben', [\App\Http\Controllers\CalendarController::class, 'saveColors'])
            ->name('calendar.colors.save');
        Route::delete('/farben/{oxCalendar}', [\App\Http\Controllers\CalendarController::class, 'resetColor'])
            ->name('calendar.colors.reset');

        // PDF-Export (TODO 28) – /export-pdf (nicht /export/pdf, da {groupname}/export/{date?} vorher matcht)
        Route::get('/export-pdf', [\App\Http\Controllers\CalendarController::class, 'exportPdf'])
            ->name('calendar.export.pdf');
    });

    // Schreiben (create calendar events)
    Route::middleware('permission:create calendar events')->group(function () {
        Route::post('/termine', [\App\Http\Controllers\CalendarController::class, 'store'])
            ->name('calendar.store')
            ->middleware('throttle:calendar-write');
    });

    // Bearbeiten/Löschen (edit calendar events)
    Route::middleware('permission:edit calendar events')->group(function () {
        Route::put('/termine/{termin}', [\App\Http\Controllers\CalendarController::class, 'update'])
            ->name('calendar.update')
            ->middleware('throttle:calendar-write');
        Route::patch('/termine/{termin}/verschieben', [\App\Http\Controllers\CalendarController::class, 'move'])
            ->name('calendar.move')
            ->middleware('throttle:calendar-write');
        Route::delete('/termine/{termin}', [\App\Http\Controllers\CalendarController::class, 'destroy'])
            ->name('calendar.destroy')
            ->middleware('throttle:calendar-write');
    });

    // Admin (Kalender-Verwaltung)
    Route::prefix('admin')->middleware('permission:manage calendar')->group(function () {
        Route::get('/', [\App\Http\Controllers\CalendarAdminController::class, 'index'])
            ->name('calendar.admin');
        Route::post('/kalender', [\App\Http\Controllers\CalendarAdminController::class, 'storeKalender'])
            ->name('calendar.admin.store');
        Route::put('/kalender/{kalender}', [\App\Http\Controllers\CalendarAdminController::class, 'updateKalender'])
            ->name('calendar.admin.update');
        Route::delete('/kalender/{kalender}', [\App\Http\Controllers\CalendarAdminController::class, 'destroyKalender'])
            ->name('calendar.admin.destroy');
        Route::post('/kalender/{kalender}/gruppen', [\App\Http\Controllers\CalendarAdminController::class, 'updateGruppen'])
            ->name('calendar.admin.gruppen');
        Route::post('/sync', [\App\Http\Controllers\CalendarAdminController::class, 'triggerSync'])
            ->name('calendar.admin.sync');
        Route::get('/logs', [\App\Http\Controllers\CalendarAdminController::class, 'logs'])
            ->name('calendar.admin.logs');
        Route::get('/health.json', [\App\Http\Controllers\CalendarAdminController::class, 'health'])
            ->name('calendar.admin.health');
    });
});

// iCal-Feed (Token-geschützt, KEIN Auth-Middleware) – wird in TODO 12 ergänzt
Route::get('/calendar/feed/{token}.ics', [\App\Http\Controllers\CalendarController::class, 'feed'])
    ->name('calendar.feed');

// Personal-Modul: Temporäre Test-Route (Phase 0 – nach Verifizierung entfernen)
Route::get('/personal/test-ui', function () {
    return view('personal.test-ui');
})->middleware('auth')->name('personal.test-ui');

// ═══════════════════════════════════════════════════════════════════════════
// Personalakte-Hub: Übersichtsseite je Mitarbeiter (alle Sub-Module)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view personal_data', 'personal.audit', 'throttle:30,1'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        Route::get('/mitarbeiter/{employe}', [App\Http\Controllers\Personal\PersonalakteController::class, 'show'])
            ->name('personalakte.show');
    });

// ═══════════════════════════════════════════════════════════════════════════
// Phase 1: Self-Service-Portal (Mein Profil) – alle eingeloggten Mitarbeiter
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'throttle:30,1', 'personal.audit'])
    ->prefix('mein-profil')
    ->name('self-service.')
    ->group(function () {
        Route::get('/',                [App\Http\Controllers\Personal\SelfServiceController::class, 'index'])        ->name('index');
        Route::get('/vertraege',       [App\Http\Controllers\Personal\SelfServiceController::class, 'vertraege'])    ->name('vertraege');
        Route::get('/dokumente',       [App\Http\Controllers\Personal\SelfServiceController::class, 'dokumente'])    ->name('dokumente');
        Route::get('/qualifikationen', [App\Http\Controllers\Personal\SelfServiceController::class, 'qualifikationen'])->name('qualifikationen');
        Route::get('/gespraeche',      [App\Http\Controllers\Personal\SelfServiceController::class, 'gespraeche'])   ->name('gespraeche');
        Route::get('/einwilligungen',  [App\Http\Controllers\Personal\SelfServiceController::class, 'einwilligungen'])->name('einwilligungen');

        // Einwilligungen (Self-Service)
        Route::post('/einwilligungen/{type}/erteilen',   [App\Http\Controllers\Personal\ConsentController::class, 'grant'])  ->name('consents.grant');
        Route::post('/einwilligungen/{type}/widerrufen', [App\Http\Controllers\Personal\ConsentController::class, 'revoke']) ->name('consents.revoke');

        // Stundenzettel: Passwort-Bestätigung erforderlich
        Route::middleware('password.confirm')->group(function () {
            Route::get('/stundenzettel', [App\Http\Controllers\Personal\SelfServiceController::class, 'stundenzettel'])->name('stundenzettel');
        });
    });

// ═══════════════════════════════════════════════════════════════════════════
// Phase 1: Vertragsmanagement
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view contracts', 'personal.audit'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        // Vertragsübersicht pro Mitarbeiter
        Route::get('/mitarbeiter/{employe}/vertraege',    [App\Http\Controllers\Personal\ContractController::class, 'index'])  ->name('contracts.index');
        Route::get('/mitarbeiter/{employe}/vertraege/neu',[App\Http\Controllers\Personal\ContractController::class, 'create']) ->name('contracts.create')
            ->middleware('permission:edit contracts');
        Route::post('/mitarbeiter/{employe}/vertraege',   [App\Http\Controllers\Personal\ContractController::class, 'store'])  ->name('contracts.store')
            ->middleware('permission:edit contracts');
        Route::get('/vertraege/{employment}/bearbeiten',  [App\Http\Controllers\Personal\ContractController::class, 'edit'])   ->name('contracts.edit')
            ->middleware('permission:edit contracts');
        Route::put('/vertraege/{employment}',             [App\Http\Controllers\Personal\ContractController::class, 'update']) ->name('contracts.update')
            ->middleware('permission:edit contracts');
        Route::patch('/vertraege/{employment}/ruhend',    [App\Http\Controllers\Personal\ContractController::class, 'setRuhend'])  ->name('contracts.setRuhend')
            ->middleware('permission:edit contracts');
        Route::patch('/vertraege/{employment}/aktiv',     [App\Http\Controllers\Personal\ContractController::class, 'setAktiv'])   ->name('contracts.setAktiv')
            ->middleware('permission:edit contracts');
        Route::patch('/vertraege/{employment}/beenden',   [App\Http\Controllers\Personal\ContractController::class, 'setBeendet'])->name('contracts.setBeendet')
            ->middleware('permission:edit contracts');
    });

// ═══════════════════════════════════════════════════════════════════════════
// Arbeitspaket 4: Prüfengine für Zeiterfassung, Dienstpläne & Vertragsänderungen
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view timesheet anomalies', 'personal.audit'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        Route::get('/mitarbeiter/{employe}/pruefung/{date?}', [App\Http\Controllers\Personal\TimesheetAnomalyController::class, 'index'])
            ->name('timesheet-validation.index');

        Route::post('/mitarbeiter/{employe}/pruefung/{date}/lauf', [App\Http\Controllers\Personal\TimesheetAnomalyController::class, 'runForEmployee'])
            ->name('timesheet-validation.run')
            ->middleware('permission:run timesheet validation');

        Route::post('/mitarbeiter/{employe}/pruefung/zeitraum-lauf', [App\Http\Controllers\Personal\TimesheetAnomalyController::class, 'runForEmployeeRange'])
            ->name('timesheet-validation.run-range')
            ->middleware('permission:run timesheet validation');

        Route::post('/abteilung/{department}/pruefung/{date}/lauf', [App\Http\Controllers\Personal\TimesheetAnomalyController::class, 'runForDepartment'])
            ->name('timesheet-validation.run-department')
            ->middleware('permission:run timesheet validation');

        Route::post('/abteilung/{department}/pruefung/zeitraum-lauf', [App\Http\Controllers\Personal\TimesheetAnomalyController::class, 'runForDepartmentRange'])
            ->name('timesheet-validation.run-department-range')
            ->middleware('permission:run timesheet validation');

        Route::patch('/pruefung/anomalien/{anomaly}/quittieren', [App\Http\Controllers\Personal\TimesheetAnomalyController::class, 'resolve'])
            ->name('timesheet-validation.resolve')
            ->middleware('permission:resolve timesheet anomalies');
    });

// ═══════════════════════════════════════════════════════════════════════════
// Phase 1: Organigramm
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view orgchart', 'personal.audit'])
    ->prefix('personal/orgchart')
    ->name('personal.orgchart.')
    ->group(function () {
        Route::get('/',           [App\Http\Controllers\Personal\OrgChartController::class, 'index'])     ->name('index');
        Route::get('/export/pdf', [App\Http\Controllers\Personal\OrgChartController::class, 'exportPdf'])->name('export.pdf')
            ->middleware('permission:export orgchart');

        // Stellen-CRUD (Personalleitung)
        Route::middleware('permission:manage orgchart')->group(function () {
            Route::get('/positions',              [App\Http\Controllers\Personal\OrgChartController::class, 'positionsIndex'])  ->name('positions.index');
            Route::get('/positions/create',       [App\Http\Controllers\Personal\OrgChartController::class, 'positionsCreate']) ->name('positions.create');
            Route::post('/positions',             [App\Http\Controllers\Personal\OrgChartController::class, 'positionsStore'])  ->name('positions.store');
            Route::get('/positions/{position}/edit',   [App\Http\Controllers\Personal\OrgChartController::class, 'positionsEdit'])  ->name('positions.edit');
            Route::put('/positions/{position}',        [App\Http\Controllers\Personal\OrgChartController::class, 'positionsUpdate'])->name('positions.update');
            Route::delete('/positions/{position}',     [App\Http\Controllers\Personal\OrgChartController::class, 'positionsDestroy'])->name('positions.destroy');
            Route::post('/positions/{position}/assign',[App\Http\Controllers\Personal\OrgChartController::class, 'positionsAssign'])->name('positions.assign');
        });
    });

// ═══════════════════════════════════════════════════════════════════════════
// Phase 1: Einwilligungsverwaltung (Admin)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:manage personal_consents', 'personal.audit'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        Route::get('/einwilligungen', [App\Http\Controllers\Personal\ConsentController::class, 'adminIndex'])->name('consents.admin');
    });

// ═══════════════════════════════════════════════════════════════════════════
// Phase 2: Dokumentenmanagement (P2-01 + P2-02)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view personal_documents', 'personal.audit'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        Route::get('/mitarbeiter/{employe}/dokumente', [App\Http\Controllers\Personal\PersonalDocumentController::class, 'index'])
            ->name('documents.index');
        Route::get('/dokumente/{document}/download', [App\Http\Controllers\Personal\PersonalDocumentController::class, 'download'])
            ->name('documents.download');
    });

Route::middleware(['auth', 'permission:manage personal_documents', 'personal.audit'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        Route::post('/mitarbeiter/{employe}/dokumente/hochladen', [App\Http\Controllers\Personal\PersonalDocumentController::class, 'upload'])
            ->name('documents.upload');
        Route::post('/mitarbeiter/{employe}/dokumente/generieren', [App\Http\Controllers\Personal\PersonalDocumentController::class, 'generate'])
            ->name('documents.generate');
        Route::delete('/dokumente/{document}', [App\Http\Controllers\Personal\PersonalDocumentController::class, 'destroy'])
            ->name('documents.destroy');
        Route::get('/dokumente/sync-fehler', [App\Http\Controllers\Personal\PersonalDocumentController::class, 'syncErrors'])
            ->name('documents.sync-errors');
        Route::post('/dokumente/{document}/sync-retry', [App\Http\Controllers\Personal\PersonalDocumentController::class, 'retrySync'])
            ->name('documents.sync-retry');
    });

// ═══════════════════════════════════════════════════════════════════════════
// Phase 2: Qualifikationsverwaltung (P2-03)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view qualifications', 'personal.audit'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        Route::get('/mitarbeiter/{employe}/qualifikationen', [App\Http\Controllers\Personal\QualificationController::class, 'index'])
            ->name('qualifications.index');
        Route::get('/qualifikationen/matrix', [App\Http\Controllers\Personal\QualificationController::class, 'matrix'])
            ->name('qualifications.matrix');
    });

Route::middleware(['auth', 'permission:manage qualifications', 'personal.audit'])
    ->prefix('personal')
    ->name('personal.')
    ->group(function () {
        Route::post('/mitarbeiter/{employe}/qualifikationen', [App\Http\Controllers\Personal\QualificationController::class, 'store'])
            ->name('qualifications.store');
        Route::delete('/qualifikationen/{qualification}', [App\Http\Controllers\Personal\QualificationController::class, 'destroy'])
            ->name('qualifications.destroy');

        // Verwaltung der Qualifikationstypen (Matrix-Vorgaben)
        Route::get('/qualifikationstypen',                    [App\Http\Controllers\Personal\QualificationTypeController::class, 'index'])->name('qualification-types.index');
        Route::get('/qualifikationstypen/neu',                [App\Http\Controllers\Personal\QualificationTypeController::class, 'create'])->name('qualification-types.create');
        Route::post('/qualifikationstypen',                   [App\Http\Controllers\Personal\QualificationTypeController::class, 'store'])->name('qualification-types.store');
        Route::get('/qualifikationstypen/{qualificationType}/bearbeiten', [App\Http\Controllers\Personal\QualificationTypeController::class, 'edit'])->name('qualification-types.edit');
        Route::put('/qualifikationstypen/{qualificationType}', [App\Http\Controllers\Personal\QualificationTypeController::class, 'update'])->name('qualification-types.update');
        Route::delete('/qualifikationstypen/{qualificationType}', [App\Http\Controllers\Personal\QualificationTypeController::class, 'destroy'])->name('qualification-types.destroy');
    });

// ═══════════════════════════════════════════════════════════════════════════
// Phase 2: Fortbildungen (P2-04)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view trainings', 'personal.audit'])
    ->prefix('personal/fortbildungen')
    ->name('personal.trainings.')
    ->group(function () {
        Route::get('/', [App\Http\Controllers\Personal\TrainingController::class, 'index'])->name('index');
        Route::get('/{training}', [App\Http\Controllers\Personal\TrainingController::class, 'show'])->name('show');
        Route::post('/{training}/anmelden', [App\Http\Controllers\Personal\TrainingController::class, 'register'])->name('register');
        Route::post('/{training}/abmelden', [App\Http\Controllers\Personal\TrainingController::class, 'cancel'])->name('cancel');
        Route::post('/{training}/teilnehmer/{employe}/bestaetigen', [App\Http\Controllers\Personal\TrainingController::class, 'approve'])
            ->middleware('permission:approve trainings')
            ->name('approve');
        Route::post('/{training}/teilnehmer/{employe}/durchgefuehrt', [App\Http\Controllers\Personal\TrainingController::class, 'markCompleted'])
            ->middleware('permission:manage trainings')
            ->name('complete');
    });

Route::middleware(['auth', 'permission:manage trainings', 'personal.audit'])
    ->prefix('personal/fortbildungen')
    ->name('personal.trainings.')
    ->group(function () {
        Route::get('/neu/erstellen', [App\Http\Controllers\Personal\TrainingController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Personal\TrainingController::class, 'store'])->name('store');
        Route::get('/{training}/bearbeiten', [App\Http\Controllers\Personal\TrainingController::class, 'edit'])->name('edit');
        Route::put('/{training}', [App\Http\Controllers\Personal\TrainingController::class, 'update'])->name('update');
        Route::delete('/{training}', [App\Http\Controllers\Personal\TrainingController::class, 'destroy'])->name('destroy');
    });

