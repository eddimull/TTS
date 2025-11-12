<?php

namespace App\Http\Traits;

use App\Mail\PaymentMade;
use App\Models\ProposalPayments;
use App\Services\PdfGeneratorService;
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
        $pdfService = app(PdfGeneratorService::class);

        return $pdfService->fromUrl($signedURL, 'Legal');
    }
}