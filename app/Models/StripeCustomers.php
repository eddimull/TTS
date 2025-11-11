<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeCustomers extends Model
{
    use HasFactory;
    protected $fillable = ['stripe_customer_id', 'stripe_account_id', 'status', 'contact_id'];
}
