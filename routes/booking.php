<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingsController;

Route::middleware(['auth', 'verified'])->group(function ()
{
    Route::get('bookings', [BookingsController::class, 'index'])->name('bookings.index');
    Route::resource('bands.booking', BookingsController::class)
        ->parameters(['bands' => 'band', 'booking' => 'booking'])
        ->except(['index', 'edit'])
        ->middleware('booking.access');

    Route::get('bands/{band}/booking/create', [BookingsController::class, 'create'])
        ->name('bands.booking.create')
        ->middleware('booking.access');
});
