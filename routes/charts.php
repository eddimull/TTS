<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ChartsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/',[ChartsController::class,'index'])
    ->middleware(['auth','charts.read'])
    ->name('charts');

Route::post('new',[ChartsController::class,'store'])
    ->middleware(['auth','verified','charts.write'])
    ->name('charts.write');

Route::get('{chart:id}',[ChartsController::class,'edit'])
    ->middleware(['auth','charts.read'])
    ->name('charts.edit');

Route::post('{chart:id}',[ChartsController::class,'update'])
    ->middleware(['auth','charts.write'])
    ->name('charts.update');    

    Route::delete('{chart:id}',[ChartsController::class,'destroy'])
    ->middleware(['auth','charts.write'])
    ->name('charts.destroy');      

Route::get('{chart:id}/chartDownload/{upload:name}',[ChartsController::class,'getResource'])
    ->middleware(['auth','charts.read'])
    ->name('charts.download');

Route::post('{chart:id}/chartDownload/{upload:id}',[ChartsController::class,'updateResource'])
    ->middleware(['auth','charts.write'])
    ->name('charts.updateResource');

Route::delete('{chart:id}/chartDownload/{upload:id}',[ChartsController::class,'deleteResource'])
    ->middleware(['auth','charts.write'])
    ->name('charts.deleteResource');    

Route::post('{chart:id}/upload',[ChartsController::class,'uploadChartData'])
    ->middleware(['auth','charts.write'])
    ->name('charts.upload');    