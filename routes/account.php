<?php


use Illuminate\Support\Facades\Route;

Route::any('/account', 'AccountController@index')->middleware(['auth', 'verified'])->name('account');
Route::patch('/account/update', 'AccountController@update')->middleware(['auth', 'verified'])->name('account.update');
