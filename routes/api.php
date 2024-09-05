<?php

use App\Http\Controllers\API\VertretungsplanImportController;
use App\Http\Controllers\VertretungsplanController;
use Illuminate\Http\Request;
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

Route::put('/vertretungen/{key}/vp', [VertretungsplanImportController::class, 'import']);

Route::get('/absences/vertretungsplan/{key}/', [VertretungsplanController::class, 'absencesToJSON']);
