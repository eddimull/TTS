<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SongsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/songs', [SongsController::class, 'index'])
        ->middleware('songs.read')
        ->name('songs.index');

    Route::post('/songs', [SongsController::class, 'store'])
        ->middleware('songs.write')
        ->name('songs.store');

    Route::patch('/songs/{song}', [SongsController::class, 'update'])
        ->middleware('songs.write')
        ->name('songs.update');

    Route::delete('/songs/{song}', [SongsController::class, 'destroy'])
        ->middleware('songs.write')
        ->name('songs.destroy');
});
