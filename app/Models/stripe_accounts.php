<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stripe_accounts extends Model
{
    use HasFactory;
    protected $fillable = ['band_id','stripe_account_id','status'];
}
