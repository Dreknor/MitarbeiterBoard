<?php

use Illuminate\Support\Facades\Route;

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

Route::get('share/{uuid}', 'ShareController@getShare');
Route::post('share/{share}/protocol', 'ShareController@protocol');

Route::group([
    'middleware' => ['auth'],
],
    function () {

        Route::get('password/expired', 'Auth\ExpiredPasswordController@expired')
            ->name('password.expired');
        Route::post('password/post_expired', 'Auth\ExpiredPasswordController@postExpired')
            ->name('password.post_expired');

        Route::group([
            'middleware' => ['password_expired'],
        ],
            function () {
                //Subscriptions
                Route::get('subscription/{type}/{id}', 'SubscriptionController@add');
                Route::get('subscription/{type}/{id}/remove', 'SubscriptionController@remove');


                Route::get('/home', 'HomeController@index')->name('home');
                Route::get('/', 'HomeController@index');

                //Themes
                Route::resource('{groupname}/themes', 'ThemeController');
                Route::get('{groupname}/view/{viewType}', 'ThemeController@setView');
                Route::get('{groupname}/archive', 'ThemeController@archive');
                Route::get('{groupname}/themes/{theme}/close', 'ThemeController@closeTheme');
                Route::post('share/{theme}', 'ShareController@shareTheme');

                Route::delete('share/{theme}', 'ShareController@removeShare');


                //Prioritäten
                Route::post('priorities', 'PriorityController@store');


                //Protocols
                Route::get('{groupname}/protocols/{theme}', 'ProtocolController@create');
                Route::post('{groupname}/protocols/{theme}', 'ProtocolController@store');
                Route::get('{groupname}/protocols/{protocol}/edit', 'ProtocolController@edit');
                Route::get('{groupname}/export/{date?}/', 'ProtocolController@createSheet');
                Route::put('{groupname}/protocols/{protocol}/', 'ProtocolController@update');

                Route::post('{groupname}/search', 'SearchController@search');
                Route::get('{groupname}/search', 'SearchController@show');

                Route::get('image/{media_id}', 'ImageController@getImage');
                //Route::get('reminder', 'MailController@remind');
                //Route::get('einladung', 'MailController@invitation');
                //Route::get('delete', 'GroupController@deleteOldGroups');

                //Route::get('import/', 'ImportController@show');
                //Route::post('import/', 'ImportController@import');

                //Roles and permissions
                Route::group(['middleware' => ['permission:edit permissions']], function () {
                    Route::get('roles', 'RolesController@edit');
                    Route::put('roles', 'RolesController@update');
                    Route::post('roles', 'RolesController@store');
                    Route::post('roles/permission', 'RolesController@storePermission');

                    Route::get('user', 'UserController@index');
                });

                //User-Route
                Route::resource('users', 'UserController');
                Route::get('importuser', 'UserController@importFromElternInfoBoard');

                //Gruppen-Route
                Route::get('groups', 'GroupController@index');
                Route::post('groups', 'GroupController@store');
                Route::put('{groupname}/addUser', 'GroupController@addUser');
                Route::delete('{groupname}/removeUser', 'GroupController@removeUser');

                //Tasks
                Route::post('{groupname}/{theme}/tasks', 'TaskController@store');
                Route::get('tasks/{task}/complete', 'TaskController@complete');
                //Route::get('remindtask', 'MailController@remindTaskMail');

                //Push-Notification
                Route::post('{groupname?}/push', 'PushController@store');
                Route::post('push', 'PushController@store');

                Route::group(['middlewareGroups' => ['role:Admin']], function () {
                    Route::get('showUser/{id}', 'UserController@loginAsUser');
                    Route::get('test', 'PushController@push');
                });

                Route::get('logoutAsUser', function () {
                    if (session()->has('ownID')) {
                        \Illuminate\Support\Facades\Auth::loginUsingId(session()->pull('ownID'));
                    }
                    return redirect(url('/'));
                });

                //Route::get('kiosk', 'KioskController@index');

                Route::prefix('procedure')->group(function () {
                    Route::get('/', 'ProcedureController@index');

                    //Procedures
                    Route::post('create/template', "ProcedureController@storeTemplate");
                    Route::get('{procedure}/edit', "ProcedureController@edit");
                    Route::get('{procedure}/start', "ProcedureController@start");
                    Route::post('{procedure}/start', "ProcedureController@startNow");
                    Route::get('step/{step}/edit', "ProcedureController@editStep");
                    Route::put('step/{step}', "ProcedureController@storeStep");
                    Route::get('step/{step}/remove/{user}', "ProcedureController@removeUser");
                    Route::post('step/addUser', "ProcedureController@addUser");
                    Route::put('step/{step}/done', "ProcedureController@done");



                    //Step
                    Route::post('{procedure}/step', "ProcedureController@addStep");


                    //positions
                    Route::get('/positions', 'PositionsController@index');
                    Route::post('/positions/{position}/add', 'PositionsController@addUser');
                    Route::get('/positions/{positions}/remove/{users}', 'PositionsController@removeUser');


                    //Categories
                    Route::post('categories', 'CategoryController@store');//Categories
                    Route::post('position', 'PositionsController@store');
                });
            });
    });
