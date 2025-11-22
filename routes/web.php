<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Inertia\Inertia;

Route::get('/', function () {
    // If contact is authenticated, redirect to portal dashboard
    if (Auth::guard('contact')->check()) {
        return redirect()->route('portal.dashboard');
    }
    
    // If regular user is authenticated, redirect to dashboard
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', 'DashboardController@index')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/stats', 'UserStatsController@index')
    ->middleware(['auth', 'verified'])
    ->name('stats');

// Public contract viewing route (for PandaDoc and external access with token)
Route::get('/contracts/{contractId}/public', [App\Http\Controllers\ContractsController::class, 'publicView'])
    ->name('contracts.public.view');

// Include all route files
require __DIR__ . '/account.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/bands.php';
require __DIR__ . '/booking.php';
require __DIR__ . '/events.php';
require __DIR__ . '/finances.php';
require __DIR__ . '/images.php';
require __DIR__ . '/notifications.php';
require __DIR__ . '/proposals.php';
require __DIR__ . '/questionnaire.php';
require __DIR__ . '/rehearsals.php';

URL::forceScheme('https');
