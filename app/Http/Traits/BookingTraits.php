<?php

namespace App\Http\Traits;

use App\Mail\PaymentMade;
use Illuminate\Support\Facades\URL;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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

    public function getContractPdf(): string
    {
        $renderedView = view('pdf.bookingContract', ['booking' => $this])->render();
        $tempPath = storage_path('app/temp_pdf_' . uniqid() . '.pdf');
        Browsershot::html($renderedView)
            ->setNodeBinary(config('browsershot.node_binary'))
            ->setNpmBinary(config('browsershot.npm_binary'))
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->setOption('executablePath', config('browsershot.executablePath'))
            ->showBackground()
            ->taggedPdf()
            ->savePdf($tempPath);

        return file_get_contents($tempPath);
    }

    public function storeContractPdf(string $contractPdf)
    {
        $contractPath = $this->band->site_name . '/contracts/' . $this->name . '_contract_' . time() . '.pdf';

        Storage::disk('s3')->put(
            $contractPath,
            $contractPdf,
            ['visibility' => 'public']
        );

        $this->contract->asset_url = Storage::disk('s3')->url($contractPath);
        $this->contract->save();
    }
}