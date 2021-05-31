<?php

use App\Http\Controllers\EventsController;
use App\Mail\Proposal;
use App\Models\ProposalContacts;
use App\Models\Bands;
use App\Models\Proposals;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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
Route::delete('/deleteOwner/{band}/{owner}','BandsController@deleteOwner')->middleware(['auth','verified'])->name('bands.deleteOwner');


Route::get('/events', 'EventsController@index')->middleware(['auth', 'verified'])->name('events');
Route::get('/events/create', 'EventsController@create')->middleware(['auth', 'verified'])->name('events.create');
Route::post('/events', 'EventsController@store')->middleware(['auth', 'verified'])->name('events.store');
Route::get('/events/{key}/edit', 'EventsController@edit')->middleware(['auth', 'verified'])->name('events.edit');
Route::get('/events/{key}/advance', 'EventsController@advance')->name('events.advance');
Route::patch('/events/{key}', 'EventsController@update')->middleware(['auth', 'verified'])->name('events.update');
Route::delete('/events/{key}', 'EventsController@destroy')->middleware(['auth', 'verified'])->name('events.destroy');
Route::get('/events/createAdvance/{id}','EventsController@createPDF')->middleware(['auth', 'verified']);
Route::get('/events/downloadPDF/{id}','EventsController@downloadPDF')->middleware(['auth', 'verified']);
Route::get('/events/{event:event_key}/locationImage','EventsController@getGoogleMapsImage')->name('events.locationImage');


Route::get('/colors','ColorsController@index')->middleware(['auth', 'verified'])->name('colors');
Route::post('/colors','ColorsController@store')->middleware(['auth', 'verified'])->name('colors.store');
Route::delete('/colors/{id}','ColorsController@destroy')->middleware(['auth', 'verified'])->name('colors.destroy');
Route::patch('/colors/{id}','ColorsController@update')->middleware(['auth', 'verified'])->name('colors.update');

Route::get('/proposals', 'ProposalsController@index')->middleware(['auth', 'verified'])->name('proposals');
Route::get('/proposals/{proposal:key}/edit', 'ProposalsController@edit')->middleware(['auth', 'verified'])->name('proposals.edit');
Route::patch('/proposals/{proposal:key}/update', 'ProposalsController@update')->middleware(['auth','verified'])->name('proposals.update');
Route::post('/proposals/{band:site_name}/create', 'ProposalsController@create')->middleware(['auth','verified'])->name('proposals.create');
Route::delete('/proposals/{proposal:key}/delete','ProposalsController@destroy')->middleware(['auth','verified'])->name('proposals.delete');
Route::post('/proposals/{proposal:key}/finalize', 'ProposalsController@finalize')->middleware(['auth','verified'])->name('proposals.finalize');
Route::post('/proposals/{proposal:key}/sendit', 'ProposalsController@sendIt')->middleware(['auth','verified'])->name('proposals.finalize');
Route::get('/proposals/{proposal:key}/details', 'ProposalsController@details')->name('proposals.details');
Route::get('/proposals/{proposal:key}/accepted', 'ProposalsController@accepted')->name('proposals.accepted');
Route::post('/proposals/{proposal:key}/accept', 'ProposalsController@accept')->name('proposals.accept');
Route::post('/proposals/createContact/{proposal:key}', 'ProposalsController@createContact')->middleware(['auth', 'verified'])->name('proposals.createContact');
Route::post('/proposals/editContact/{contact}', 'ProposalsController@editContact')->middleware(['auth', 'verified'])->name('proposals.editContact');
Route::delete('/proposals/deleteContact/{contact}', 'ProposalsController@deleteContact')->middleware(['auth', 'verified'])->name('proposals.deleteContact');
Route::post('/autocompleteLocation','ProposalsController@searchLocations')->middleware(['auth','verified'])->name('proposals.search');

Route::get('/images/{uri}','ImageController@index');
Route::get('/images/{band_site}/{uri}','ImageController@siteImages');

Route::post('/inviteOwner/{band_id}','InvitationsController@createOwner')->middleware(['auth','verified'])->name('invite.createOwner');
Route::post('/inviteMember/{band_id}','InvitationsController@createMember')->middleware(['auth','verified'])->name('invite.createMember');
Route::delete('/deleteInvite/{band}/{invitations}','InvitationsController@destroy')->middleware(['auth','verified'])->name('invite.delete');

Route::get('/notifications',function(){
    return json_encode(Auth::user()->Notifications);
});
Route::post('/notification/{id}',function($id){
    $notification = Auth::user()->Notifications->find($id);
    if($notification)
    {
        $notification->markAsRead();
    }

    return false;
})->middleware(['auth','verified']);


Route::post('/readAllNotifications',function(){
    $notifications = Auth::user()->unreadNotifications;
    foreach($notifications as $notification)
    {
        $notification->markAsRead();
    }

    return false;
})->middleware(['auth','verified']);


Route::post('/seentIt',function(){
    $notifications = Auth::user()->unreadNotifications;
    foreach($notifications as $notification)
    {
        $notification->markAsSeen();
    }

    return false;
})->middleware(['auth','verified']);

require __DIR__.'/auth.php';
