<?php

use App\Http\Controllers\EventsController;
use App\Http\Controllers\FinalizedProposalController;
use App\Http\Controllers\FinancesController;
use App\Http\Controllers\InvitationsController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\QuestionnaireController;
use App\Models\Bands;
use App\Services\AdvanceReminderService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
})->middleware(['guest']);

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
Route::get('/bands/{band}/edit/{setting}', 'BandsController@edit')->middleware(['auth', 'verified'])->name('bands.editSettings');
Route::patch('/bands/{band}', 'BandsController@update')->middleware(['auth', 'verified'])->name('bands.update');
Route::delete('/bands/{band}', 'BandsController@destroy')->middleware(['auth', 'verified'])->name('bands.destroy');
Route::delete('/deleteOwner/{band}/{owner}','BandsController@deleteOwner')->middleware(['auth','verified'])->name('bands.deleteOwner');
Route::post('/bands/{band}/uploadLogo','BandsController@uploadLogo')->middleware(['auth', 'verified'])->name('bands.uploadLogo');
Route::get('/bands/{band}/setupStripe','BandsController@setupStripe')->middleware(['auth', 'verified'])->name('bands.setupStripe');
Route::post('/bands/{band}/syncCalendar','BandsController@syncCalendar')->middleware(['auth', 'verified'])->name('bands.syncCalendar');


Route::get('/events', [EventsController::class,'index'])->middleware(['auth', 'verified'])->name('events');
Route::get('/events/create', [EventsController::class,'create'])->middleware(['auth', 'verified'])->name('events.create');
Route::post('/events', [EventsController::class,'store'])->middleware(['auth', 'verified'])->name('events.store');
Route::get('/events/{key}/edit', [EventsController::class,'edit'])->middleware(['auth', 'verified'])->name('events.edit');
Route::get('/events/{key}/advance', [EventsController::class,'advance'])->name('events.advance');
Route::patch('/events/{key}', [EventsController::class,'update'])->middleware(['auth', 'verified'])->name('events.update');
Route::delete('/events/{key}', [EventsController::class,'destroy'])->middleware(['auth', 'verified'])->name('events.destroy');
Route::get('/events/createAdvance/{id}',[EventsController::class,'createPDF'])->middleware(['auth', 'verified']);
Route::get('/events/downloadPDF/{id}',[EventsController::class,'downloadPDF'])->middleware(['auth', 'verified']);
Route::get('/events/{event:event_key}/locationImage',[EventsController::class,'getGoogleMapsImage'])->name('events.locationImage');
Route::post('/events/createContact/{event:event_key}', [EventsController::class,'createContact'])->middleware(['auth', 'verified'])->name('events.createContact');
Route::post('/events/editContact/{contact}', [EventsController::class,'editContact'])->middleware(['auth', 'verified'])->name('events.editContact');
Route::delete('/events/deleteContact/{contact}', [EventsController::class,'deleteContact'])->middleware(['auth', 'verified'])->name('events.deleteContact');


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
        Route::get('/{proposal:key}/finalize', 'ProposalsController@finalize')->name('proposals.finalize.get');
        Route::post('/{proposal:key}/finalize', 'ProposalsController@finalize')->name('proposals.finalize.post');
        Route::post('/{proposal:key}/sendit', 'ProposalsController@sendIt')->name('proposals.sendIt');
        Route::post('/{proposal:key}/sendContract', 'ProposalsController@sendContract')->name('proposals.sendContract');
        Route::post('/{proposal:key}/writeToCalendar', 'ProposalsController@writeToCalendar')->name('proposals.writeToCalendar');
        Route::post('/createContact/{proposal:key}', 'ProposalsController@createContact')->name('proposals.createContact');
        Route::post('/editContact/{contact}', 'ProposalsController@editContact')->name('proposals.editContact');
        Route::delete('/deleteContact/{contact}', 'ProposalsController@deleteContact')->name('proposals.deleteContact');
        Route::get('/{proposal:key}/payments',[FinalizedProposalController::class,'paymentIndex'])->name('proposals.paymentReview');
        Route::post('/{proposal:key}/payment',[FinalizedProposalController::class,'submitPayment'])->name('proposals.submitPayment');
        Route::delete('/{proposal:key}/deletePayment/{payment}',[FinalizedProposalController::class,'deletePayment'])->name('proposals.deletePayment');
        Route::get('/{proposal:key}/downloadReceipt',[FinalizedProposalController::class,'getReceipt'])->name('proposal.receipt');
    });

    Route::get('paymentpdf/{payment}',[FinalizedProposalController::class,'paymentPDF'])->name('paymentpdf')->middleware('signed');

    Route::get('/{proposal:key}/details', 'ProposalsController@details')->name('proposals.details');
    Route::get('/{proposal:key}/accepted', 'ProposalsController@accepted')->name('proposals.accepted');
    Route::post('/{proposal:key}/accept', 'ProposalsController@accept')->name('proposals.accept');

});
Route::post('/autocompleteLocation','ProposalsController@searchLocations')->middleware(['auth','verified'])->name('proposals.searchLocations');
Route::get('/getLocation','ProposalsController@searchDetails')->middleware(['auth','verified'])->name('proposals.searchDetails');

