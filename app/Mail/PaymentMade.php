<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentMade extends Mailable
{
    use Queueable, SerializesModels;

    protected $payment;
    protected $pdf;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
        $this->pdf = Pdf::loadView('pdf.payment',['payment'=>$payment]);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->pdf->render();
        $output = $this->pdf->output();
        $filename = $this->payment->id . ' - Receipt.pdf';
        file_put_contents($filename,$output);
        return $this->view('email.payment')->subject('Payment Received')->attach($filename);
    }
}
