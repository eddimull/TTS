<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;

Route::middleware(['auth', 'verified'])->group(function ()
{
    Route::get('/booking', [BookingController::class, 'index'])->name('booking');

    // Add other booking-related routes here as needed
    // For example:
    // Route::post('/booking/create', [BookingController::class, 'create'])->name('booking.create');
    // Route::get('/booking/{id}', [BookingController::class, 'show'])->name('booking.show');
});
