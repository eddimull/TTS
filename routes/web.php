<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard','DashboardController@index')->middleware(['auth', 'verified'])->name('dashboard');

Route::any('/account', 'AccountController@index')->middleware(['auth', 'verified'])->name('account');   
Route::patch('/account/update', 'AccountController@update')->middleware(['auth', 'verified'])->name('account.update');   
// Route::get('/bands/create', 'BandsController@create')->middleware(['auth', 'verified'])->name('bands.create');
// Route::post('/bands', 'BandsController@store')->middleware(['auth', 'verified'])->name('bands.store');
// Route::get('/bands/{band}/edit', 'BandsController@edit')->middleware(['auth', 'verified'])->name('bands.edit');
// Route::patch('/bands/{band}', 'BandsController@update')->middleware(['auth', 'verified'])->name('bands.update');
// Route::delete('/bands/{band}', 'BandsController@destroy')->middleware(['auth', 'verified'])->name('bands.destroy');

Route::get('/bands', 'BandsController@index')->middleware(['auth', 'verified'])->name('bands');
Route::get('/bands/create', 'BandsController@create')->middleware(['auth', 'verified'])->name('bands.create');
Route::post('/bands', 'BandsController@store')->middleware(['auth', 'verified'])->name('bands.store');
Route::get('/bands/{band}/edit', 'BandsController@edit')->middleware(['auth', 'verified'])->name('bands.edit');
Route::patch('/bands/{band}', 'BandsController@update')->middleware(['auth', 'verified'])->name('bands.update');
Route::delete('/bands/{band}', 'BandsController@destroy')->middleware(['auth', 'verified'])->name('bands.destroy');


Route::get('/events', 'EventsController@index')->middleware(['auth', 'verified'])->name('events');
Route::get('/events/create', 'EventsController@create')->middleware(['auth', 'verified'])->name('events.create');
Route::post('/events', 'EventsController@store')->middleware(['auth', 'verified'])->name('events.store');
Route::get('/events/{key}/edit', 'EventsController@edit')->middleware(['auth', 'verified'])->name('events.edit');
Route::patch('/events/{key}', 'EventsController@update')->middleware(['auth', 'verified'])->name('events.update');
Route::delete('/events/{key}', 'EventsController@destroy')->middleware(['auth', 'verified'])->name('events.destroy');

Route::get('/colors','ColorsController@index')->middleware(['auth', 'verified'])->name('colors');
Route::put('/colors','ColorsController@store')->middleware(['auth', 'verified'])->name('colors.store');

Route::get('/images/{uri}','ImageController@index');
Route::get('/images/{band_site}/{uri}','ImageController@index');


require __DIR__.'/auth.php';
