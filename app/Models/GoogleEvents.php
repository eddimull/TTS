<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleEvents extends Model
{
    use HasFactory;

    protected $fillable = [
        'google_event_id',
        'google_eventable_id',
        'google_eventable_type',
        'band_calendar_id',
    ];

    public function googleEventable()
    {
        return $this->morphTo();
    }

    public function bandCalendar()
    {
        return $this->hasOne(BandCalendars::class, 'id', 'band_calendar_id');
    }
}
