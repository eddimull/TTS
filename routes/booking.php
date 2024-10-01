<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\ContractsController;

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

    Route::get('bands/{band}/booking/{booking}/events', [BookingsController::class, 'events'])
        ->name('Booking Events')
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

    Route::get('bands/{band}/booking/{booking}/contract', [BookingsController::class, 'contract'])
        ->name('Booking Contract')
        ->middleware('booking.access');

    Route::get('bands/{band}/booking/{booking}/contract/download', [BookingsController::class, 'downloadContract'])
        ->name('Download Booking Contract')
        ->middleware('booking.access');

    Route::post('bands/{band}/booking/{booking}/contract/upload', [BookingsController::class, 'uploadContract'])
        ->name('Upload Booking Contract')
        ->middleware('booking.access');

    Route::post('bands/{band}/booking/{booking}/contract/save', [ContractsController::class, 'update'])
        ->name('Update Booking Contract')
        ->middleware('booking.access');

    Route::post('bands/{band}/booking/{booking}/contract/send', [ContractsController::class, 'sendBookingContract'])
        ->name('Send Booking Contract')
        ->middleware('booking.access');

    Route::post('bands/{band}/booking/{booking}/finances', [BookingsController::class, 'storePayment'])
        ->name('Store Booking Payment')
        ->middleware('booking.access');

    Route::delete('bands/{band}/booking/{booking}/finances/{payment}', [BookingsController::class, 'destroyPayment'])
        ->name('Delete Booking Payment')
        ->middleware('booking.access');

    Route::post('bands/{band}/booking/{booking}/events/{event}', [BookingsController::class, 'updateEvent'])
        ->name('Update Booking Event')
        ->middleware('booking.access');
});
Route::get('{booking}/downloadReceiptPDF', [BookingsController::class, 'paymentPDF'])->name('bookingpaymentpdf');
