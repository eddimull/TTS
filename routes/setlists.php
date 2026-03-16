<?php

use App\Http\Controllers\CaptainController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\SetlistController;
use App\Http\Controllers\SetlistSuggestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Static setlist
    Route::get('/events/{key}/setlist', [SetlistController::class, 'show'])->name('setlists.show');
    Route::post('/events/{key}/setlist/generate', [SetlistController::class, 'generate'])->name('setlists.generate');
    Route::put('/events/{key}/setlist', [SetlistController::class, 'update'])->name('setlists.update');

    // Live session
    Route::get('/events/{key}/setlist/live', [LiveSessionController::class, 'show'])->name('setlists.live');
    Route::post('/events/{key}/setlist/session', [LiveSessionController::class, 'start'])->name('setlists.session.start');
    Route::delete('/events/{key}/setlist/session', [LiveSessionController::class, 'end'])->name('setlists.session.end');

    // Captain actions
    Route::post('/setlist/sessions/{id}/next', [CaptainController::class, 'next'])->name('setlists.captain.next');
    Route::post('/setlist/sessions/{id}/reaction', [CaptainController::class, 'reaction'])->name('setlists.captain.reaction');
    Route::post('/setlist/sessions/{id}/skip', [CaptainController::class, 'skip'])->name('setlists.captain.skip');
    Route::post('/setlist/sessions/{id}/skip-remove', [CaptainController::class, 'skipRemove'])->name('setlists.captain.skipRemove');
    Route::post('/setlist/sessions/{id}/off-setlist', [CaptainController::class, 'offSetlist'])->name('setlists.captain.offSetlist');
    Route::post('/setlist/sessions/{id}/promote', [CaptainController::class, 'promote'])->name('setlists.captain.promote');
    Route::post('/setlist/sessions/{id}/demote', [CaptainController::class, 'demote'])->name('setlists.captain.demote');

    // AI suggestion
    Route::get('/setlist/sessions/{id}/suggest', [SetlistSuggestionController::class, 'suggest'])->name('setlists.suggest');
    Route::post('/setlist/sessions/{id}/accept-suggestion', [SetlistSuggestionController::class, 'accept'])->name('setlists.acceptSuggestion');
    Route::post('/setlist/sessions/{id}/queuing-next', [SetlistSuggestionController::class, 'queuingNext'])->name('setlists.queuingNext');
});
