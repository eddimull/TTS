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


    public function getEventsAttribute($afterDate = null)
    {
        $bands = $this->bands();

        $bands->load(['bookings.events' => function ($query) use ($afterDate)
        {
            if ($afterDate)
            {
                $query->where('date', '>', $afterDate);
            }
            $query->orderBy('date');
        }, 'bookings.contacts']);

        $events = $bands->flatMap(function ($band)
        {
            return $band->bookings->flatMap(function ($booking)
            {
                return $booking->events->map(function ($event) use ($booking)
                {
                    $event->band_id = $booking->band_id;
                    $event->booking_name = $booking->name;
                    $event->booking_id = $booking->id;
                    $event->contacts = $booking->contacts->makeHidden('booking_history');
                    $event->venue_name = $booking->venue_name;
                    $event->venue_address = $booking->venue_address;
                    return $event;
                });
            });
        });
        return $events->sortBy('date')->values();
    }
}
