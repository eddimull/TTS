<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoices extends Model
{
    use HasFactory;
    protected $fillable = ['booking_id', 'amount', 'status', 'stripe_id', 'convenience_fee'];

    public function booking()
    {
        return $this->belongsTo(Bookings::class);
    }
}
