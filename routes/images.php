<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\ImageController;



Route::get('/images/{uri}', [ImageController::class, 'index']);
Route::get('/images/{band_site}/{path?}', [ImageController::class, 'siteImages'])
    ->where('path', '.*');
