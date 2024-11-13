<?php

namespace App\Events;

use App\Models\Payments;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class PaymentWasReceived
{
    use Dispatchable, SerializesModels;

    public $payment;

    public function __construct(Payments $payment)
    {
        $this->payment = $payment;
    }
}