Route::group(['prefix'=>'finances','middleware'=>['auth','verified']],function(){
    Route::get('/',[FinancesController::class,'index'])->name('finances');
    Route::get('/invoices',[InvoicesController::class,'index'])->name('invoices');
    Route::post('/invoices/{proposal:key}/send',[InvoicesController::class,'create'])->name('invoices.create');
});



Route::get('/images/{uri}','ImageController@index');
Route::get('/images/{band_site}/{uri}','ImageController@siteImages');

Route::post('/inviteOwner/{band_id}',[InvitationsController::class,'createOwner'])->middleware(['auth','verified'])->name('invite.createOwner');
Route::post('/inviteMember/{band_id}',[InvitationsController::class,'createMember'])->middleware(['auth','verified'])->name('invite.createMember');
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

Route::get('advanceTest',function(){
    $bands = Bands::all();

            foreach($bands as $band)
            {
                $reminder = new AdvanceReminderService($band);
                $reminder->searchAndSend();
            }
    return 'sent';
});
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

Route::group(['prefix'=>'questionnaire','middleware'=>['auth','verified']],function(){
    // Route::resource('/','QuestionnaireController'); 
    Route::get('/',[QuestionnaireController::class,'index'])->name('questionnaire');
    Route::post('/new',[QuestionnaireController::class,'store'])->name('questionnaire.new');
    Route::get('/{questionnaire:slug}',[QuestionnaireController::class,'edit'])->name('questionnaire.edit');
    Route::post('/{questionnaire:slug}/add',[QuestionnaireController::class,'addQuestion'])->name('questionnaire.addQuestion');
});


Route::group(['prefix'=>'mail','middleware'=>['dev']],function(){
    Route::get('/payment',function(){
        $payment = App\Models\ProposalPayments::first();
        // $payment->sendReceipt();
        return view('email.payment',[
            'performance'=>$payment->proposal->name,
            'amount'=>$payment->formattedPaymentAmount,
            'balance'=>$payment->proposal->AmountLeft
            ]);
    });

    Route::get('signedRoute',function(){
        $payment = App\Models\ProposalPayments::first();

        return URL::temporarySignedRoute('paymentpdf',now()->addMinutes(1),['payment'=>$payment]);
    });
    Route::get('test',function(){
        $payment = App\Models\ProposalPayments::first();
        $signedURL = URL::temporarySignedRoute('paymentpdf',now()->addMinutes(1),['payment'=>$payment]);
        $pdf = \Spatie\Browsershot\Browsershot::url($signedURL)
            ->setNodeBinary('/home/ec2-user/.nvm/versions/node/v16.3.0/bin/node')
            ->setNpmBinary('/home/ec2-user/.nvm/versions/node/v16.3.0/bin/npm')
            ->format('Legal')
            ->showBackground();

        Storage::put('receipt.pdf',$pdf->pdf());
        return Storage::download('receipt.pdf');
    });
});

Route::any('/info/',function(){
    if(!env('APP_DEBUG'))
    {
        abort(403);
    }
    return phpinfo();
});

URL::forceScheme('https');

require __DIR__.'/auth.php';
