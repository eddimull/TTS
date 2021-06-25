<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stripe_products extends Model
{
    use HasFactory;
    protected $fillable = ['proposal_id','product_name','stripe_product_id'];
}
