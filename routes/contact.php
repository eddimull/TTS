<?php

use App\Http\Controllers\Contact\ContactAuthController;
use App\Http\Controllers\Contact\ContactPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contact Portal Routes
|--------------------------------------------------------------------------
|
| Routes for booking contacts to log in and manage their payments
|
*/

// Root portal route - redirect based on authentication status
Route::get('/', function () {
    return redirect()->route(
        auth('contact')->check() ? 'portal.dashboard' : 'portal.login'
    );
})->name('portal.index');

// Guest routes (not authenticated)
Route::middleware('guest:contact')->group(function () {
    Route::get('/login', [ContactAuthController::class, 'showLogin'])->name('portal.login');
    Route::post('/login', [ContactAuthController::class, 'login']);

    Route::get('/forgot-password', [ContactAuthController::class, 'showForgotPassword'])->name('portal.password.request');
    Route::post('/forgot-password', [ContactAuthController::class, 'sendResetLink'])->name('portal.password.email');

    Route::get('/reset-password/{token}', [ContactAuthController::class, 'showResetPassword'])->name('portal.password.reset');
    Route::post('/reset-password', [ContactAuthController::class, 'resetPassword'])->name('portal.password.update');
});

// Authenticated contact routes
Route::middleware('auth:contact')->group(function () {
    Route::get('/dashboard', [ContactPortalController::class, 'dashboard'])->name('portal.dashboard');
    Route::post('/logout', [ContactAuthController::class, 'logout'])->name('portal.logout');

    // Password change (for temporary passwords)
    Route::get('/change-password', [ContactAuthController::class, 'showChangePassword'])->name('portal.password.change');
    Route::post('/change-password', [ContactAuthController::class, 'changePassword']);

    // Payment routes
    Route::get('/booking/{booking}/payment', [ContactPortalController::class, 'showPayment'])->name('portal.booking.payment');
    Route::post('/booking/{booking}/checkout', [ContactPortalController::class, 'createCheckoutSession'])->name('portal.booking.checkout');

    // Payment history
    Route::get('/payment-history', [ContactPortalController::class, 'paymentHistory'])->name('portal.payment.history');

    // Invoices
    Route::get('/invoices', [ContactPortalController::class, 'invoices'])->name('portal.invoices');

    // Contract download
    Route::get('/booking/{booking}/contract', [ContactPortalController::class, 'downloadContract'])->name('portal.booking.contract');

    // Media gallery
    Route::get('/media', [ContactPortalController::class, 'media'])->name('portal.media');
    Route::get('/media/{media}/download', [ContactPortalController::class, 'downloadMedia'])->name('portal.media.download');
    Route::get('/media/{media}/thumbnail', [ContactPortalController::class, 'serveMediaThumbnail'])->name('portal.media.thumbnail');
    Route::get('/media/{media}/serve', [ContactPortalController::class, 'serveMedia'])->name('portal.media.serve');

    // Payment callbacks
    Route::get('/payment/success', [ContactPortalController::class, 'paymentSuccess'])->name('portal.payment.success');
    Route::get('/payment/cancelled', [ContactPortalController::class, 'paymentCancelled'])->name('portal.payment.cancelled');
});
