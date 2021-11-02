<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charts extends Model
{
    protected $guarded = [];
    use HasFactory;

    protected $with = ['uploads'];

    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    public function uploads()
    {
        return $this->hasMany(ChartUploads::class,'chart_id');
    }
}
