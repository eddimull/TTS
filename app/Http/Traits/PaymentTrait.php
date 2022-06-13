<?php 

namespace App\Http\Traits;

use App\Mail\PaymentMade;
use App\Models\ProposalPayments;
use Illuminate\Support\Facades\Mail;

trait PaymentTrait{
    public function sendReceipt()
    {
        $contacts = $this->proposal->ProposalContacts;
        foreach($contacts as $contact)
        {
            Mail::to($contact->email)->send(new PaymentMade($this));
        }
    }
}