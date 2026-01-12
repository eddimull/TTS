<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bands extends Model
{
    use HasFactory;


    public function getRouteKeyName()
    {
        return 'id';
    }

    protected $fillable = ['name', 'site_name'];

    public function owner()
    {
        return $this->hasMany(BandOwners::class, 'band_id')->orderBy('created_at')->limit(1);
    }

    public function owners()
    {
        return $this->hasMany(BandOwners::class, 'band_id');
    }

    public function member()
    {
        return $this->hasMany(BandMembers::class, 'band_id')->orderBy('created_at')->limit(1);
    }
    public function members()
    {
        return $this->hasMany(BandMembers::class, 'band_id');
    }

    public function everyone()
    {
        $owners = collect($this->owners);
        $members = collect($this->members);


        return $owners->merge($members);
    }

    public function rosters()
    {
        return $this->hasMany(Roster::class, 'band_id');
    }

    public function defaultRoster()
    {
        return $this->hasOne(Roster::class, 'band_id')->where('is_default', true);
    }

    public function substituteCallLists()
    {
        return $this->hasMany(SubstituteCallList::class, 'band_id')->orderBy('instrument')->orderBy('priority');
    }

    public function stripe_accounts()
    {
        return $this->hasOne(StripeAccounts::class, 'band_id');
    }

    public function invites()
    {
        return $this->hasMany(Invitations::class, 'band_id');
    }
    public function invitations()
    {
        return $this->hasMany(Invitations::class, 'band_id');
    }

    public function pendingInvites()
    {
        return $this->invitations()->where('pending', '=', '1');
    }

    public function proposals()
    {
        return $this->hasMany(Proposals::class, 'band_id')->orderBy('created_at', 'desc');
    }
    public function completedProposals()
    {
        return $this->hasMany(Proposals::class, 'band_id')->where('phase_id', '=', '6')->with(['invoices', 'payments'])->orderBy('name', 'asc');
    }

    public function completedBookings()
    {
        return $this->hasMany(Bookings::class, 'band_id')->where('status', '=', 'confirmed')->with(['payments'])->orderBy('name', 'asc');
    }

    public function events()
    {
        return $this->hasManyThrough(
        Events::class,
        Bookings::class,
        'band_id',     // Foreign key on bookings table
        'eventable_id', // Foreign key on events table
        'id',          // Local key on bands table
        'id'           // Local key on bookings table
        )
        ->select(['events.*'])
        ->addSelect(DB::raw('NULL as notes'))
        ->orderBy('events.date', 'desc')
        ->where('eventable_type', Bookings::class);
    }

    public function futureEvents()
    {
        return $this->events()->where('events.date', '>=', now());
    }

    public function publicEvents()
    {
        return $this->hasManyThrough(
            Events::class,
            Bookings::class,
            'band_id',     // Foreign key on bookings table
            'eventable_id', // Foreign key on events table
            'id',          // Local key on bands table
            'id'           // Local key on bookings table
        )
        ->where('eventable_type', Bookings::class)
        ->whereRaw("JSON_EXTRACT(events.additional_data, '$.public') IN (1, true, '1', 'true')");
    }

    public function futurePublicEvents()
    {
        return $this->publicEvents()->where('events.date', '>=', now());
    }

    public function colorways()
    {
        // belongsToMany(Bands::class,'band_owners','user_id','band_id')
        return $this->hasMany(Colorways::class, 'band_id');
    }

    public function payments()
    {
        return $this->hasMany(Payments::class, 'band_id');
    }

    public function paymentsByYear()
    {
        return $this->payments()
            ->selectRaw('YEAR(date) as year, SUM(amount) as total')
            ->groupBy(DB::raw('YEAR(date)'))
            ->orderBy('year', 'desc');
    }

    public function bookings()
    {
        return $this->hasMany(Bookings::class, 'band_id')->orderBy('date', 'desc');
    }

    public function getUnpaidBookings($snapshotDate = null)
    {
        $unpaidBookings = $this->bookings()->unpaid();

        if ($snapshotDate) {
            return $unpaidBookings->filter(function ($booking) use ($snapshotDate) {
                return $booking->created_at <= $snapshotDate;
            })->values();
        }

        return $unpaidBookings;
    }

    public function getPaidBookings($snapshotDate = null)
    {
        $paidBookings = $this->bookings()->paid();

        if ($snapshotDate) {
            return $paidBookings->filter(function ($booking) use ($snapshotDate) {
                return $booking->created_at <= $snapshotDate;
            })->values();
        }

        return $paidBookings;
    }

    public function contacts()
    {
        return $this->hasMany(Contacts::class, 'band_id')->orderBy('name', 'asc');
    }

    public function calendars()
    {
        return $this->hasMany(BandCalendars::class, 'band_id');
    }

    public function eventCalendar()
    {
        return $this->hasOne(BandCalendars::class, 'band_id')->where('type', 'event');
    }

    public function publicCalendar()
    {
        return $this->hasOne(BandCalendars::class, 'band_id')->where('type', 'public');
    }

    public function bookingCalendar()
    {
        return $this->hasOne(BandCalendars::class, 'band_id')->where('type', 'booking');
    }

    public function rehearsalSchedules()
    {
        return $this->hasMany(RehearsalSchedule::class, 'band_id')->orderBy('name', 'asc');
    }

    public function activeRehearsalSchedules()
    {
        return $this->rehearsalSchedules()->where('active', true);
    }

    public function payoutConfigs()
    {
        return $this->hasMany(BandPayoutConfig::class, 'band_id')->orderBy('is_active', 'desc')->orderBy('created_at', 'desc');
    }

    public function activePayoutConfig()
    {
        return $this->hasOne(BandPayoutConfig::class, 'band_id')->where('is_active', true);
    }

    public function paymentGroups()
    {
        return $this->hasMany(BandPaymentGroup::class, 'band_id')->where('is_active', true)->orderBy('display_order');
    }

    public function apiTokens()
    {
        return $this->hasMany(BandApiToken::class, 'band_id')->orderBy('created_at', 'desc');
    }

    public function activeApiTokens()
    {
        return $this->apiTokens()->where('is_active', true);
    }

    /**
     * Check if a user has access to any of this band's calendars
     */
    public function userHasCalendarAccess($user)
    {
        // Only check explicit calendar access
        return CalendarAccess::whereIn('band_calendar_id', $this->calendars->pluck('id'))
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Get user's role for a specific calendar type
     */
    public function getUserCalendarRole($user, $calendarType)
    {
        // Get explicit calendar access only
        $calendar = $this->calendars()->where('type', $calendarType)->first();
        if ($calendar) {
            $access = CalendarAccess::where('user_id', $user->id)
                ->where('band_calendar_id', $calendar->id)
                ->first();
            
            return $access ? $access->role : null;
        }
        
        return null;
    }
}
