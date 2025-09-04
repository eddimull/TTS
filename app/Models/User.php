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
            'Events' => false,
            'Proposals' => false,
            'Invoices' => false,
            'Colors' => false,
            'Charts' => false,
            'Bookings' => false
        ];


        if (count($this->bandOwner) > 0) //no need to check anything else. They should have access to all the stuff for their band
        {
            return [
                'Events' => true,
                'Proposals' => true,
                'Invoices' => true,
                'Colors' => true,
                'Charts' => true,
                'Bookings' => true
            ];
        }
        $bands = $this->bandMember;

        foreach ($bands as $band)
        {
            $permissions = $this->permissionsForBand($band->id);

            foreach ($availableNav as $key => $navItem)
            {
                if (!$navItem)
                {
                    if ($permissions['read_' . strtolower($key)])
                    {
                        $availableNav[$key] = true;
                    }
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
        
        // Build the main events query
        $query = Events::join('bookings', 'events.eventable_id', '=', 'bookings.id')
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
                'bookings.band_id',
                'bookings.name as booking_name',
                'bookings.id as booking_id',
                'bookings.venue_name',
                'bookings.venue_address'
            ])
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id');

        if ($includeNotes) {
            $query->addSelect('events.notes');
        }
        
        // Apply date filter if provided
        if ($afterDate) {
            $query->where('events.date', '>', $afterDate);
        }
        
        // Get events
        $events = $query->orderBy('events.date')->get();
        
        if ($events->isEmpty()) {
            return collect();
        }
        
        // Get all booking IDs from the events
        $bookingIds = $events->pluck('booking_id')->unique()->toArray();
        
        // Load all contacts for these bookings in one query
        $contacts = \DB::table('booking_contacts') // Adjust table name as needed
            ->whereIn('booking_id', $bookingIds)
            ->get()
            ->groupBy('booking_id');
        
        // Attach contacts to events
        return $events->map(function ($event) use ($contacts) {
            $event->contacts = $contacts->get($event->booking_id, collect());
            return $event;
        });
    }

    public function calendarAccess()
    {
        return $this->hasMany(CalendarAccess::class);
    }
}
