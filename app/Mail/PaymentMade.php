<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\URL;
use App\Models\ProposalPayments;
use Illuminate\Support\Facades\Storage;

class PaymentMade extends Mailable
{
    use Queueable, SerializesModels;

    protected $payment;
    protected $pdf;
    protected $receiptName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(ProposalPayments $payment)
    {
        $this->payment = $payment;
        $this->receiptName = $this->payment->id . ' - Receipt.pdf';
        $this->setupPDF();
    }

    /**
     * Store the file to attach to the PDF
     * 
     */
    private function setupPDF()
    {
        $this->pdf = $this->payment->getPdf();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('email.payment',[
                            'performance'=>$this->payment->proposal->name,
                            'amount'=>$this->payment->formattedPaymentAmount,
                            'balance'=>$this->payment->proposal->AmountLeft
                            ])
                    ->subject('Payment Received')
                    ->attachData($this->pdf->pdf(),$this->receiptName,[
                        'mime'=>'application/pdf'
                    ])
                ;
    }
}
