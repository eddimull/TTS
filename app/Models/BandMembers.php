<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BandMembers extends Model
{
    protected $fillable = ['user_id','band_id'];
    use HasFactory;
    
    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
}
