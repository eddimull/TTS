<?php

namespace App\Notifications;

use App\Models\Payments;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\View;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    protected Payments $payment;
    protected $pdf;
    protected $receiptName;
    /**
     * Create a new notification instance.
     */
    public function __construct(Payments $payment)
    {
        $this->payment = $payment;
        $this->receiptName = $this->payment->id . ' - Receipt.pdf';
    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function setupPDF()
    {
        $this->pdf = $this->payment->payable->getPaymentPdf();
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->setupPDF();

        return (new MailMessage)
            ->subject('Payment Received')
            ->view('email.payment', [
                'performance' => $this->payment->payable->name,
                'amount' => $this->payment->amount,
                'balance' => $this->payment->payable->amountLeft
            ])
            ->attachData(
                $this->pdf,
                $this->receiptName,
                ['mime' => 'application/pdf']
            );
    }
}
