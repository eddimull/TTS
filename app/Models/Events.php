<?php

namespace App\Models;

use Carbon\Carbon;
use App\Casts\Price;
use App\Formatters\CalendarEventFormatter;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        // Sync roster members when a new event is created with a roster
        static::created(function ($event) {
            if ($event->roster_id) {
                $event->syncRosterMembers();
            }
        });

        // Sync roster members when roster_id changes on existing events
        static::updated(function ($event) {
            if ($event->wasChanged('roster_id') && $event->roster_id) {
                $event->syncRosterMembers();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        // Accept either the event key (mobile app) or numeric id (web app)
        if (is_numeric($value)) {
            return $this->where('id', $value)->first();
        }

        return $this->where('key', $value)->first();
    }

    protected $fillable = [
        'additional_data',
        'date',
        'end_time',
        'event_type_id',
        'eventable_id',
        'eventable_type',
        'key',
        'price',
        'start_time',
        'title',
        'notes',
        'roster_id',
        'value',
        'venue_address',
        'venue_timezone',
        'venue_name',
        'media_folder_path',
        'enable_portal_media_access',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'value' => Price::class,
        'price' => Price::class,
        'enable_portal_media_access' => 'boolean',
    ];

    protected $attributes = [
        'enable_portal_media_access' => true,
    ];

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function type()
    {
        return $this->hasOne(EventTypes::class, 'id', 'event_type_id');
    }

    public function setlist()
    {
        return $this->hasOne(EventSetlist::class, 'event_id');
    }

    public function liveSetlistSession()
    {
        return $this->hasOne(LiveSetlistSession::class, 'event_id');
    }

    public function getOldEventAttribute()
    {
        //return dates that are older than today plus a day
        return Carbon::parse($this->date) < Carbon::now()->subDay();
    }

    public function getISODateAttribute()
    {
        $date = $this->date->toDateString();

        // Falls back to a noon time if start_time is null (legacy events with no time set).
        $time = $this->start_time ? $this->start_time->format('H:i:s') : '12:00:00';

        return Carbon::parse("{$date} {$time}")->isoFormat('YYYY-MM-DD Thh:mm:ss.sss');
    }

    public function getStartDateTimeAttribute()
    {
        $date = $this->date->toDateString();
        $time = $this->start_time ? $this->start_time->format('H:i') : '12:00';

        return Carbon::parse("{$date} {$time}")->isoFormat('YYYY-MM-DDTHH:mm:ss.sss');
    }

    public function getEndDateTimeAttribute()
    {
        $start = Carbon::parse($this->startDateTime);

        // Prefer the dedicated end_time column when populated.
        if ($this->end_time) {
            $date = $this->date->toDateString();
            $time = $this->end_time->format('H:i');
            $end = Carbon::parse("{$date} {$time}");

            // end_time is stored as a TIME (no date). When it falls on or before
            // the start, assume the event crosses midnight (e.g., 22:00 -> 02:00)
            // and roll the end forward by a day. Google Calendar rejects events
            // whose end is not strictly after the start (timeRangeEmpty).
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            return $end->isoFormat('YYYY-MM-DDTHH:mm:ss.sss');
        }

        // Fall back to additional_data->times (legacy data path).
        $endDateTime = $start->copy()->addHour();
        try {
            if ($this->additional_data && isset($this->additional_data->times) && $this->additional_data->times) {
                $endTime = collect($this->additional_data->times)->max('time');
                $parsed = Carbon::parse($endTime);
                if ($parsed->greaterThan($start)) {
                    $endDateTime = $parsed;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error parsing end time for event ID ' . $this->id . ': ' . $e->getMessage());
        }
        return $endDateTime->isoFormat('YYYY-MM-DDTHH:mm:ss.sss');
    }

    /**
     * Venue name for the event. Lives on the events row since the
     * 2026_05_03 migration moved it off bookings; falls back to the
     * eventable for non-booking types (e.g. rehearsals) not backfilled there.
     */
    public function getResolvedVenueNameAttribute(): ?string
    {
        return $this->venue_name ?? $this->eventable?->venue_name ?? null;
    }

    /**
     * Venue address for the event. See getResolvedVenueNameAttribute().
     */
    public function getResolvedVenueAddressAttribute(): ?string
    {
        return $this->venue_address ?? $this->eventable?->venue_address ?? null;
    }

    /**
     * Normalize additional_data to an object on read.
     *
     * The column is JSON, but some rows are double-encoded (a JSON string
     * whose content is itself JSON). Decode until we have an object so every
     * reader (->public, ->times, ...) is safe regardless of how the row was
     * stored.
     */
    protected function additionalData(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null) {
                    return null;
                }

                $decoded = $value;
                for ($i = 0; $i < 4 && is_string($decoded); $i++) {
                    $decoded = json_decode($decoded);
                }

                return is_object($decoded) ? $decoded : null;
            },
            set: function ($value) {
                // Preserve the prior `object` cast's write behavior: serialize
                // arrays / objects to JSON. Strings are passed through as-is
                // (write-side normalization of malformed strings is out of
                // scope for this change).
                if ($value === null || is_string($value)) {
                    return $value;
                }

                return json_encode($value);
            },
        );
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

        // Load relationships if not already loaded
        if (!$this->relationLoaded('eventable')) {
            $this->load('eventable');
        }

        if (!$this->relationLoaded('roster')) {
            $this->load('roster.members');
        }

        // Get the band_id from the eventable (either Booking or BandEvents)
        $bandId = $this->eventable->band_id ?? null;

        if (!$bandId) {
            \Log::warning("Cannot sync roster members for event {$this->id}: band_id not found in eventable");
            return;
        }

        // Permanently delete existing event members (including soft-deleted ones)
        // Use forceDelete to avoid unique constraint violation
        $this->eventMembers()->withTrashed()->forceDelete();

        // Get active roster members
        $rosterMembers = $this->roster->members()->where('is_active', true)->get();

        if ($rosterMembers->isEmpty()) {
            return;
        }

        // Create event members from roster members
        foreach ($rosterMembers as $rosterMember) {
            EventMember::create([
                'event_id' => $this->id,
                'band_id' => $bandId,
                'roster_member_id' => $rosterMember->id,
                'slot_id' => $rosterMember->slot_id,
                'user_id' => $rosterMember->user_id,
                'band_role_id' => $rosterMember->band_role_id,
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

    public function getGoogleCalendarSummary(BandCalendars $bandCalendar = null): string|null
    {
        if ($bandCalendar?->type === 'public') {
            return $this->title;
        }

        if ($this->eventable_type === 'App\\Models\\Bookings' && $this->eventable) {
            return $this->title . ' (' . ucfirst($this->eventable->status) . ')';
        }
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
        if (!empty($this->venue_name)) {
            return $this->venue_name . (!empty($this->venue_address) ? ', ' . $this->venue_address : '');
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
                'start_time',
                'end_time',
                'venue_name',
                'venue_address',
                'price',
                'notes',
                'event_type_id',
                'eventable_type',
                'eventable_id',
                'additional_data',
                'value',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('events')
            ->setDescriptionForEvent(fn(string $eventName) => "Event has been {$eventName}");
    }

}
