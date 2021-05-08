<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BandEvents extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'band_events';
    protected $fillable = [
        'band_id',
        'event_name',
        'venue_name',
        'first_dance',
        'second_dance',
        'money_dance',
        'bouquet_dance',
        'address_street',
        'zip',
        'notes',
        'event_time',
        'band_loadin_time',
        'end_time',
        'rhythm_loadin_time',
        'production_loadin_time',
        'pay',
        'depositReceived',
        'event_key',
        'created_at',
        'updated_at',
        'public',
        'event_type_id',
        'lodging',
        'state_id',
        'city',
        'colorway_id',
        'quiet_time',
        'end_time',
        'ceremony_time',
        'onsite',
        'google_calendar_event_id'

    ];

    public function event_type()
    {
        return $this->hasOne(EventTypes::class,'id','event_type_id');
    }

    public function band()
    {
        return $this->hasOne(Bands::class,'id','band_id');
    }

    public function colorway()
    {
        return $this->hasOne(Colorways::class,'id','colorway_id');
    }

    public function state()
    {
        return $this->hasOne(State::class,'state_id','state_id');
    }
}


