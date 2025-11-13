<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FinancesController;
// use App\Http\Controllers\InvoicesController; // Deprecated

Route::middleware(['auth', 'verified'])->prefix('finances')->group(function () {
    Route::get('/', [FinancesController::class, 'index'])->name('finances');
    Route::get('/revenue', [FinancesController::class, 'revenue'])->name('Revenue');
    Route::get('/paidUnpaid', [FinancesController::class, 'paidUnpaid'])->name('Paid/Unpaid');
    Route::get('/unpaidServices', [FinancesController::class, 'unpaidServices'])->name('Unpaid Services');
    Route::get('/paidContracts', [FinancesController::class, 'paidServices'])->name('Paid Services');
    Route::get('/payments', [FinancesController::class, 'payments'])->name('Payments');
    // Removed - invoices page is deprecated
    // Route::get('/invoices', [InvoicesController::class, 'index'])->name('Invoices');
    // Route::post('/invoices/{proposal:key}/send', [InvoicesController::class, 'create'])->name('Create Invoice');
    Route::get('/payout-calculator', [FinancesController::class, 'payoutCalculator'])->name('Payout Calculator');
    Route::post('/payout-config/{bandId}', [FinancesController::class, 'storePayoutConfig'])->name('finances.payout.store');
    Route::put('/payout-config/{bandId}/{configId}', [FinancesController::class, 'updatePayoutConfig'])->name('finances.payout.update');
    Route::delete('/payout-config/{bandId}/{configId}', [FinancesController::class, 'deletePayoutConfig'])->name('finances.payout.delete');
    
    // Payment Groups
    Route::post('/payment-group/{bandId}', [FinancesController::class, 'storePaymentGroup'])->name('finances.paymentGroup.store');
    Route::put('/payment-group/{bandId}/{groupId}', [FinancesController::class, 'updatePaymentGroup'])->name('finances.paymentGroup.update');
    Route::delete('/payment-group/{bandId}/{groupId}', [FinancesController::class, 'deletePaymentGroup'])->name('finances.paymentGroup.delete');
    Route::post('/payment-group/{bandId}/{groupId}/user', [FinancesController::class, 'addUserToPaymentGroup'])->name('finances.paymentGroup.addUser');
    Route::delete('/payment-group/{bandId}/{groupId}/user/{userId}', [FinancesController::class, 'removeUserFromPaymentGroup'])->name('finances.paymentGroup.removeUser');
    Route::put('/payment-group/{bandId}/{groupId}/user/{userId}', [FinancesController::class, 'updateUserInPaymentGroup'])->name('finances.paymentGroup.updateUser');
});


Route::group(['prefix' => 'mail', 'middleware' => ['dev']], function ()
{
    Route::get('/payment', function ()
    {
        $payment = App\Models\ProposalPayments::first();
        // $payment->sendReceipt();
        return view('email.payment', [
            'performance' => $payment->proposal->name,
            'amount' => $payment->formattedPaymentAmount,
            'balance' => $payment->proposal->AmountLeft
        ]);
    });

    Route::get('signedRoute', function ()
    {
        $payment = App\Models\ProposalPayments::first();

        return URL::temporarySignedRoute('paymentpdf', now()->addMinutes(1), ['payment' => $payment]);
    });
    Route::get('test', function ()
    {
        $payment = App\Models\ProposalPayments::first();
        $signedURL = URL::temporarySignedRoute('paymentpdf', now()->addMinutes(1), ['payment' => $payment]);
        $pdf = \Spatie\Browsershot\Browsershot::url($signedURL)
            ->setNodeBinary('/home/ec2-user/.nvm/versions/node/v16.3.0/bin/node')
            ->setNpmBinary('/home/ec2-user/.nvm/versions/node/v16.3.0/bin/npm')
            ->format('Legal')
            ->showBackground();

        Storage::put('receipt.pdf', $pdf->pdf());
        return Storage::download('receipt.pdf');
    });

    Route::get('bookingTest', function ()
    {
        // Browsershot::chrome('/usr/bin/google-chrome');
        // Browsershot::url('https://example.com')
        //     ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
        //     ->pdf('example.pdf');
        // $paidBooking = Bands::first()->getPaidBookings()->last();
        // $signedURL = URL::temporarySignedRoute('paymentpdf', now()->addMinutes(60), ['payment' => $paidBooking]);
        // return redirect($signedURL);
        $pdf = Browsershot::url('https://example.com')
            ->setNodeBinary('/usr/local/bin/node')
            ->setNpmBinary('/usr/local/bin/npm')
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->setOption('executablePath', '/usr/bin/google-chrome')
            ->noSandbox()
            ->setScreenshotType('jpeg', 100)
            ->windowSize(1920, 1080)
            ->timeout(60000)
            ->setEnvironmentOptions([
                'CHROME_CONFIG_HOME' => '/tmp/.config'
            ])
            ->setTemporaryHtmlDirectory('/tmp/browsershot')
            ->save('/tmp/asdfasdf.pdf');

        // $filename = 'receipt_' . $payment->id  . '.pdf';
        // $pdfContent = $pdf->pdf();

        return response()->download('/tmp/asdfasdf.pdf');
        // if (Storage::disk('public')->put($filename, $pdfContent))
        //     // {
        //     return Storage::disk('public')->download($filename, 'Receipt.pdf', ['Content-Type' => 'application/pdf']);
        // }
        // else
        // {
        //     throw new \Exception('Failed to save PDF to storage');
        // }

    });
});
