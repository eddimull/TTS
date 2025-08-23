<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BandCalendars extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'calendar_id',
        'type',
    ];

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function userAccess()
    {
        return $this->hasMany(CalendarAccess::class, 'band_calendar_id');
    }

    public function grantAccess(User $user, string $role)
    {
        return $this->userAccess()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function revokeAccess(User $user)
    {
        return $this->userAccess()->where('user_id', $user->id)->delete();
    }

}
