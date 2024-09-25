<?php

namespace App\Models;

use App\Casts\TimeCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Ramsey\Uuid\Type\Time;

class Events extends Model
{
    use HasFactory;

    protected $fillable = [
        'additional_data',
        'date',
        'event_type_id',
        'eventable_id',
        'eventable_type',
        'key',
        'title',
        'notes',
        'time',
    ];

    protected $casts = [
        'additional_data' => 'object',
        'date' => 'date:Y-m-d',
        'time' => TimeCast::class,
    ];

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getOldEventAttribute()
    {
        //return dates that are older than today plus a day
        return Carbon::parse($this->date) < Carbon::now()->subDay();
    }

    public function getISODateAttribute()
    {

        $date = $this->date->toDateString();

        // Assuming $this->time is in 'HH:mm:ss' format after TimeCast
        $time = $this->time->toTimeString();

        return Carbon::parse("{$date} {$time}")->isoFormat('YYYY-MM-DD Thh:mm:ss.sss');
    }

    public function advanceURL()
    {
        return config('app.url') . '/events/' . $this->key . '/advance';
    }
}
