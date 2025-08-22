<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BandCalendars extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'calendar_id',
        'type',
    ];

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

}
