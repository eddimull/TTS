<?php

use App\Http\Controllers\RehearsalScheduleController;
use App\Http\Controllers\RehearsalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rehearsal Routes
|--------------------------------------------------------------------------
|
| Routes for managing rehearsal schedules and individual rehearsals
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    
    // All bands rehearsal schedules index (no band specified)
    Route::get('rehearsal-schedules', [RehearsalScheduleController::class, 'index'])
        ->name('rehearsal-schedules.index');
    
    // Single band rehearsal schedule routes
    Route::prefix('bands/{band}')->group(function () {
        Route::resource('rehearsal-schedules', RehearsalScheduleController::class)
            ->except(['index']);
        
        // Nested rehearsal routes under schedules
        Route::prefix('rehearsal-schedules/{rehearsal_schedule}')->group(function () {
            Route::resource('rehearsals', RehearsalController::class);
            Route::post('rehearsals/{rehearsal}/toggle-cancelled', [RehearsalController::class, 'toggleCancelled'])
                ->name('rehearsals.toggle-cancelled');
        });
    });
});
