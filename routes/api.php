<?php

use App\Http\Controllers\API\CallbackController;
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


Route::post('/wave-callback', [CallbackController::class, 'wave_callback'])->name('wave_callback');

Route::post('/omsenegal-callback', [CallbackController::class, 'om_senegal_callback']);