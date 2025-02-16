<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->middleware(['guest']);

Route::get('/dashboard', 'DashboardController@index')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Include all route files
require __DIR__ . '/auth.php';
require __DIR__ . '/bands.php';
require __DIR__ . '/booking.php';
require __DIR__ . '/events.php';
require __DIR__ . '/finances.php';
require __DIR__ . '/images.php';
require __DIR__ . '/notifications.php';
require __DIR__ . '/proposals.php';
require __DIR__ . '/questionnaire.php';

URL::forceScheme('https');
