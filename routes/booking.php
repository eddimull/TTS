<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingsController;

Route::middleware(['auth', 'verified'])->group(function ()
{
    Route::get('bookings', [BookingsController::class, 'index'])->name('Bookings Home');
    Route::resource('bands.booking', BookingsController::class)
        ->parameters(['bands' => 'band', 'booking' => 'booking'])
        ->except(['index', 'edit'])
        ->middleware('booking.access')
        ->names([
            'show' => 'Booking Details',
        ]);

    Route::get('bands/{band}/booking/create', [BookingsController::class, 'create'])
        ->name('Create Booking')
        ->middleware('booking.access');

    Route::get('bands/{band}/booking/{booking}/downloadReceipt', [BookingsController::class, 'receipt'])
        ->name('Booking Receipt')
        ->middleware('booking.access');

    Route::get('bands/{band}/booking/{booking}/contacts', [BookingsController::class, 'contacts'])
        ->name('Booking Contacts')
        ->middleware('booking.access');

    Route::post('bands/{band}/booking/{booking}/contacts', [BookingsController::class, 'storeContact'])
        ->name('Store Booking Contact')
        ->middleware('booking.access');

    Route::put('bands/{band}/booking/{booking}/contacts/{contact}', [BookingsController::class, 'updateContact'])
        ->name('Update Booking Contact')
        ->middleware('booking.access');

    Route::delete('bands/{band}/booking/{booking}/{contact}', [BookingsController::class, 'destroyContact'])
        ->name('Delete Booking Contact')
        ->middleware('booking.access');

    Route::get('bands/{band}/booking/{booking}/finances', [BookingsController::class, 'finances'])
        ->name('Booking Finances')
        ->middleware('booking.access');

    Route::post('bands/{band}/booking/{booking}/finances', [BookingsController::class, 'storePayment'])
        ->name('Store Booking Payment')
        ->middleware('booking.access');
});
Route::get('{booking}/downloadReceiptPDF', [BookingsController::class, 'paymentPDF'])->name('bookingpaymentpdf');
