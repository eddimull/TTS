<?php

namespace App\Models;

use Carbon\Carbon;
use App\Casts\TimeCast;
use Ramsey\Uuid\Type\Time;
use Illuminate\Database\Eloquent\Model;
use App\Models\Interfaces\GoogleCalenderable;
use App\Models\Traits\GoogleCalendarWritable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Events extends Model implements GoogleCalenderable
{
    use HasFactory, GoogleCalendarWritable;

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

    public function type()
    {
        return $this->hasOne(EventTypes::class, 'id', 'event_type_id');
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

    public function getStartDateTimeAttribute()
    {
        $date = $this->date->toDateString();

        // Assuming $this->time is in 'HH:mm:ss' format after TimeCast
        $time = $this->time;

        return Carbon::parse("{$date} {$time}")->isoFormat('YYYY-MM-DDThh:mm:ss.sss');
    }

    public function getEndDateTimeAttribute()
    {
        $endTime = collect($this->additional_data->times)->max('time');
        return Carbon::parse($endTime)->isoFormat('YYYY-MM-DDThh:mm:ss.sss');
    }


    public function advanceURL()
    {
        return config('app.url') . '/events/' . $this->key . '/advance';
    }

    public function googleEvents()
    {
        return $this->morphMany(GoogleEvents::class, 'google_eventable');
    }

    public function getGoogleEvent(BandCalendars $bandCalendar = null): GoogleEvents|null
    {
        if(!$bandCalendar) {
            return $this->googleEvents()->first();
        }
        return $this->googleEvents()->where('band_calendar_id', $bandCalendar->id)->first();
    }

    public function getGoogleCalendar(): BandCalendars|null
    {
        return $this->eventable->band->eventCalendar;
    }

    public function getPublicGoogleCalendar(): BandCalendars|null
    {
        return $this->eventable->band->publicCalendar;
    }

    public function getGoogleCalendarSummary(): string|null
    {
        return $this->title;
    }

    public function getGoogleCalendarDescription(): string|null
    {
        return $this->buildGoogleCalendarDescription();
    }

    public function getGoogleCalendarStartTime(): \Google\Service\Calendar\EventDateTime
    {
        return new \Google\Service\Calendar\EventDateTime(['dateTime' => $this->startDateTime, 'timeZone' => config('app.timezone')]);
    }

    public function getGoogleCalendarEndTime(): \Google\Service\Calendar\EventDateTime
    {
        return new \Google\Service\Calendar\EventDateTime(['dateTime' => $this->endDateTime, 'timeZone' => config('app.timezone')]);
    }

    public function getGoogleCalendarLocation(): string|null
    {
        if ($this->eventable && property_exists($this->eventable, 'venue_address')) {
            return $this->eventable->venue_address;
        }
        return null;
    }

    public function getGoogleCalendarColor(): string|null
    {
        return null;
    }

    public function buildGoogleCalendarDescription(): string
    {
        $elements = [];
        
        // Event Type
        if ($this->type) {
            $elements['Event Type'] = $this->type->name;
        }
        
        // Venue information
        if ($this->eventable) {
            if ($this->eventable->venue_name) {
                $elements['Venue'] = $this->eventable->venue_name;
            }
            if ($this->eventable->venue_address) {
                $elements['Address'] = $this->eventable->venue_address;
            }
        }
        
        // Notes (strip HTML tags)
        if ($this->notes) {
            $elements['Notes'] = strip_tags($this->notes);
        }
        
        // Timeline - format the times array
        if (isset($this->additional_data->times) && is_array($this->additional_data->times)) {
            $timeline = collect($this->additional_data->times)
                ->sortBy('time')
                ->map(function ($timeEntry) {
                    $time = Carbon::parse($timeEntry->time)->format('g:i A');
                    return "  {$time} - {$timeEntry->title}";
                })
                ->implode("\n");
            
            if ($timeline) {
                $elements['Timeline'] = "\n" . $timeline;
            }
        }
        
        // Attire (strip HTML tags)
        if (isset($this->additional_data->attire) && !empty($this->additional_data->attire)) {
            $elements['Attire'] = strip_tags($this->additional_data->attire);
        }
        
        // Additional conditions
        if (isset($this->additional_data->outside) && $this->additional_data->outside) {
            $elements['Conditions'] = 'Outside event';
        }
        
        if (isset($this->additional_data->backline_provided) && $this->additional_data->backline_provided) {
            $elements['Backline'] = 'Provided';
        }
        
        if (isset($this->additional_data->production_needed) && $this->additional_data->production_needed) {
            $elements['Production'] = 'Required';
        }
        
        // Advance URL
        $elements['Advance URL'] = $this->advanceURL();
        
        return collect($elements)
            ->filter() // Remove empty values
            ->map(fn($value, $key) => "{$key}: {$value}")
            ->implode("\n\n");
    }

}
