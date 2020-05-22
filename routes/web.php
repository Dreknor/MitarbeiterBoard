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

Route::group([
    'middleware' => ['auth'],
],
    function () {
        Route::get('/home', 'HomeController@index')->name('home');
        Route::get('/', 'HomeController@index');
        Route::resource('{groupname}/themes', 'ThemeController');
        Route::get('{groupname}/archive', 'ThemeController@archive');
        Route::post('priorities', 'PriorityController@store');
        //Route::get('priorities', 'PriorityController@store');
        Route::get('protocols/{theme}', 'ProtocolController@create');
        Route::post('protocols/{theme}', 'ProtocolController@store');

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

        //Gruppen-Route
        Route::get('groups', 'GroupController@index');
        Route::post('groups', 'GroupController@store');
        Route::put('{groupname}/addUser', 'GroupController@addUser');



        Route::group(['middlewareGroups' => ['role:Admin']], function () {
            Route::get('showUser/{id}', 'UserController@loginAsUser');
        });

        Route::get('logoutAsUser', function (){
            if (session()->has('ownID')){
                \Illuminate\Support\Facades\Auth::loginUsingId(session()->pull('ownID'));
            }
            return redirect(url('/'));
        });

    });
