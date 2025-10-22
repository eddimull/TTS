<?php

namespace App\Models;

use App\Models\Bandnotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Charts;
use App\Models\userPermissions;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
        return $this->belongsToMany(Bands::class, 'band_owners', 'user_id', 'band_id');
    }

    public function bandMember()
    {
        return $this->belongsToMany(Bands::class, 'band_members', 'user_id', 'band_id');
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

        $permissions = $this->permissionsForBand($bandId);
        $permissionKey = 'read_' . $resource;

        return (bool)($permissions->{$permissionKey} ?? false);
    }

    /**
     * Check if user can write a specific resource type
     */
    public function canWrite($resource, $bandId)
    {
        if ($this->ownsBand($bandId)) {
            return true;
        }

        $permissions = $this->permissionsForBand($bandId);
        $permissionKey = 'write_' . $resource;

        return (bool)($permissions->{$permissionKey} ?? false);
    }

    public function charts()
    {
        // return $this->hasManyThrough(Charts::class,Bands::class,'band_members','user_id','band_id');
        $bandIds = [];

        $ownedBands = $this->bandOwner;
        $bandMember = $this->bandMember;

        foreach ($ownedBands as $band)
        {
            array_push($bandIds, $band->id);
        }

        foreach ($bandMember as $band)
        {
            array_push($bandIds, $band->id);
        }
        $bandIds = array_unique($bandIds);

        $charts = Charts::whereIn('band_id', $bandIds)->orderBy('title', 'asc')->get(); //when the charts gets rendered, it will be ordered by title from last to first (proper alphabetical order)

        return $charts;
    }

    public function questionnaires()
    {
        $bandIds = [];

        $ownedBands = $this->bandOwner;
        $bandMember = $this->bandMember;

        foreach ($ownedBands as $band)
        {
            array_push($bandIds, $band->id);
        }

        foreach ($bandMember as $band)
        {
            array_push($bandIds, $band->id);
        }
        $bandIds = array_unique($bandIds);

        $charts = Questionnairres::whereIn('band_id', $bandIds)->orderBy('name')->get();

        return $charts;
    }

    public function getNav()
    {
        $availableNav = [
            'Events' => ['read' => false, 'write' => false],
            'Proposals' => ['read' => false, 'write' => false],
            'Invoices' => ['read' => false, 'write' => false],
            'Colors' => ['read' => false, 'write' => false],
            'Charts' => ['read' => false, 'write' => false],
            'Bookings' => ['read' => false, 'write' => false],
            'Rehearsals' => ['read' => false, 'write' => false]
        ];


        if (count($this->bandOwner) > 0) //no need to check anything else. They should have access to all the stuff for their band
        {
            return [
                'Events' => ['read' => true, 'write' => true],
                'Proposals' => ['read' => true, 'write' => true],
                'Invoices' => ['read' => true, 'write' => true],
                'Colors' => ['read' => true, 'write' => true],
                'Charts' => ['read' => true, 'write' => true],
                'Bookings' => ['read' => true, 'write' => true],
                'Rehearsals' => ['read' => true, 'write' => true]
            ];
        }
        $bands = $this->bandMember;

        foreach ($bands as $band)
        {
            $permissions = $this->permissionsForBand($band->id);

            foreach ($availableNav as $key => $navItem)
            {
                $resourceKey = strtolower($key);
                
                // Check read permission
                if (!$navItem['read'] && $permissions['read_' . $resourceKey])
                {
                    $availableNav[$key]['read'] = true;
                }
                
                // Check write permission
                if (!$navItem['write'] && $permissions['write_' . $resourceKey])
                {
                    $availableNav[$key]['write'] = true;
                }
            }
        }
        return $availableNav;
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


    public function getEventsAttribute($afterDate = null, $includeNotes = false)
    {
        $bandIds = $this->bands()->pluck('id')->toArray();
        
        if (empty($bandIds)) {
            return collect();
        }
        
        // Build the main booking events query
        $bookingQuery = Events::join('bookings', 'events.eventable_id', '=', 'bookings.id')
            ->whereIn('bookings.band_id', $bandIds)
            ->where('events.eventable_type', 'App\\Models\\Bookings') 
            ->select([
                'events.date',
                'events.event_type_id',
                'event_types.name as event_type_name',
                'events.id',
                'events.title',
                'events.date',
                'events.time',
                'events.key',
                'events.additional_data',
                'events.eventable_id',
                'events.eventable_type',
                'bookings.band_id',
                'bookings.name as booking_name',
                'bookings.id as booking_id',
                'bookings.venue_name',
                'bookings.venue_address',
                \DB::raw("'booking' as event_source"),
                \DB::raw('NULL as is_cancelled'),
                \DB::raw('FALSE as is_virtual')
            ])
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id');

        if ($includeNotes) {
            $bookingQuery->addSelect('events.notes');
        }
        
        // Apply date filter if provided
        if ($afterDate) {
            $bookingQuery->where('events.date', '>=', $afterDate->toDateString());
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
                'events.time',
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
        
        // Get both result sets and merge them
        $bookingEvents = $bookingQuery->get();
        $rehearsalEvents = $rehearsalQuery->get();
        
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
}
