<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeProducts extends Model
{
    use HasFactory;
    protected $fillable = ['band_id','product_name','stripe_product_id'];
}
