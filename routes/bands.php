<?php

use App\Models\BandCalendars;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BandsController;
use App\Http\Controllers\InvitationsController;
use App\Http\Controllers\BandCalendarController;
use App\Http\Controllers\CalendarAccessController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('bands')->group(function () {
        Route::get('/', [BandsController::class, 'index'])->name('bands');
        Route::get('/create', [BandsController::class, 'create'])->name('bands.create');
        Route::post('/', [BandsController::class, 'store'])->name('bands.store');
        Route::get('/{band}/edit', [BandsController::class, 'edit'])->middleware(['userInBand'])->name('bands.edit');
        Route::get('/{band}/edit/{setting}', [BandsController::class, 'edit'])->middleware(['userInBand'])->name('bands.editSettings');
        Route::patch('/{band}', [BandsController::class, 'update'])->middleware(['owner'])->name('bands.update');
        Route::delete('/{band}', [BandsController::class, 'destroy'])->middleware(['owner'])->name('bands.destroy');
        Route::delete('/deleteOwner/{band}/{owner}', [BandsController::class, 'deleteOwner'])->middleware(['owner'])->name('bands.deleteOwner');
        Route::delete('/{band}/members/{user}', [BandsController::class, 'deleteMember'])->middleware(['owner'])->name('bands.deleteMember');
        Route::post('/{band}/uploadLogo', [BandsController::class, 'uploadLogo'])->middleware(['owner'])->name('bands.uploadLogo');
        Route::get('/{band}/setupStripe', [BandsController::class, 'setupStripe'])->middleware(['owner'])->name('bands.setupStripe');
        Route::post('/{band}/syncBandCalendarAccess', [BandsController::class, 'syncBandCalendarAccess'])->middleware(['owner'])->name('bands.syncBandCalendarAccess');
        
    });
    
    Route::post('/grantCalendarAccess/{calendar_id}', [CalendarAccessController::class, 'create'])->middleware(['CalendarOwner'])->name('bands.grantCalendarAccess');
    Route::delete('/revokeCalendarAccess/{calendar_id}/{user}', [CalendarAccessController::class, 'destroy'])->middleware(['CalendarOwner'])->name('bands.revokeCalendarAccess');
    Route::post('/createCalendar/{band}/{type}', [BandCalendarController::class, 'create'])->middleware(['owner'])->name('bands.createCalendar')->whereIn('type', ['booking','event','public']);
    //this is an odd place for this route
    Route::post('/syncCalendar/{calendar_id}', [BandCalendarController::class, 'syncCalendar'])->middleware(['CalendarOwner'])->name('bands.syncCalendar');
    
    // Invitations routes
    Route::post('/inviteOwner/{band_id}', [InvitationsController::class, 'createOwner'])->name('invite.createOwner');
    Route::post('/inviteMember/{band_id}', [InvitationsController::class, 'createMember'])->name('invite.createMember');
    Route::delete('/deleteInvite/{band}/{invitations}', [InvitationsController::class, 'destroy'])->name('invite.delete');
});
