<?php


use Illuminate\Support\Facades\Route;

Route::any('/account', 'AccountController@index')->middleware(['auth', 'verified'])->name('account');
Route::patch('/account/update', 'AccountController@update')->middleware(['auth', 'verified'])->name('account.update');

// Request account deletion. This does not delete immediately: it emails the
// user a signed, expiring link that performs the irreversible delete (the
// confirm-deletion routes below). Shared by the mobile DELETE /account flow.
Route::post('/account/delete', 'AccountController@requestDeletion')->middleware(['auth', 'verified'])->name('account.delete');

// Public, signed account-deletion confirmation (auth is the signature itself,
// so a logged-out user following the emailed link still reaches it). GET renders
// the confirmation page; POST (from that page, same signed URL) performs the
// irreversible delete — so a GET prefetch can never delete. Used by both web and
// mobile request flows.
Route::get('/account/confirm-deletion/{user}', 'AccountController@confirmDeletion')->name('account.confirm-deletion');
Route::post('/account/confirm-deletion/{user}', 'AccountController@performDeletion')->name('account.perform-deletion');
