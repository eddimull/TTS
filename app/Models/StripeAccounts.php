<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeAccounts extends Model
{
    use HasFactory;

    protected $fillable = ['band_id', 'stripe_account_id', 'status'];
}
