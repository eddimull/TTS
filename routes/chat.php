<?php

use App\Http\Controllers\Api\Mobile\ConversationsController;
use App\Http\Controllers\Api\Mobile\MessageReactionsController;
use App\Http\Controllers\Api\Mobile\MessagesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat / comments — session-authenticated web surface
|--------------------------------------------------------------------------
| The same controllers as /api/mobile (they are band-agnostic and gate on
| ConversationPolicy), exposed under the web guard so Inertia pages can use
| axios + Ziggy route names. Only what the comments drawer needs is
| registered here; the conversation list / DM / contacts / delivered
| endpoints arrive with the Messages slice.
*/

Route::middleware(['auth', 'verified'])->name('chat.')->group(function () {
    Route::get('chat/events/{event}/conversation', [ConversationsController::class, 'forEvent'])
        ->name('events.conversation');
    Route::get('chat/rehearsals/{rehearsal}/conversation', [ConversationsController::class, 'forRehearsal'])
        ->name('rehearsals.conversation');
    // Mirrors the mobile asymmetry: booking threads are band-scoped and sit
    // behind the same gate as the booking pages (owners + members only).
    Route::get('bands/{band}/booking/{booking}/conversation', [ConversationsController::class, 'forBooking'])
        ->middleware('booking.access')
        ->scopeBindings()
        ->name('bookings.conversation');

    Route::get('chat/conversations/{conversation}/messages', [ConversationsController::class, 'messages'])
        ->name('conversations.messages.index');
    Route::post('chat/conversations/{conversation}/messages', [ConversationsController::class, 'storeMessage'])
        ->name('conversations.messages.store');
    Route::post('chat/conversations/{conversation}/read', [ConversationsController::class, 'read'])
        ->name('conversations.read');
    Route::post('chat/conversations/{conversation}/typing', [ConversationsController::class, 'typing'])
        ->middleware('throttle:chat-typing')
        ->name('conversations.typing');

    Route::patch('chat/messages/{message}', [MessagesController::class, 'update'])->name('messages.update');
    Route::delete('chat/messages/{message}', [MessagesController::class, 'destroy'])->name('messages.destroy');
    Route::get('chat/messages/{message}/attachments/{attachment}', [MessagesController::class, 'attachment'])
        ->name('messages.attachments.show');

    Route::post('chat/messages/{message}/reactions', [MessageReactionsController::class, 'store'])
        ->name('messages.reactions.store');
    Route::delete('chat/messages/{message}/reactions/{emoji}', [MessageReactionsController::class, 'destroy'])
        ->name('messages.reactions.destroy');
});
