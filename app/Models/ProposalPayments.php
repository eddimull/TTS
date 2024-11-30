<?php

namespace App\Models;

use App\Http\Traits\PaymentTrait;
use App\Mail\PaymentMade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class ProposalPayments extends Model
{
    use HasFactory;
    use PaymentTrait;

    protected $guarded = [];

    protected $with = ['proposal:id,name,key,band_id,author_id,price'];
    protected $appends = ['formattedPaymentDate', 'formattedPaymentAmount'];
    protected $table = 'proposal_payments';

    public function getformattedPaymentDateAttribute()
    {
        return Carbon::parse($this->paymentDate)->format('Y-m-d');
    }

    public function getformattedPaymentAmountAttribute()
    {
        return number_format($this->amount / 100, 2);
    }

    public function sendReceipt()
    {
        $contacts = $this->proposal->ProposalContacts;
        foreach ($contacts as $contact)
        {
            Mail::to($contact->email)->send(
                new PaymentMade($this)
            );
        }
    }

    public function proposal()
    {
        return $this->belongsTo(Proposals::class);
    }
}
