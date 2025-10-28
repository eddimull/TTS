<?php

namespace App\Models;

use App\Formatters\CalendarEventFormatter;
use App\Models\Interfaces\GoogleCalenderable;
use App\Models\Traits\GoogleCalendarWritable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Rehearsal extends Model implements GoogleCalenderable
{
    use HasFactory, SoftDeletes, GoogleCalendarWritable, LogsActivity;

    protected $fillable = [
        'rehearsal_schedule_id',
        'band_id',
        'venue_name',
        'venue_address',
        'notes',
        'additional_data',
        'is_cancelled',
    ];

    protected $casts = [
        'additional_data' => 'object',
        'is_cancelled' => 'boolean',
    ];

    /**
     * Get the band that owns this rehearsal (direct relationship)
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class);
    }

    /**
     * Get the rehearsal schedule that owns this rehearsal
     */
    public function rehearsalSchedule(): BelongsTo
    {
        return $this->belongsTo(RehearsalSchedule::class);
    }

    /**
     * Get all events for this rehearsal (polymorphic relationship)
     */
    public function events(): MorphMany
    {
        return $this->morphMany(Events::class, 'eventable');
    }

    /**
     * Get associated bookings/events via pivot table
     */
    public function associations()
    {
        return $this->hasMany(RehearsalAssociation::class);
    }

    /**
     * Get associated bookings
     */
    public function bookings()
    {
        return $this->morphToMany(
            Bookings::class,
            'associable',
            'rehearsal_associations',
            'rehearsal_id',
            'associable_id'
        )->wherePivot('associable_type', Bookings::class);
    }

    /**
     * Get the Google Events for this rehearsal
     */
    public function googleEvents()
    {
        return $this->morphMany(GoogleEvents::class, 'google_eventable');
    }

    /**
     * Get a specific Google Event for a band calendar
     */
    public function getGoogleEvent(BandCalendars $bandCalendar = null): GoogleEvents|null
    {
        if (!$bandCalendar) {
            return $this->googleEvents()->first();
        }
        return $this->googleEvents()->where('band_calendar_id', $bandCalendar->id)->first();
    }

    /**
     * Get the Google Calendar for this rehearsal (event calendar)
     */
    public function getGoogleCalendar(): BandCalendars|null
    {
        return $this->band->eventCalendar;
    }

    public function getGoogleCalendarSummary(): string|null
    {
        // Get the first event or use schedule name
        $event = $this->events()->first();
        return $event ? $event->title : ($this->rehearsalSchedule->name ?? 'Rehearsal');
    }

    public function getGoogleCalendarDescription(): string|null
    {
        return CalendarEventFormatter::formatRehearsalDescription($this);
    }

    public function getGoogleCalendarStartTime(): \Google\Service\Calendar\EventDateTime
    {
        $event = $this->events()->first();
        if ($event) {
            return new \Google\Service\Calendar\EventDateTime([
                'dateTime' => $event->startDateTime,
                'timeZone' => config('app.timezone')
            ]);
        }
        // Fallback to a default time if no event exists yet
        return new \Google\Service\Calendar\EventDateTime([
            'dateTime' => now()->toIso8601String(),
            'timeZone' => config('app.timezone')
        ]);
    }

    public function getGoogleCalendarEndTime(): \Google\Service\Calendar\EventDateTime
    {
        $event = $this->events()->first();
        if ($event) {
            return new \Google\Service\Calendar\EventDateTime([
                'dateTime' => $event->endDateTime,
                'timeZone' => config('app.timezone')
            ]);
        }
        // Fallback to a default time if no event exists yet
        return new \Google\Service\Calendar\EventDateTime([
            'dateTime' => now()->addHours(2)->toIso8601String(),
            'timeZone' => config('app.timezone')
        ]);
    }

    public function getGoogleCalendarLocation(): string|null
    {
        // Use rehearsal-specific venue, or fall back to schedule location
        if ($this->venue_name) {
            return $this->venue_name . ($this->venue_address ? ', ' . $this->venue_address : '');
        }
        
        if ($this->rehearsalSchedule->location_name) {
            return $this->rehearsalSchedule->location_name . 
                   ($this->rehearsalSchedule->location_address ? ', ' . $this->rehearsalSchedule->location_address : '');
        }
        
        return null;
    }

    public function getGoogleCalendarColor(): string|null
    {
        // You can customize the color for rehearsal events
        // Google Calendar color IDs: 1-11
        return '5'; // Yellow for rehearsals
    }

    /**
     * Configure activity logging options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'rehearsal_schedule_id',
                'band_id',
                'venue_name',
                'venue_address',
                'notes',
                'additional_data',
                'is_cancelled',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('rehearsals')
            ->setDescriptionForEvent(fn(string $eventName) => "Rehearsal has been {$eventName}");
    }
}
