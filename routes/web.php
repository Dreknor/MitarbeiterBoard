<?php

use App\Http\Controllers\Auth\ExpiredPasswordController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DailyNewsController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\KlasseController;
use App\Http\Controllers\PositionsController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VertretungController;
use App\Http\Controllers\VertretungsplanController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\ShareController;

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

Auth::routes(['register' => false]);
Route::get('/vertretungsplan/{gruppen?}', [VertretungsplanController::class, 'index'])->where('gruppen','.+');

Route::get('share/{uuid}', [\App\Http\Controllers\ShareController::class,'getShare']);
Route::post('share/{share}/protocol', [ShareController::class,'protocol']);

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
                //Klassen
                Route::group(['middleware' => ['permission:edit klassen']], function () {
                    Route::resource('klassen', KlasseController::class);
                });

                //Vertretungen planen
                Route::group(['middleware' => ['permission:edit vertretungen']], function () {
                    Route::resource('vertretungen', VertretungController::class);
                    Route::get('vertretungen/{date}/generate-doc', [VertretungController::class, 'generateDoc']);
                    Route::post('dailyNews', [DailyNewsController::class, 'store']);
                    Route::get('dailyNews', [DailyNewsController::class, 'index']);
                    Route::delete('dailyNews/{dailyNews}', [DailyNewsController::class, 'destroy']);
                });

                //Subscriptions
                Route::get('subscription/{type}/{id}', [SubscriptionController::class,'add']);
                Route::get('subscription/{type}/{id}/remove', [SubscriptionController::class,'remove']);

                Route::get('/home', [HomeController::class, 'index'])->name('home');
                Route::get('/', [HomeController::class, 'index']);

                //Themes
                Route::resource('{groupname}/themes', ThemeController::class);
                Route::get('{groupname}/view/{viewType}', [ThemeController::class,'setView']);
                Route::get('{groupname}/archive', [ThemeController::class,'archive']);
                Route::get('{groupname}/themes/{theme}/close', [ThemeController::class,'closeTheme']);
                Route::post('share/{theme}', [ShareController::class, 'shareTheme']);

                Route::delete('share/{theme}', [ShareController::class,'removeShare']);

                //Prioritäten
                Route::post('priorities', [PriorityController::class, 'store']);

                //Protocols
                Route::get('{groupname}/protocols/{theme}', [ProtocolController::class,'create']);
                Route::post('{groupname}/protocols/{theme}',  [ProtocolController::class,'store']);
                Route::get('{groupname}/protocols/{protocol}/edit',  [ProtocolController::class,'edit']);
                Route::get('{groupname}/export/{date?}/',  [ProtocolController::class,'createSheet']);
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

                //User-Route
                Route::resource('users', UserController::class);
                Route::get('importuser', [UserController::class, 'importFromElternInfoBoard']);

                //Gruppen-Route
                Route::get('groups', [GroupController::class, 'index']);
                Route::post('groups', [GroupController::class, 'store']);
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

                //Route::get('kiosk', 'KioskController@index');

                Route::prefix('procedure')->group(function () {
                    Route::get('/', [ProcedureController::class, 'index']);

                    //Procedures
                    Route::post('create/template', [ProcedureController::class, 'storeTemplate']);
                    Route::get('{procedure}/edit', [ProcedureController::class, 'edit']);
                    Route::get('{procedure}/start', [ProcedureController::class, 'start']);
                    Route::post('{procedure}/start', [ProcedureController::class, 'startNow']);
                    Route::get('step/{step}/edit', [ProcedureController::class, 'editStep']);
                    Route::put('step/{step}', [ProcedureController::class, 'storeStep']);
                    Route::get('step/{step}/remove/{user}', [ProcedureController::class, 'removeUser']);
                    Route::post('step/addUser', [ProcedureController::class, 'addUser']);
                    Route::put('step/{step}/done', [ProcedureController::class, 'done']);

                    //Step
                    Route::post('{procedure}/step', [ProcedureController::class, 'addStep']);

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
