<?php

namespace App\Http\Traits;

use App\Models\Contacts;
use App\Mail\PaymentMade;
use Illuminate\Support\Facades\URL;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\BookingContact;
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
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--headless'])
            ->setOption('executablePath', config('browsershot.executablePath'))
            ->format('Legal')
            ->showBackground()
            ->savePdf($tempPath);

        return file_get_contents($tempPath);
    }

    public function getContractPdf(Contacts $contact = null): string
    {
        $logoPath = str_replace('/images/', '', $this->band->logo);

        if (is_null($contact))
        {
            $contact = $this->contacts->first();
        }

        // Use Storage facade instead of direct file_get_contents
        $imageContents = Storage::disk('s3')->get($logoPath);
        $base64Image = base64_encode($imageContents);
        $mimeType = Storage::disk('s3')->mimeType($logoPath);
        $dataUri = "data:{$mimeType};base64,{$base64Image}";

        $renderedView = view('pdf.bookingContract', [
            'booking' => $this,
            'logoDataUri' => $dataUri,
            'signer' => $contact
        ])->render();

        $tempPath = storage_path('app/temp_pdf_' . uniqid() . '.pdf');

        Browsershot::html($renderedView)
            ->setNodeBinary(config('browsershot.node_binary'))
            ->setNpmBinary(config('browsershot.npm_binary'))
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--headless'])
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
