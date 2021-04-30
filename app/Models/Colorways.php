<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Colorways extends Model
{
    use HasFactory;

    protected $table = "colorways";
    protected $fillable = ['band_id','color_title','color_tags','colorway_description'];
}
