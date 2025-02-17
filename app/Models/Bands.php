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

    protected $fillable = ['name', 'site_name', 'calendar_id'];
    // protected $with = ['proposals'];

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
        return $this->hasMany(BandEvents::class, 'band_id');
    }

    public function futureEvents()
    {
        return $this->events()->where('event_time', '>=', now());
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
        return $this->hasMany(Bookings::class, 'band_id');
    }

    public function getUnpaidBookings()
    {
        return $this->bookings()->unpaid();
    }

    public function getPaidBookings()
    {
        return $this->bookings()->paid();
    }

    public function contacts()
    {
        return $this->hasMany(Contacts::class, 'band_id')->orderBy('name', 'asc');
    }
}
