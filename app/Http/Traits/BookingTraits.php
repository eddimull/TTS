<?php

namespace App\Http\Traits;

use App\Mail\PaymentMade;
use Illuminate\Support\Facades\URL;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Mail;

trait BookingTraits
{
    public function sendReceipt()
    {
        $contacts = $this->proposal->ProposalContacts;
        foreach ($contacts as $contact)
        {
            Mail::to($contact->email)->send(new PaymentMade($this));
        }
    }

    public function getPdf()
    {

        $signedURL = URL::temporarySignedRoute('bookingpaymentpdf', now()->addMinutes(5), ['booking' => $this]);
        // $pdf = \Spatie\Browsershot\Browsershot::url($signedURL)
        //     ->setNodeBinary(env('NODE_BINARY', '/usr/bin/node'))
        //     ->setNpmBinary(env('NPM_BINARY', '/usr/bin/npm'))
        //     ->format('Legal')
        //     ->showBackground();
        $signedURL = str_replace('https://', 'http://', $signedURL);
        $modifiedURL = str_replace(':8710', '', $signedURL);
        // $modifiedURL = str_replace(':8080', '', $modifiedURL);

        $moreModifiedURL = str_replace('localhost', 'host.docker.internal', $modifiedURL);
        // dd($moreModifiedURL);
        $pdf = Browsershot::url($moreModifiedURL)
            ->ignoreHttpsErrors()
            ->setNodeBinary('/usr/local/bin/node')
            ->setNpmBinary('/usr/local/bin/npm')
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->setOption('executablePath', '/usr/bin/google-chrome')
            ->noSandbox()
            ->setEnvironmentOptions([
                'CHROME_CONFIG_HOME' => '/tmp/.config'
            ])
            ->setTemporaryHtmlDirectory('/tmp/browsershot')
            ->format('Legal')
            ->showBackground()
            ->save('/tmp/test.pdf');
        return true;
    }
}
