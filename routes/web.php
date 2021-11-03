<?php

use App\Http\Controllers\EventsController;
use App\Http\Controllers\FinancesController;
use App\Http\Controllers\InvoicesController;
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
Route::post('/bands/{band}/uploadLogo','BandsController@uploadLogo')->middleware(['auth', 'verified'])->name('bands.uploadLogo');
Route::get('/bands/{band}/setupStripe','BandsController@setupStripe')->middleware(['auth', 'verified'])->name('bands.setupStripe');


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
Route::post('/events/createContact/{event:event_key}', 'EventsController@createContact')->middleware(['auth', 'verified'])->name('events.createContact');
Route::post('/events/editContact/{contact}', 'EventsController@editContact')->middleware(['auth', 'verified'])->name('events.editContact');
Route::delete('/events/deleteContact/{contact}', 'EventsController@deleteContact')->middleware(['auth', 'verified'])->name('events.deleteContact');


Route::get('/colors','ColorsController@index')->middleware(['auth', 'verified'])->name('colors');
Route::post('/colors','ColorsController@store')->middleware(['auth', 'verified'])->name('colors.store');
Route::delete('/colors/{id}','ColorsController@destroy')->middleware(['auth', 'verified'])->name('colors.destroy');
Route::patch('/colors/{id}','ColorsController@update')->middleware(['auth', 'verified'])->name('colors.update');
Route::group(['prefix'=>'proposals'],function(){
    Route::group(['middleware'=>['auth','verified']],function(){
        Route::get('/', 'ProposalsController@index')->name('proposals');
        Route::get('/{proposal:key}/edit', 'ProposalsController@edit')->name('proposals.edit');
        Route::patch('/{proposal:key}/update', 'ProposalsController@update')->name('proposals.update');
        Route::post('/{band:site_name}/create', 'ProposalsController@create')->name('proposals.create');
        Route::delete('/{proposal:key}/delete','ProposalsController@destroy')->name('proposals.delete');
        Route::get('/{proposal:key}/finalize', 'ProposalsController@finalize')->name('proposals.finalize');
        Route::post('/{proposal:key}/finalize', 'ProposalsController@finalize')->name('proposals.finalize');
        Route::post('/{proposal:key}/sendit', 'ProposalsController@sendIt')->name('proposals.sendIt');
        Route::post('/{proposal:key}/sendContract', 'ProposalsController@sendContract')->name('proposals.sendContract');
        Route::post('/{proposal:key}/writeToCalendar', 'ProposalsController@writeToCalendar')->name('proposals.writeToCalendar');
        Route::post('/createContact/{proposal:key}', 'ProposalsController@createContact')->name('proposals.createContact');
        Route::post('/editContact/{contact}', 'ProposalsController@editContact')->name('proposals.editContact');
        Route::delete('/deleteContact/{contact}', 'ProposalsController@deleteContact')->name('proposals.deleteContact');
    });

    Route::get('/{proposal:key}/details', 'ProposalsController@details')->name('proposals.details');
    Route::get('/{proposal:key}/accepted', 'ProposalsController@accepted')->name('proposals.accepted');
    Route::post('/{proposal:key}/accept', 'ProposalsController@accept')->name('proposals.accept');

});
Route::post('/autocompleteLocation','ProposalsController@searchLocations')->middleware(['auth','verified'])->name('proposals.search');
Route::get('/getLocation','ProposalsController@searchDetails')->middleware(['auth','verified'])->name('proposals.search');

Route::group(['prefix'=>'finances','middleware'=>['auth','verified']],function(){
    Route::get('/',[FinancesController::class,'index'])->name('finances');
    Route::get('/invoices',)->name('invoices');
    Route::post('/invoices/{proposal:key}/send',[InvoicesController::class,'create'])->name('invoices.create');
});

Route::get('/images/{uri}','ImageController@index');
Route::get('/images/{band_site}/{uri}','ImageController@siteImages');

Route::post('/inviteOwner/{band_id}','InvitationsController@createOwner')->middleware(['auth','verified'])->name('invite.createOwner');
Route::post('/inviteMember/{band_id}','InvitationsController@createMember')->middleware(['auth','verified'])->name('invite.createMember');
Route::delete('/deleteInvite/{band}/{invitations}','InvitationsController@destroy')->middleware(['auth','verified'])->name('invite.delete');


Route::get('/contracts','ContractsController@index')->name('contracts');

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


Route::any('/info/',function(){
    return phpinfo();
});

require __DIR__.'/auth.php';
