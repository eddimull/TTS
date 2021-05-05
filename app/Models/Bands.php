<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bands extends Model
{
    use HasFactory;
    protected $fillable = ['name','site_name'];

    public function owners()
    {
        return $this->hasMany(BandOwners::class);
    }

    public function members()
    {
        return $this->hasMany(BandMembers::class);
    }

    public function events()
    {
        return $this->hasMany(BandEvents::class,'band_id');
    }
    public function colorways()
    {
        // belongsToMany(Bands::class,'band_owners','user_id','band_id')
        return $this->hasMany(Colorways::class,'band_id');
    }
}
