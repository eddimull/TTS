<?php

use App\Http\Controllers\LodgingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Cross-band index (nav entry, mirrors rehearsal-schedules.index)
    Route::get('/lodgings', [LodgingController::class, 'index'])->name('lodgings.index');

    Route::prefix('bands/{band}')->group(function () {
        Route::get('/lodgings/create', [LodgingController::class, 'create'])->name('bands.lodgings.create');
        Route::post('/lodgings', [LodgingController::class, 'store'])->name('bands.lodgings.store');
    });

    // Attachment routes BEFORE /lodgings/{lodging} so 'attachments' never
    // binds as a lodging id; belt-and-braces with whereNumber below.
    Route::post('/lodgings/{lodging}/attachments', [LodgingController::class, 'uploadAttachment'])->name('lodgings.attachments.upload');
    Route::get('/lodgings/attachments/{attachment}', [LodgingController::class, 'showAttachment'])->name('lodgings.attachments.show');
    Route::get('/lodgings/attachments/{attachment}/download', [LodgingController::class, 'downloadAttachment'])->name('lodgings.attachments.download');
    Route::delete('/lodgings/attachments/{attachment}', [LodgingController::class, 'destroyAttachment'])->name('lodgings.attachments.destroy');

    Route::get('/lodgings/{lodging}', [LodgingController::class, 'show'])->whereNumber('lodging')->name('lodgings.show');
    Route::get('/lodgings/{lodging}/edit', [LodgingController::class, 'edit'])->whereNumber('lodging')->name('lodgings.edit');
    Route::patch('/lodgings/{lodging}', [LodgingController::class, 'update'])->whereNumber('lodging')->name('lodgings.update');
    Route::delete('/lodgings/{lodging}', [LodgingController::class, 'destroy'])->whereNumber('lodging')->name('lodgings.destroy');
});
