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
     * Invoices the client can still pay: everything not marked paid. This is
     * what the contact portal lists under "View & Pay Invoice", so anything
     * that must agree with the portal (e.g. the balance write-off guard)
     * should use this scope.
     */
    public function scopeOutstanding($query)
    {
        return $query->where('status', '!=', 'paid');
    }
}
