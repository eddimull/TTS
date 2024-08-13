<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
        'father_daughter',
        'mother_groom',
        'bouquet_garter',
        'money_dance',
        'production_needed',
        'backline_provided',
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
        'colorway_text',
        'quiet_time',
        'end_time',
        'ceremony_time',
        'onsite',
        'google_calendar_event_id'
    ];


    protected $with = ['band', 'event_contacts', 'event_type', 'state'];

    public function getOldEventAttribute()
    {
        //return dates that are older than today plus a day
        return Carbon::parse($this->event_time) < Carbon::now()->subDay();
    }

    public function getISODateAttribute()
    {
        return Carbon::parse($this->event_time)->isoFormat('YYYY-MM-DD Thh:mm:ss.sss');
    }

    public function event_type()
    {
        return $this->hasOne(EventTypes::class, 'id', 'event_type_id');
    }

    public function band()
    {
        return $this->hasOne(Bands::class, 'id', 'band_id');
    }

    public function advanceURL()
    {
        return config('app.url') . '/events/' . $this->event_key . '/advance';
    }

    public function colorway()
    {
        return $this->hasOne(Colorways::class, 'id', 'colorway_id');
    }

    public function state()
    {
        return $this->hasOne(State::class, 'state_id', 'state_id');
    }

    public function event_contacts()
    {
        return $this->hasMany(EventContacts::class, 'event_id');
    }

    public function getKeyAttribute()
    {
        return $this->attributes['event_key']->toString();
    }
}
