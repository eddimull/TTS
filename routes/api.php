<?php

use App\Http\Controllers\PandadocWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeWebhookController;

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

Route::any('/stripe',[StripeWebhookController::class,'index'])->name('webhook.stripe');
Route::any('/pandadoc',[PandadocWebhookController::class,'index'])->name('webhook.pandadoc');


