<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserPermissionsController;
use App\Models\Bands;
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
// Route::get('/permissions/{band}/{user}',function(){
//         return 'test';
//     });

Route::get('/permissions/{band}/{user}',[UserPermissionsController::class,'index'])
        ->middleware(['auth','verified','owner','userInBand'])
        ->name('userpermissions');

Route::post('/permissions/{band}/{user}',[UserPermissionsController::class,'store'])
        ->middleware(['auth','verified','owner','userInBand'])
        ->name('userpermissions.save');        
