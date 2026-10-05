<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Events\NotificationChanged;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', function () {
        return json_encode(Auth::user()->Notifications);
    });

    Route::post('/notification/{id}', function ($id) {
        $notification = Auth::user()->Notifications->find($id);
        if ($notification) {
            $notification->markAsRead();
            NotificationChanged::dispatch(Auth::id(), $notification->id, 'read');
        }
        return false;
    });

    Route::post('/readAllNotifications', function () {
        $notifications = Auth::user()->unreadNotifications;
        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }
        NotificationChanged::dispatch(Auth::id(), null, 'read');
        return false;
    });

    Route::post('/seentIt', function () {
        $notifications = Auth::user()->unreadNotifications;
        foreach ($notifications as $notification) {
            $notification->markAsSeen();
        }
        NotificationChanged::dispatch(Auth::id(), null, 'seen');
        return false;
    });
});
