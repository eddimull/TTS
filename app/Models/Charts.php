<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Charts extends Model
{
    protected $guarded = [];
    use HasFactory, Searchable;
    use \App\Models\Traits\BroadcastsBandChanges;

    protected $with = ['uploads'];

    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    public function uploads()
    {
        return $this->hasMany(ChartUploads::class,'chart_id');
    }

    public function song()
    {
        return $this->belongsTo(Song::class, 'song_id');
    }
}
