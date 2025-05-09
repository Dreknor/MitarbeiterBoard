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
                    Route::resource('tickets', \App\Http\Controllers\Ticketsystem\TicketController::class)->except('create','edit');
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
                    Route::resource('employes', EmployeController::class)->names([
                        'show' => 'employes.show',
                        'index' => 'employes.index',
                    ])->except('create');
                    Route::put('employes/{employe}/data/update', [EmployeController::class, 'updateData'])->name('employes.data.update');
                });


                //Urlaubsverwaltung
                Route::middleware(['permission:has holidays|approve holidays'])->group(function () {
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

                //Anstellungen
                Route::post('employments/{employe}/add', [EmploymentController::class, 'store']);

                Route::post('addresses/{employe}',[AddressController::class, 'update']);


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
                    Route::get('roster/{roster}/exportEmploye/{employe}/pdf', [RosterController::class, 'exportPdfEmploye'])->name('roster.export.employe.pdf');
                    Route::get('roster/news/{news}/delete', [RosterNewsController::class, 'destroy'])->name('roster.news.delete');
                    Route::post('roster/{roster}/news/add', [RosterNewsController::class, 'store'])->name('roster.news.add');

                    Route::get('roster/{roster}/toggleView/{day}', [RosterController::class, 'toogleDayView'])->name('toggleDayView');

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


            //Raumplan
                Route::prefix('rooms')->middleware('permission:view roomBooking')->group(function () {
                    Route::resource('rooms', RoomController::class)->except('create');
                    Route::get('rooms/{room}/{week?}', [RoomController::class, 'show']);
                    Route::post('bookings', [RoomController::class, 'storeBooking']);
                    Route::get('booking/{booking}', [RoomController::class, 'editBooking']);
                    Route::get('rooms/{room}/export', [RoomController::class, 'export']);
                    Route::delete('booking/{booking}', [RoomController::class, 'deleteBooking']);
                    Route::put('bookings/{booking}', [RoomController::class, 'updateBooking']);
                    Route::post('import', [RoomController::class, 'import'])->middleware('permission:manage rooms');

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

                //Klassen
                Route::group(['middleware' => ['permission:edit klassen']], function () {
                    Route::resource('klassen', KlasseController::class);
                });

                //absences
                Route::middleware(['permission:view absences'])->group(function (){
                    Route::get('absences', [AbsenceController::class, 'index'])->middleware(['permission:view old absences']);
                    Route::post('absences', [AbsenceController::class, 'store']);
                    Route::get('absences/export', [AbsenceController::class, 'export'])->middleware(['permission:export absence']);
                    Route::get('absences/{absence}/delete', [AbsenceController::class, 'delete']);
                    Route::get('absences/abo/{type}', [AbsenceController::class, 'abo']);
                });

                Route::middleware(['permission:manage sick_notes'])->group(function (){
                    Route::get('sick_notes', [AbsenceController::class, 'sick_notes_index']);
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
                    Route::resource('categories', \App\Http\Controllers\Inventory\CategoryController::class);
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
                Route::get('subscription/{type}/{id}', [SubscriptionController::class,'add']);
                Route::get('subscription/{type}/{id}/remove', [SubscriptionController::class,'remove']);

                Route::get('/home', [HomeController::class, 'index'])->name('home');
                Route::get('/', [HomeController::class, 'index']);
                Route::post('cards/disable', [DashboardController::class, 'disableCard']);

                //Posts
                Route::resource('posts', PostsController::class);
                Route::get('posts/{post}/release', [PostsController::class, 'release']);




                //globale Suche
                Route::post('search/search', [SearchController::class, 'searchGlobal']);
                Route::get('search', [SearchController::class, 'globalSearch']);


                //recurring Themes
                Route::middleware('permission:manage recurring themes')->group(function (){
                    Route::resource('{groupname}/themes/recurring', RecurringThemeController::class)->except('show');
                    Route::get('{groupname}/themes/recurring/file/{media}/delete', [ImageController::class, 'removeImage']);
                    Route::get('themes/recurring/start/{now?}', [RecurringThemeController::class, 'createNewThemes']);
                });

                //Themes
                Route::resource('{groupname}/themes', ThemeController::class);
                Route::get('{groupname}/themes/create/{speicher?}', [ThemeController::class, 'create']);
                Route::post('{groupname}/move/themes', [ThemeController::class,'moveAllThemes']);
                Route::get('{groupname}/move/theme/{theme}/{newDate}/{redirect}', [ThemeController::class,'move']);
                Route::get('{groupname}/memory/{theme}', [ThemeController::class,'memoryTheme']);
                Route::get('{groupname}/memory', [ThemeController::class,'memory']);
                Route::get('{groupname}/view/{viewType}', [ThemeController::class,'setView']);
                Route::get('{groupname}/archive/{month?}', [ThemeController::class,'archive']);
                Route::get('unarchiv/{theme}', [ThemeController::class, 'unArchive'])->middleware('permission:unarchive theme');
                Route::get('{groupname}/themes/{theme}/close', [ThemeController::class,'closeTheme']);
                Route::get('{groupname}/themes/{theme}/activate', [ThemeController::class,'activate']);
                Route::post('share/{theme}', [ShareController::class, 'shareTheme']);
                Route::get('theme/{theme}/assign/{user}', [ThemeController::class, 'assgin_to']);
                Route::get('theme/{theme}/change/group/{group}', [ThemeController::class, 'change_group']);
                Route::delete('share/{theme}', [ShareController::class,'removeShare']);

                //Surveys
                Route::get('{groupname}/themes/{theme}/survey/create', [SurveyController::class,'create']);
                Route::post('{groupname}/themes/{theme}/survey/store', [SurveyController::class,'store'])->name('survey.store');
                Route::get('/survey/{survey}/edit', [SurveyController::class,'edit'])->name('survey.edit');
                Route::put('/survey/{survey}', [SurveyController::class,'update'])->name('survey.update');
                Route::delete('/survey/{survey}', [SurveyController::class,'destroy'])->name('survey.destroy');
                Route::get('{groupname}/themes/{theme}/survey/{survey}', [SurveyController::class,'show'])->name('survey.show');
                Route::post('survey/{survey}/store/question', [SurveyController::class,'storeQuestion'])->name('survey.question.store');
                Route::delete('survey/{survey}/delete/question/{question}', [SurveyController::class,'destroyQuestion'])->name('survey.question.destroy');
                Route::post('survey/{survey}/question/{question}/add/answer', [SurveyController::class,'storeAnswer'])->name('survey.answer.store');
                Route::post('survey/{survey}/answer', [SurveyController::class,'answer'])->name('survey.submit');

                //Anwesenheit
                Route::get('{groupname}/presence/{date?}', [PresenceController::class, 'index']);
                Route::post('{groupname}/presences/add', [PresenceController::class, 'store']);
                Route::post('{groupname}/presences/addGuest', [PresenceController::class, 'addGuest']);
                Route::get('{groupname}/presences/{presence}/deleteGuest', [PresenceController::class, 'deleteGuest']);

                //Prioritäten
                Route::post('priorities', [PriorityController::class, 'store']);
                Route::get('priorities/{theme}', [PriorityController::class, 'delete'])->name('priorities.delete');

                //Protocols
                Route::get('{groupname}/protocols/{theme}', [ProtocolController::class,'create']);
                Route::post('{groupname}/protocols/{theme}',  [ProtocolController::class,'store']);
                Route::get('{groupname}/protocols/{protocol}/edit',  [ProtocolController::class,'edit']);
                Route::get('{groupname}/export/{date?}/',  [ProtocolController::class,'showDailyProtocol']);
                Route::post('{groupname}/export/{date}/download',  [ProtocolController::class,'createSheet']);
                Route::put('{groupname}/protocols/{protocol}/',  [ProtocolController::class,'update']);

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
                    Route::get('/template', [ProcedureController::class, 'index_templates']);
                    Route::get('/recurring', [RecurringProcedureController::class, 'index']);
                    Route::post('/recurring', [RecurringProcedureController::class, 'store']);
                    Route::delete('/recurring/{recurringProcedure}', [RecurringProcedureController::class, 'destroy']);
                    Route::get('/recurring/{recurringProcedure}/start/{redirect?}', [RecurringProcedureController::class, 'start']);

                    //Procedures
                    Route::post('create/template', [ProcedureController::class, 'storeTemplate']);
                    Route::get('{procedure}/edit', [ProcedureController::class, 'edit']);
                    Route::get('{procedure}/start', [ProcedureController::class, 'start']);
                    Route::get('{procedure}/ends', [ProcedureController::class, 'endProcedure']);
                    Route::post('{procedure}/start', [ProcedureController::class, 'startNow']);
                    Route::get('step/{step}/edit', [ProcedureController::class, 'editStep']);
                    Route::delete('step/{step}/delete', [ProcedureController::class, 'destroy']);
                    Route::put('step/{step}', [ProcedureController::class, 'storeStep']);
                    Route::get('step/{step}/remove/{user}', [ProcedureController::class, 'removeUser']);
                    Route::post('step/addUser', [ProcedureController::class, 'addUser']);
                    Route::get('{procedure}/delete', [ProcedureController::class, 'delete']);


                    //Step
                    Route::post('{procedure}/step', [ProcedureController::class, 'addStep']);
                    Route::put('step/{step}/done', [ProcedureController::class, 'done']);
                    Route::get('step/{step}/done/mail', [ProcedureController::class, 'done']);
                    Route::get('/stepMail', [ProcedureController::class, 'remindStepMail']);


                    //positions
                    Route::get('/positions', [PositionsController::class, 'index']);
                    Route::post('/positions/{position}/add', [PositionsController::class, 'addUser']);
                    Route::get('/positions/{positions}/remove/{users}', [PositionsController::class, 'removeUser']);

                    //Categories
                    Route::post('categories', [CategoryController::class, 'store']); //Categories
                    Route::post('position', [PositionsController::class, 'store']);
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
            });
    });
