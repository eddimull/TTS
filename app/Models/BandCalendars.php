<?php

namespace App\Models;

use App\Services\GoogleCalendarService;
use Google\Service\Calendar\Calendar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BandCalendars extends Model
{
    use HasFactory;

    public function getRouteKeyName()
    {
        return 'calendar_id';
    }

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

    public function getGoogleCalendarAttribute(): Calendar
    {
        return app(GoogleCalendarService::class)->getCalendar($this->calendar_id);
    }
    

}
