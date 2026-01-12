<?php

namespace App\Models;

use Carbon\Carbon;
use App\Casts\TimeCast;
use App\Formatters\CalendarEventFormatter;
use Illuminate\Database\Eloquent\Model;
use App\Models\Interfaces\GoogleCalenderable;
use App\Models\Traits\GoogleCalendarWritable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Events extends Model implements GoogleCalenderable
{
    use HasFactory, GoogleCalendarWritable, LogsActivity;

    protected static function booted()
    {
        // Automatically sync roster members when roster_id is set or changed
        static::saved(function ($event) {
            if ($event->wasChanged('roster_id') && $event->roster_id) {
                $event->syncRosterMembers();
            }
        });
    }

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
        'roster_id',
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

        return Carbon::parse("{$date} {$time}")->isoFormat('YYYY-MM-DDTHH:mm:ss.sss');
    }

    public function getEndDateTimeAttribute()
    {

        $endDateTime = Carbon::parse($this->startDateTime)->addHour()->isoFormat('YYYY-MM-DDTHH:mm:ss.sss');
        try{   
            // Check if additional_data exists and has times property
            if ($this->additional_data && isset($this->additional_data->times) && $this->additional_data->times) {
                $endTime = collect($this->additional_data->times)->max('time');
                $endDateTime = Carbon::parse($endTime)->isoFormat('YYYY-MM-DDTHH:mm:ss.sss');
            }
        } catch (\Exception $e) {
            // Handle the exception if needed
            \Log::error('Error parsing end time for event ID ' . $this->id . ': ' . $e->getMessage());
        }
        return $endDateTime;
    }

    public function scopePublic($query)
    {
        return $query->whereRaw("JSON_EXTRACT(additional_data, '$.public') IN (1, true, '1', 'true')");
    }


    public function advanceURL()
    {
        return config('app.url') . '/events/' . $this->key . '/advance';
    }

    public function googleEvents()
    {
        return $this->morphMany(GoogleEvents::class, 'google_eventable');
    }

    public function attachments()
    {
        return $this->hasMany(EventAttachment::class, 'event_id');
    }

    public function roster()
    {
        return $this->belongsTo(Roster::class);
    }

    public function eventMembers()
    {
        return $this->hasMany(EventMember::class, 'event_id');
    }

    public function attendedMembers()
    {
        return $this->eventMembers()->attended();
    }

    public function absentMembers()
    {
        return $this->eventMembers()->absent();
    }

    /**
     * Sync roster members to event members.
     * This copies all active members from the assigned roster to this event.
     */
    public function syncRosterMembers(): void
    {
        if (!$this->roster_id) {
            return;
        }

        // Get the band_id from the eventable (either Booking or BandEvents)
        $bandId = $this->eventable->band_id ?? null;

        if (!$bandId) {
            return;
        }

        // Permanently delete existing event members (including soft-deleted ones)
        // Use forceDelete to avoid unique constraint violation
        $this->eventMembers()->withTrashed()->forceDelete();

        // Get active roster members
        $rosterMembers = $this->roster->members()->where('is_active', true)->get();

        // Create event members from roster members
        foreach ($rosterMembers as $rosterMember) {
            EventMember::create([
                'event_id' => $this->id,
                'band_id' => $bandId,
                'roster_member_id' => $rosterMember->id,
                'user_id' => $rosterMember->user_id,
                'attendance_status' => 'confirmed',
            ]);
        }
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
        return $this->eventable->band->eventCalendar ?? null;
    }

    public function getPublicGoogleCalendar(): BandCalendars|null
    {
        return $this->eventable->band->publicCalendar ?? null;
    }

    public function getGoogleCalendarSummary(): string|null
    {
        return $this->title;
    }

    public function getGoogleCalendarDescription(): string|null
    {
        // If this event belongs to a rehearsal, use the rehearsal formatter
        if ($this->eventable_type === 'App\\Models\\Rehearsal') {
            return CalendarEventFormatter::formatRehearsalDescription($this->eventable);
        }
        
        return CalendarEventFormatter::formatEventDescription($this);
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
        if ($this->eventable) {
            return $this->eventable->venue_name . ($this->eventable->venue_address ? ', ' . $this->eventable->venue_address : '');
        }
        return null;
    }

    public function getGoogleCalendarColor(): string|null
    {
        // Use yellow color for rehearsals
        if ($this->eventable_type === 'App\\Models\\Rehearsal') {
            return '5'; // Yellow for rehearsals
        }
        
        return null;
    }

    /**
     * Configure activity logging options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title',
                'date',
                'time',
                'notes',
                'event_type_id',
                'eventable_type',
                'eventable_id',
                'additional_data',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('events')
            ->setDescriptionForEvent(fn(string $eventName) => "Event has been {$eventName}");
    }

}
