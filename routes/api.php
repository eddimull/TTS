<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BandsController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\ChartsController;

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

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::any('/stripe', [StripeWebhookController::class, 'index'])->name('webhook.stripe');


Route::get('/getAllEventTypes', [EventTypeController::class, 'getAllEventTypes'])->name('getAllEventTypes');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/bands/{band}/contacts', [BandsController::class, 'contacts']);
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/charts', [ChartsController::class, 'getChartsForUser']);
});

Route::post('/searchLocations', [LocationController::class, 'searchLocations'])->name('searchLocations');
Route::post('/getLocationDetails', [LocationController::class, 'getLocationDetails'])->name('getLocationDetails');
Route::get('/contracts/{contract:envelope_id}/history', [ContractsController::class, 'getHistory'])->name('getContractHistory');
// });


