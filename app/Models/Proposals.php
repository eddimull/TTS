<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Proposals extends Model
{
    use HasFactory;

    protected $table = 'proposals';
    protected $with = ['band', 'proposal_contacts', 'phase', 'author', 'event_type', 'recurring_dates', 'contract'];
    protected $guarded = [];

    public function getISODateAttribute()
    {
        return Carbon::parse($this->date)->isoFormat('YYYY-MM-DD Thh:mm:ss.sss');
    }

    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    public function contract()
    {
        return $this->hasOne(ProposalContracts::class, 'proposal_id');
    }

    public function proposalContacts() //did this to satisfy laravel's 'magic' factory methods
    {
        return $this->proposal_contacts();
    }

    public function proposal_contacts()
    {
        return $this->hasMany(ProposalContacts::class, 'proposal_id');
    }

    public function recurring_dates()
    {
        return $this->hasMany(recurring_proposal_dates::class, 'proposal_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoices::class, 'proposal_id');
    }

    public function phase()
    {
        return $this->belongsTo(ProposalPhases::class, 'phase_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function event_type()
    {
        return $this->belongsTo(EventTypes::class);
    }

    public function stripe_customers()
    {
        return $this->hasMany(stripe_customers::class, 'proposal_id');
    }

    public function payments()
    {
        return $this->hasMany(ProposalPayments::class, 'proposal_id');
    }

    public function lastPayment()
    {
        return $this->hasOne(ProposalPayments::class, 'proposal_id')->latestOfMany();
    }

    public function getformattedDraftDateAttribute()
    {
        return Carbon::parse($this->created_at)->format('Y-m-d');
    }

    public function getformattedPerformanceDateAttribute()
    {
        return Carbon::parse($this->date)->format('Y-m-d');
    }

    public function getformattedPriceAttribute()
    {
        return number_format(floatval($this->price), 2);
    }

    public function getAmountPaidAttribute()
    {

        $paid = 0;
        foreach ($this->payments as $payment)
        {
            $paid += $payment->amount;
        }

        return number_format($paid / 100, 2);
    }


    public function getAmountLeftAttribute()
    {
        return number_format(floatval($this->price) - floatval(str_replace(",", "", $this->amountPaid)), 2);
    }

    public function attachPayments()
    {
        $this->amountLeft = $this->amountLeft;
        $this->amountPaid = $this->amountPaid;

        foreach ($this->payments as $payment)
        {
            $payment->formattedPaymentDate = $payment->formattedPaymentDate;
        }
    }
}
