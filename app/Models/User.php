<?php

namespace App\Models;

use App\Models\Bandnotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        return $this->belongsToMany(Bands::class,'band_owners','user_id','band_id');
    }

    public function bandMember()
    {
        return $this->belongsToMany(BandMembers::class);
    }

    public function notifications()
    {
        return $this->morphMany(Bandnotification::class, 'notifiable')
                        ->orderBy('created_at','desc');
    }

    public function ownsBand($id)
    {
        $bandsOwned = $this->bandOwner;
        $owns = false;
        foreach($bandsOwned as $band)
        {
            if($id == $band->id)
            {
                $owns = true;
            }
        }

        return $owns;
    }

}
