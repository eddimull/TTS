<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoices extends Model
{
    use HasFactory;
    protected $fillable = ['booking_id', 'amount', 'status', 'stripe_id', 'stripe_url', 'convenience_fee'];

    public function booking()
    {
        return $this->belongsTo(Bookings::class);
    }

    /**
     * Invoices not marked paid. Nothing moves an invoice out of 'open' except
     * a Stripe payment, so many of these are stale: use isPayable() before
     * offering one to a client.
     */
    public function scopeOutstanding($query)
    {
        return $query->where('status', '!=', 'paid');
    }

    /**
     * Whether the client should be offered this invoice to pay: it isn't paid
     * and its booking still owes money. A settled booking (paid in full or
     * written off) has nothing payable, whatever its old invoices say.
     */
    public function isPayable(): bool
    {
        return $this->status !== 'paid'
            && $this->booking !== null
            && (float) $this->booking->amount_due > 0;
    }
}
