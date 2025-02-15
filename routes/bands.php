<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BandsController;
use App\Http\Controllers\InvitationsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('bands')->group(function () {
        Route::get('/', [BandsController::class, 'index'])->name('bands');
        Route::get('/create', [BandsController::class, 'create'])->name('bands.create');
        Route::post('/', [BandsController::class, 'store'])->name('bands.store');
        Route::get('/{band}/edit', [BandsController::class, 'edit'])->name('bands.edit');
        Route::get('/{band}/edit/{setting}', [BandsController::class, 'edit'])->name('bands.editSettings');
        Route::patch('/{band}', [BandsController::class, 'update'])->name('bands.update');
        Route::delete('/{band}', [BandsController::class, 'destroy'])->name('bands.destroy');
        Route::delete('/deleteOwner/{band}/{owner}', [BandsController::class, 'deleteOwner'])->name('bands.deleteOwner');
        Route::post('/{band}/uploadLogo', [BandsController::class, 'uploadLogo'])->name('bands.uploadLogo');
        Route::get('/{band}/setupStripe', [BandsController::class, 'setupStripe'])->name('bands.setupStripe');
        Route::post('/{band}/syncCalendar', [BandsController::class, 'syncCalendar'])->name('bands.syncCalendar');
    });

    // Invitations routes
    Route::post('/inviteOwner/{band_id}', [InvitationsController::class, 'createOwner'])->name('invite.createOwner');
    Route::post('/inviteMember/{band_id}', [InvitationsController::class, 'createMember'])->name('invite.createMember');
    Route::delete('/deleteInvite/{band}/{invitations}', [InvitationsController::class, 'destroy'])->name('invite.delete');
});
