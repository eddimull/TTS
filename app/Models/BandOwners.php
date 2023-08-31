<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class BandOwners extends Model
{
    protected $fillable = ['user_id','band_id'];
    use HasFactory, Notifiable;


    public function bands()
    {
        return $this->belongsTo(Bands::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
}
