<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stripe_customers extends Model
{
    use HasFactory;
    protected $fillable = ['stripe_account_id','proposal_id','status'];
}
