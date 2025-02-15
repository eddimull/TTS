<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', function () {
        return json_encode(Auth::user()->Notifications);
    });

    Route::post('/notification/{id}', function ($id) {
        $notification = Auth::user()->Notifications->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
        return false;
    });

    Route::post('/readAllNotifications', function () {
        $notifications = Auth::user()->unreadNotifications;
        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }
        return false;
    });

    Route::post('/seentIt', function () {
        $notifications = Auth::user()->unreadNotifications;
        foreach ($notifications as $notification) {
            $notification->markAsSeen();
        }
        return false;
    });
});
