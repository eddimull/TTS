<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BandsController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\BookedDatesController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\ChartsController;
use App\Http\Controllers\RehearsalController;
use App\Http\Controllers\ChunkedUploadController;

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
    Route::get('/bands/{band}/members', [BandsController::class, 'members'])->name('api.bands.members');
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/charts', [ChartsController::class, 'getChartsForUser']);
    Route::get('/rehearsal/{rehearsal_id}', [RehearsalController::class, 'getRehearsalData'])->name('api.rehearsal.get');
    Route::get('/rehearsal-schedule/{rehearsal_schedule_id}/band/{band_id}', [RehearsalController::class, 'getRehearsalScheduleData'])->name('api.rehearsal-schedule.get');

    // Chunked upload routes
    Route::post('/chunked-uploads/initiate', [ChunkedUploadController::class, 'initiate'])->name('chunked-uploads.initiate');
    Route::get('/chunked-uploads/{uploadId}', [ChunkedUploadController::class, 'getStatus'])->name('chunked-uploads.status');
    Route::post('/chunked-uploads/{uploadId}/chunk', [ChunkedUploadController::class, 'uploadChunk'])->name('chunked-uploads.chunk');
    Route::post('/chunked-uploads/{uploadId}/complete', [ChunkedUploadController::class, 'complete'])->name('chunked-uploads.complete');
});

Route::post('/searchLocations', [LocationController::class, 'searchLocations'])->name('searchLocations');
Route::post('/getLocationDetails', [LocationController::class, 'getLocationDetails'])->name('getLocationDetails');
Route::post('/geocodeAddress', [LocationController::class, 'geocodeAddress'])->name('geocodeAddress');
Route::get('/contracts/{contract:envelope_id}/history', [ContractsController::class, 'getHistory'])->name('getContractHistory');

// Band API routes (token-authenticated)
Route::middleware(['band.api'])->group(function () {
    // Booked Dates - Read Bookings
    Route::get('/booked-dates', [BookedDatesController::class, 'index'])
        ->middleware('api.permission:api:read-bookings')
        ->name('api.booked-dates');

    // Events - Read
    Route::get('/events', [EventsController::class, 'index'])
        ->middleware('api.permission:api:read-events')
        ->name('api.events.index');

    // Bookings - Read
    Route::get('/bookings', [BookingsController::class, 'index'])
        ->middleware('api.permission:api:read-bookings')
        ->name('api.bookings.index');

    Route::get('/bookings/{id}', [BookingsController::class, 'show'])
        ->middleware('api.permission:api:read-bookings')
        ->name('api.bookings.show');

    // Bookings - Write
    Route::post('/bookings', [BookingsController::class, 'store'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.store');

    Route::put('/bookings/{id}', [BookingsController::class, 'update'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.update');

    Route::patch('/bookings/{id}', [BookingsController::class, 'update'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.patch');

    Route::delete('/bookings/{id}', [BookingsController::class, 'destroy'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.destroy');
});
// });


