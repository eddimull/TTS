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

    public function getPaymentPdf()
    {
        $renderedView = view('pdf.bookingPayment', ['booking' => $this])->render();
        $tempPath = storage_path('app/temp_pdf_' . uniqid() . '.pdf');
        Browsershot::html($renderedView)
            ->setNodeBinary(config('browsershot.node_binary'))
            ->setNpmBinary(config('browsershot.npm_binary'))
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->setOption('executablePath', config('browsershot.executablePath'))
            ->format('Legal')
            ->showBackground()
            ->savePdf($tempPath);

        return file_get_contents($tempPath);
    }
}
