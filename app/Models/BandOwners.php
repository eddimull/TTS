<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BandOwners extends Model
{
    protected $fillable = ['user_id','band_id'];
    use HasFactory;
}
