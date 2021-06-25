<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stripe_invoice_prices extends Model
{
    use HasFactory;
    protected $fillable = ['proposal_id','stripe_price_id'];
}
