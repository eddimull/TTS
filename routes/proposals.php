<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProposalsController;
use App\Http\Controllers\FinalizedProposalController;

Route::prefix('proposals')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [ProposalsController::class, 'index'])->name('proposals');
        Route::get('/{proposal:key}/edit', [ProposalsController::class, 'edit'])->name('proposals.edit');
        Route::patch('/{proposal:key}/update', [ProposalsController::class, 'update'])->name('proposals.update');
        Route::post('/{band:site_name}/create', [ProposalsController::class, 'create'])->name('proposals.create');
        Route::delete('/{proposal:key}/delete', [ProposalsController::class, 'destroy'])->name('proposals.delete');

        // Proposal finalization and contracts
        Route::get('/{proposal:key}/finalize', [ProposalsController::class, 'finalize'])->name('proposals.finalize.get');
        Route::post('/{proposal:key}/finalize', [ProposalsController::class, 'finalize'])->name('proposals.finalize.post');
        Route::post('/{proposal:key}/sendit', [ProposalsController::class, 'sendIt'])->name('proposals.sendIt');
        Route::post('/{proposal:key}/sendContract', [ProposalsController::class, 'sendContract'])->name('proposals.sendContract');
        Route::post('/{proposal:key}/writeToCalendar', [ProposalsController::class, 'writeToCalendar'])->name('proposals.writeToCalendar');

        // Proposal contacts
        Route::post('/createContact/{proposal:key}', [ProposalsController::class, 'createContact'])->name('proposals.createContact');
        Route::post('/editContact/{contact}', [ProposalsController::class, 'editContact'])->name('proposals.editContact');
        Route::delete('/deleteContact/{contact}', [ProposalsController::class, 'deleteContact'])->name('proposals.deleteContact');

        // Payments
        Route::get('/{proposal:key}/payments', [FinalizedProposalController::class, 'paymentIndex'])->name('proposals.paymentReview');
        Route::post('/{proposal:key}/payment', [FinalizedProposalController::class, 'submitPayment'])->name('proposals.submitPayment');
        Route::delete('/{proposal:key}/deletePayment/{payment}', [FinalizedProposalController::class, 'deletePayment'])->name('proposals.deletePayment');
        Route::get('/{proposal:key}/downloadReceipt', [FinalizedProposalController::class, 'getReceipt'])->name('proposal.receipt');
    });


    Route::get('paymentpdf/{payment}', [FinalizedProposalController::class, 'paymentPDF'])->name('paymentpdf')->middleware('signed');
    Route::post('/autocompleteLocation', 'ProposalsController@searchLocations')->middleware(['auth', 'verified'])->name('proposals.searchLocations');
    Route::get('/getLocation', 'ProposalsController@searchDetails')->middleware(['auth', 'verified'])->name('proposals.searchDetails');

    // Public proposal routes
    Route::get('/{proposal:key}/details', [ProposalsController::class, 'details'])->name('proposals.details');
    Route::get('/{proposal:key}/accepted', [ProposalsController::class, 'accepted'])->name('proposals.accepted');
    Route::post('/{proposal:key}/accept', [ProposalsController::class, 'accept'])->name('proposals.accept');
});
