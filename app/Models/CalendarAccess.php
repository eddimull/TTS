<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'band_calendar_id',
        'role',
    ];

    protected $table = 'calendar_access';

    public function band()
    {
        return $this->hasOneThrough(Bands::class, BandCalendars::class, 'id', 'id', 'band_calendar_id', 'band_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
