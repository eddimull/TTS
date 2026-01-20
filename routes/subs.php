<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubInvitationController;

/*
|--------------------------------------------------------------------------
| Substitute Routes
|--------------------------------------------------------------------------
|
| Routes for handling substitute invitations and management
|
*/

// Public route for viewing sub invitation (no auth required)
Route::get('/sub/invitation/{key}', [SubInvitationController::class, 'show'])
    ->name('sub.invitation.show');

// Authenticated sub routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Accept invitation
    Route::post('/sub/invitation/{key}/accept', [SubInvitationController::class, 'accept'])
        ->name('sub.invitation.accept');

    // Get my pending invitations
    Route::get('/sub/invitations', [SubInvitationController::class, 'myInvitations'])
        ->name('sub.invitations');

    // Band owner/member routes for managing subs
    Route::post('/sub/invite', [SubInvitationController::class, 'store'])
        ->name('sub.invite');

    Route::delete('/sub/{eventSubId}', [SubInvitationController::class, 'destroy'])
        ->name('sub.remove');
});
