<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColorwayPhotos extends Model
{
    use HasFactory;

    protected $table = 'colorway_photos';

    protected $fillable = ['colorway_id','photo_name'];

    public function color()
    {
        return $this->belongsTo(Colorways::class);
    }
}
