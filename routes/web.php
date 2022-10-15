<?php

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Auth\ExpiredPasswordController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DailyNewsController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\Inventory\LocationController;
use App\Http\Controllers\Inventory\LocationTypeController;
use App\Http\Controllers\KlasseController;
use App\Http\Controllers\PositionsController;
use App\Http\Controllers\PostsController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RecurringThemeController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TerminListen\ListenController;
use App\Http\Controllers\TerminListen\ListenTerminController;

use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VertretungController;
use App\Http\Controllers\VertretungsplanController;
use App\Http\Controllers\VertretungsplanWeekController;
use App\Http\Controllers\WochenplanController;
use App\Http\Controllers\WPRowsController;
use App\Http\Controllers\WpTaskController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShareController;

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
if (config('config.auth.auth_local')){
    Auth::routes(['register' => false]);
} else {
    Auth::routes(['register' => false]);
    Route::post('login', function(){
        return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Login nicht gestattet']);
    });
}

Route::get('/vertretungsplan/{gruppen?}', [VertretungsplanController::class, 'index'])->where('gruppen','.+');
Route::get('/api/vertretungsplan/{gruppen?}', [VertretungsplanController::class, 'toJSON'])->where('gruppen','.+');

Route::get('share/{uuid}', [\App\Http\Controllers\ShareController::class,'getShare']);
Route::post('share/{share}/protocol', [ShareController::class,'protocol']);

Route::get('inventory/item/{uuid}', [\App\Http\Controllers\Inventory\ItemsController::class,'scan']);
Route::post('inventory/item/{uuid}', [\App\Http\Controllers\Inventory\ItemsController::class,'scanUpdate']);

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

            //Raumplan
                Route::prefix('rooms')->middleware('permission:view roomBooking')->group(function () {
                    Route::resource('rooms', RoomController::class)->except('create');

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
                    Route::post('absences', [AbsenceController::class, 'store']);
                    Route::get('absences/{absence}/delete', [AbsenceController::class, 'delete']);
                    Route::get('absences/abo/{type}', [AbsenceController::class, 'abo']);
                    //Route::get('absences/report', [AbsenceController::class, 'dailyReport']);
                });


                //Inventar
                Route::prefix('inventory')->middleware(['permission:edit inventar'])->group(function () {
                    Route::get('locations/import', [LocationController::class, 'showImport']);
                    Route::post('locations/import', [LocationController::class, 'import']);
                    Route::post('locations/print', [LocationController::class, 'print']);

                    Route::post('items/search', [\App\Http\Controllers\Inventory\ItemsController::class, 'index']);
                    Route::post('items/print', [\App\Http\Controllers\Inventory\ItemsController::class, 'print']);
                    Route::post('items/import', [\App\Http\Controllers\Inventory\ItemsController::class, 'import']);

                    Route::get('items/import', [\App\Http\Controllers\Inventory\ItemsController::class, 'showImport']);

                    Route::resource('locations', LocationController::class);
                    Route::resource('lieferanten', \App\Http\Controllers\Inventory\LieferantController::class);
                    Route::resource('items', \App\Http\Controllers\Inventory\ItemsController::class);
                    Route::resource('categories', \App\Http\Controllers\Inventory\CategoryController::class);
                    Route::resource('locationtype', LocationTypeController::class);

                });

                //Vertretungen planen
                Route::group(['middleware' => ['permission:edit vertretungen']], function () {
                    Route::resource('vertretungen', VertretungController::class);
                    Route::post('export/vertretungen', [VertretungController::class, 'export']);
                    Route::get('vertretungen/{vertretung}/copy', [VertretungController::class, 'copy']);
                    Route::get('vertretungen/{date}/generate-doc', [VertretungController::class, 'generateDoc']);
                    Route::post('dailyNews', [DailyNewsController::class, 'store']);
                    Route::get('dailyNews', [DailyNewsController::class, 'index']);
                    Route::delete('dailyNews/{dailyNews}', [DailyNewsController::class, 'destroy']);
                    Route::get('weeks', [VertretungsplanWeekController::class, 'index']);
                    Route::get('weeks/change/{week}', [VertretungsplanWeekController::class, 'update']);
                    Route::delete('weeks/delete/{week}', [VertretungsplanWeekController::class, 'destroy']);
                });

                //Subscriptions
                Route::get('subscription/{type}/{id}', [SubscriptionController::class,'add']);
                Route::get('subscription/{type}/{id}/remove', [SubscriptionController::class,'remove']);

                Route::get('/home', [HomeController::class, 'index'])->name('home');
                Route::get('/', [HomeController::class, 'index']);

                //Posts
                Route::resource('posts', PostsController::class);
                Route::get('posts/{post}/release', [PostsController::class, 'release']);

                //globale Suche
                Route::post('search/search', [SearchController::class, 'searchGlobal']);
                Route::get('search', [SearchController::class, 'globalSearch']);


                //recurring Themes
                Route::middleware('permission:manage recurring themes')->group(function (){
                    Route::resource('{groupname}/themes/recurring', RecurringThemeController::class)->except('show');
                });

                //Themes
                Route::resource('{groupname}/themes', ThemeController::class);
                Route::get('{groupname}/themes/create/{speicher?}', [ThemeController::class, 'create']);
                Route::post('{groupname}/move/themes', [ThemeController::class,'move']);
                Route::get('{groupname}/memory/{theme}', [ThemeController::class,'memoryTheme']);
                Route::get('{groupname}/memory', [ThemeController::class,'memory']);
                Route::get('{groupname}/view/{viewType}', [ThemeController::class,'setView']);
                Route::get('{groupname}/archive', [ThemeController::class,'archive']);
                Route::get('unarchiv/{theme}', [ThemeController::class, 'unArchive'])->middleware('permission:unarchive theme');
                Route::get('{groupname}/themes/{theme}/close', [ThemeController::class,'closeTheme']);
                Route::get('{groupname}/themes/{theme}/activate', [ThemeController::class,'activate']);
                Route::post('share/{theme}', [ShareController::class, 'shareTheme']);

                Route::delete('share/{theme}', [ShareController::class,'removeShare']);

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

                Route::get('image/{media_id}', [ImageController::class, 'getImage']);
                ;

                //Roles and permissions
                Route::group(['middleware' => ['permission:edit permissions']], function () {
                    Route::get('roles', [RolesController::class, 'edit']);
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

                Route::get('kiosk', [\App\Http\Controllers\KioskController::class, 'index']);

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
            });
    });
