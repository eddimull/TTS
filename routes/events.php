<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\EventAttachmentsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('events')->group(function () {
        Route::get('/', [EventsController::class, 'index'])->name('events');
        Route::get('/create', [EventsController::class, 'create'])->name('events.create');
        Route::post('/', [EventsController::class, 'store'])->name('events.store');
        Route::get('/{key}/edit', [EventsController::class, 'edit'])->name('events.edit');
        Route::get('/{key}/advance', [EventsController::class, 'advance'])->name('events.advance');
        Route::get('/{key}/history', [EventsController::class, 'history'])->name('events.history');
        Route::get('/{key}/history-json', [EventsController::class, 'historyJson'])->name('events.historyJson');
        Route::patch('/{key}', [EventsController::class, 'update'])->name('events.update');
        Route::delete('/{key}', [EventsController::class, 'destroy'])->name('events.destroy');
        Route::get('/createAdvance/{id}', [EventsController::class, 'createPDF']);
        Route::get('/downloadPDF/{id}', [EventsController::class, 'downloadPDF']);
        Route::get('/{event:key}/locationImage', [EventsController::class, 'getGoogleMapsImage'])->name('events.locationImage');

        // Event contacts
        Route::post('/createContact/{event:event_key}', [EventsController::class, 'createContact'])->name('events.createContact');
        Route::post('/editContact/{contact}', [EventsController::class, 'editContact'])->name('events.editContact');
        Route::delete('/deleteContact/{contact}', [EventsController::class, 'deleteContact'])->name('events.deleteContact');

        // Event attachments
        Route::get('/{event}/attachments', [EventAttachmentsController::class, 'index'])->name('events.attachments.index');
        Route::post('/{event}/attachments', [EventAttachmentsController::class, 'upload'])->name('events.attachments.upload');
        Route::post('/{event}/attachments/convert-image', [EventAttachmentsController::class, 'convertImageToAttachment'])->name('events.attachments.convertImage');
        Route::get('/attachments/{attachment}', [EventAttachmentsController::class, 'show'])->name('events.attachments.show');
        Route::get('/attachments/{attachment}/download', [EventAttachmentsController::class, 'download'])->name('events.attachments.download');
        Route::delete('/attachments/{attachment}', [EventAttachmentsController::class, 'destroy'])->name('events.attachments.destroy');
    });
});
