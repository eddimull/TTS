<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BandEvents extends Model
{
    use HasFactory;

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
        'finish_time',
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
        'state_id'
    ];
}


