<?php 

namespace App\Http\Traits;

use App\Mail\PaymentMade;
use App\Models\ProposalPayments;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

trait PaymentTrait{
    public function sendReceipt()
    {
        $contacts = $this->proposal->ProposalContacts;
        foreach($contacts as $contact)
        {
            Mail::to($contact->email)->send(new PaymentMade($this));
        }
    }

    public function getPdf()
    {
        $signedURL = URL::temporarySignedRoute('paymentpdf',now()->addMinutes(5),['payment'=>$this]);

        $pdf = \Spatie\Browsershot\Browsershot::url($signedURL)
            ->setNodeBinary(env('NODE_BINARY','/usr/bin/node'))
            ->setNpmBinary(env('NPM_BINARY','/usr/bin/npm'))
            ->format('Legal')
            ->showBackground();

        return $pdf;
    }
}