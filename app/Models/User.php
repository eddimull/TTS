<?php

namespace App\Models;

use App\Models\Bandnotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\BandResource;
use App\Models\Charts;
use App\Models\userPermissions;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'Zip',
        'City',
        'StateID',
        'CountryID',
        'Address1',
        'Address2',
        'Address3',
        'emailNotifications'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'calendar_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function bandOwner()
    {
        return $this->belongsToMany(Bands::class, 'band_owners', 'user_id', 'band_id')
            ->withTimestamps();
    }

    public function bandMember()
    {
        return $this->belongsToMany(Bands::class, 'band_members', 'user_id', 'band_id')
            ->withTimestamps();
    }

    public function bandSub()
    {
        return $this->belongsToMany(Bands::class, 'band_subs', 'user_id', 'band_id')
            ->withTimestamps();
    }

    public function isSubOfBand($bandId): bool
    {
        return $this->bandSub->contains('id', $bandId);
    }

    /**
     * Chart IDs a sub is entitled to see for a given band: the charts referenced
     * in the additional_data of events the user is assigned to (accepted
     * event_subs, or event_members rows filling a sub slot). A sub does NOT get
     * the band's whole chart library — only the charts for gigs they play.
     *
     * @return array<int>
     */
    public function assignedChartIdsForBand($bandId): array
    {
        // Events in this band the user is assigned to as a sub.
        $assignedEventIds = array_values(array_unique(array_merge(
            \DB::table('event_subs')
                ->where('user_id', $this->id)
                ->where('pending', false)
                ->pluck('event_id')
                ->toArray(),
            \DB::table('event_members')
                ->where('user_id', $this->id)
                ->whereNull('roster_member_id')
                ->whereNull('deleted_at')
                ->pluck('event_id')
                ->toArray(),
        )));

        if (empty($assignedEventIds)) {
            return [];
        }

        // Restrict to events that belong to this band, then pull chart ids out of
        // their additional_data->performance->charts[].id references.
        $events = \App\Models\Events::whereIn('id', $assignedEventIds)
            ->whereHasMorph('eventable', [\App\Models\Bookings::class, \App\Models\Rehearsal::class], function ($q) use ($bandId) {
                $q->where('band_id', $bandId);
            })
            ->pluck('additional_data');

        $chartIds = [];
        foreach ($events as $additionalData) {
            $charts = $additionalData->performance->charts ?? null;
            if (is_array($charts)) {
                foreach ($charts as $chart) {
                    $id = is_object($chart) ? ($chart->id ?? null) : ($chart['id'] ?? null);
                    if ($id !== null) {
                        $chartIds[] = (int) $id;
                    }
                }
            }
        }

        return array_values(array_unique($chartIds));
    }

    /**
     * Return this user's calendar feed token, minting one on first access.
     *
     * The token authenticates the unauthenticated ICS subscription URL
     * (/calendar/{token}.ics), so it is long and random rather than guessable.
     * Generated lazily so existing users get one only when they first open the
     * "subscribe" screen.
     */
    public function getCalendarToken(): string
    {
        if (empty($this->calendar_token)) {
            $this->forceFill(['calendar_token' => \Illuminate\Support\Str::random(48)])->save();
        }

        return $this->calendar_token;
    }

    /**
     * Issue a fresh calendar token, invalidating any previously shared feed
     * URL. Used by the "reset link" action.
     */
    public function regenerateCalendarToken(): string
    {
        $this->forceFill(['calendar_token' => \Illuminate\Support\Str::random(48)])->save();

        return $this->calendar_token;
    }

    public function permissionsForBand($id)
    {
        return userPermissions::firstOrCreate(['user_id' => $this->id, 'band_id' => $id]);
    }


    public function canWriteCharts($bandId)
    {
        // dd($bandId);
        if ($this->ownsBand($bandId))
        {
            return true;
        }
        else
        {
            $permissions = $this->permissionsForBand($bandId);


            return (bool)$permissions->write_charts;
        }
    }

    /**
     * Check if user can read a specific resource type
     */
    public function canRead($resource, $bandId)
    {
        if ($this->ownsBand($bandId)) {
            return true;
        }

        // Subs may READ events and charts for bands they sub for, but the
        // controller must scope the results to the gigs they're assigned to
        // (their assigned events / the charts on those events) — a sub does not
        // get the band's full library. Any other resource (bookings, finances,
        // media, rehearsals) is NOT readable by a sub without an explicit grant.
        if (in_array($resource, ['events', 'charts'], true) && $this->isSubOfBand($bandId)) {
            return true;
        }

        setPermissionsTeamId($bandId);
        $result = $this->hasPermissionTo('read:' . $resource);
        setPermissionsTeamId(0);

        return $result;
    }

    /**
     * Check if user can write a specific resource type
     */
    public function canWrite($resource, $bandId)
    {
        if ($this->ownsBand($bandId)) {
            return true;
        }

        setPermissionsTeamId($bandId);
        $result = $this->hasPermissionTo('write:' . $resource);
        setPermissionsTeamId(0);

        return $result;
    }

    public function charts()
    {
        // return $this->hasManyThrough(Charts::class,Bands::class,'band_members','user_id','band_id');
        $bandIds = [];

        $bandIds = $this->allBands()->pluck('id')->toArray();

        $charts = Charts::whereIn('band_id', $bandIds)->orderBy('title', 'asc')->get();

        return $charts;
    }

    public function questionnaires()
    {
        $bandIds = $this->allBands()->pluck('id')->toArray();
        return \App\Models\Questionnaires::whereIn('band_id', $bandIds)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
    }

    public function getNav()
    {
        $nav = collect(BandResource::cases())
            ->mapWithKeys(fn($r) => [$r->label() => ['read' => false, 'write' => false]])
            ->all();

        foreach ($this->allBands() as $band) {
            setPermissionsTeamId($band->id);

            if ($this->hasRole('band-owner')) {
                setPermissionsTeamId(0);
                return collect(BandResource::cases())
                    ->mapWithKeys(fn($r) => [$r->label() => ['read' => true, 'write' => true]])
                    ->all();
            }

            foreach (BandResource::cases() as $resource) {
                if (!$nav[$resource->label()]['read']) {
                    $nav[$resource->label()]['read'] = $this->hasPermissionTo($resource->readPermission());
                }
                if (!$nav[$resource->label()]['write']) {
                    $nav[$resource->label()]['write'] = $this->hasPermissionTo($resource->writePermission());
                }
            }
        }

        setPermissionsTeamId(0);

        return $nav;
    }

    public function notifications()
    {
        return $this->morphMany(Bandnotification::class, 'notifiable')
            ->limit(50)
            ->orderBy('created_at', 'desc');
    }

    public function isPartOfBand($id)
    {
        $bandsPartOf = $this->bandMember;
        $partOf = false;
        foreach ($bandsPartOf as $band)
        {
            if ($id == $band->id)
            {
                $partOf = true;
            }
        }

        return $partOf;
    }

    public function assignBandMemberDefaults(int $bandId): void
    {
        setPermissionsTeamId($bandId);
        $this->assignRole('band-member');
        $this->givePermissionTo(['read:events', 'read:charts', 'read:rehearsals', 'read:media']);
        setPermissionsTeamId(null);
    }

    public function isOwner($id)
    {
        return $this->ownsBand($id);
    }

    public function ownsBand($id)
    {
        $bandsOwned = $this->bandOwner;
        $owns = false;
        foreach ($bandsOwned as $band)
        {
            if ($id == $band->id)
            {
                $owns = true;
            }
        }

        return $owns;
    }

    public function bands()
    {
        $ownerOf = $this->bandOwner;
        $memberOf = $this->bandMember;
        return $ownerOf->merge($memberOf);
    }

    public function allBands()
    {
        $ownerOf = $this->bandOwner;
        $memberOf = $this->bandMember;
        $subOf = $this->bandSub;
        return $ownerOf->merge($memberOf)->merge($subOf)->unique('id');
    }


    public function getEventsAttribute($afterDate = null, $includeNotes = false, $beforeDate = null, $limit = null)
    {
        $subBandIds = $this->bandSub->pluck('id')->toArray();
        $bandIds = $this->bands()->pluck('id')->toArray();

        // The user's individually-assigned sub events, across ANY band: accepted
        // event_subs invitations plus event_members rows where they fill a sub
        // slot (roster_member_id NULL). These must be shown IN ADDITION to the
        // events of bands they own/are a member of — being a member of one band
        // must not hide sub assignments in another. Mirrors getSubEvents().
        $subAssignedEventIds = array_values(array_unique(array_merge(
            \DB::table('event_subs')
                ->where('user_id', $this->id)
                ->where('pending', false)
                ->pluck('event_id')
                ->toArray(),
            \DB::table('event_members')
                ->where('user_id', $this->id)
                ->whereNull('roster_member_id')
                ->whereNull('deleted_at')
                ->pluck('event_id')
                ->toArray(),
        )));

        // Pure-sub users (no owned/member bands) are routed to getSubEvents()
        // by UserEventsService before reaching here, but keep the standalone
        // behaviour correct: restrict the query to their assigned events only.
        if (!empty($subBandIds) && empty($bandIds)) {
            if (empty($subAssignedEventIds)) {
                return collect();
            }

            $assignedEventIds = $subAssignedEventIds;
            $bandIds = $subBandIds;
        } else {
            $assignedEventIds = null;
        }

        if (empty($bandIds) && empty($subAssignedEventIds)) {
            return collect();
        }
        
        // Member/owner bands contribute all their events; cross-band sub
        // assignments contribute their specific events. For a member+sub user
        // ($assignedEventIds === null but $subAssignedEventIds non-empty) these
        // are OR'd together so neither hides the other.
        $bookingBandFilter = function ($q) use ($bandIds, $assignedEventIds, $subAssignedEventIds) {
            $q->whereIn('bookings.band_id', $bandIds);
            if ($assignedEventIds === null && !empty($subAssignedEventIds)) {
                $q->orWhereIn('events.id', $subAssignedEventIds);
            }
        };

        // Build the main booking events query
        $bookingQuery = Events::join('bookings', 'events.eventable_id', '=', 'bookings.id')
            ->where($bookingBandFilter)
            ->where('events.eventable_type', 'App\\Models\\Bookings')
            ->select([
                'events.date',
                'events.event_type_id',
                'event_types.name as event_type_name',
                'events.id',
                'events.title',
                'events.date',
                'events.start_time',
                'events.key',
                'events.additional_data',
                'events.eventable_id',
                'events.eventable_type',
                'events.roster_id',
                'bookings.band_id',
                'bookings.name as booking_name',
                'bookings.id as booking_id',
                'events.venue_name',
                'events.venue_address',
                \DB::raw("'booking' as event_source"),
                \DB::raw('NULL as is_cancelled'),
                \DB::raw('FALSE as is_virtual')
            ])
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id');

        if ($assignedEventIds !== null) {
            $bookingQuery->whereIn('events.id', $assignedEventIds);
        }

        if ($includeNotes) {
            $bookingQuery->addSelect('events.notes');
        }

        // Apply date filter if provided
        if ($afterDate) {
            $bookingQuery->where('events.date', '>=', $afterDate->toDateString());
        }

        if ($beforeDate) {
            $bookingQuery->where('events.date', '<', $beforeDate->toDateString());
        }

        // Build the rehearsal events query
        $rehearsalQuery = Events::join('rehearsals', 'events.eventable_id', '=', 'rehearsals.id')
            ->join('rehearsal_schedules', 'rehearsals.rehearsal_schedule_id', '=', 'rehearsal_schedules.id')
            ->whereIn('rehearsal_schedules.band_id', $bandIds)
            ->where('events.eventable_type', 'App\\Models\\Rehearsal')
            ->whereNull('rehearsals.deleted_at')
            ->select([
                'events.event_type_id',
                'event_types.name as event_type_name',
                'events.id',
                'events.title',
                'events.date',
                'events.start_time',
                'events.key',
                'events.additional_data',
                'events.eventable_id',
                'events.eventable_type',
                'rehearsal_schedules.band_id',
                \DB::raw("CONCAT('Rehearsal: ', rehearsal_schedules.name) as booking_name"),
                \DB::raw('NULL as booking_id'),
                \DB::raw('COALESCE(rehearsals.venue_name, rehearsal_schedules.location_name) as venue_name'),
                \DB::raw('COALESCE(rehearsals.venue_address, rehearsal_schedules.location_address) as venue_address'),
                \DB::raw("'rehearsal' as event_source"),
                'rehearsals.is_cancelled',
                \DB::raw('FALSE as is_virtual'),
                'rehearsals.rehearsal_schedule_id',
                'rehearsal_schedules.name as rehearsal_schedule_name'
            ])
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id');

        if ($includeNotes) {
            $rehearsalQuery->addSelect('events.notes');
        }
        
        // Apply date filter if provided
        if ($afterDate) {
            $rehearsalQuery->where('events.date', '>=', $afterDate->toDateString());
        }
        
        if ($beforeDate) {
            $rehearsalQuery->where('events.date', '<', $beforeDate->toDateString());
        }
        
        // Get both result sets and merge them
        $bookingEvents = $bookingQuery->get();
        $rehearsalEvents = $assignedEventIds !== null ? collect() : $rehearsalQuery->get();
        
        // Merge and sort by date
        $events = $bookingEvents->merge($rehearsalEvents)->sortBy('date');
        
        if ($events->isEmpty()) {
            return collect();
        }
        
        // Get all booking IDs from the events (excluding rehearsals which have null booking_id)
        $bookingIds = $events->whereNotNull('booking_id')->pluck('booking_id')->unique()->toArray();
        
        // Load all contacts for these bookings in one query
        $contacts = collect();
        if (!empty($bookingIds)) {
            $contacts = \DB::table('booking_contacts')
                ->join('contacts', 'booking_contacts.contact_id', '=', 'contacts.id')
                ->whereIn('booking_id', $bookingIds)
                ->select('booking_contacts.booking_id', 'contacts.id', 'contacts.name', 'contacts.phone', 'contacts.email')
                ->get()
                ->groupBy('booking_id');
        }
        
        // Get rehearsal IDs and load their associations with booking events
        $rehearsalEventIds = $events->where('eventable_type', 'App\\Models\\Rehearsal')
            ->whereNotNull('eventable_id')
            ->pluck('eventable_id')
            ->unique()
            ->toArray();
        
        $rehearsalData = collect();
        if (!empty($rehearsalEventIds)) {
            // Load rehearsals with their event associations (not booking associations)
            $rehearsals = \App\Models\Rehearsal::with(['associations' => function($query) {
                $query->where('associable_type', 'App\\Models\\Events');
            }, 'associations.associable'])
                ->whereIn('id', $rehearsalEventIds)
                ->get()
                ->keyBy('id');
            
            // Process additional_data to ensure charts have their uploads loaded
            foreach ($rehearsals as $rehearsal) {
                if (isset($rehearsal->additional_data->charts) && is_array($rehearsal->additional_data->charts)) {
                    $chartIds = collect($rehearsal->additional_data->charts)->pluck('id')->filter()->toArray();
                    if (!empty($chartIds)) {
                        $chartsWithUploads = \App\Models\Charts::with('uploads')
                            ->whereIn('id', $chartIds)
                            ->get()
                            ->keyBy('id');
                        
                        // Replace chart data with full chart objects including uploads
                        $rehearsal->additional_data = (object) array_merge(
                            (array) $rehearsal->additional_data,
                            [
                                'charts' => collect($rehearsal->additional_data->charts)
                                    ->map(function ($chart) use ($chartsWithUploads) {
                                        $fullChart = $chartsWithUploads->get($chart->id ?? $chart['id'] ?? null);
                                        return $fullChart ? $fullChart : $chart;
                                    })
                                    ->toArray()
                            ]
                        );
                    }
                }
            }
            
            $rehearsalData = $rehearsals;
        }
        
        // Attach contacts to events
        return $events->map(function ($event) use ($contacts, $rehearsalData) {
            if ($event->booking_id) {
                $event->contacts = $contacts->get($event->booking_id, collect());
            } else {
                $event->contacts = collect();
            }
            
            // Attach rehearsal data with associations if this is a rehearsal event
            if ($event->eventable_type === 'App\\Models\\Rehearsal' && $event->eventable_id) {
                $rehearsal = $rehearsalData->get($event->eventable_id);
                if ($rehearsal) {
                    $event->eventable = $rehearsal;
                }
            }
            
            return $event;
        });
    }

    public function calendarAccess()
    {
        return $this->hasMany(CalendarAccess::class);
    }

    public function deviceTokens()
    {
        return $this->hasMany(\App\Models\DeviceToken::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }
}
