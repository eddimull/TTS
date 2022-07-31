<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bands extends Model
{
    use HasFactory;
    protected $fillable = ['name','site_name','calendar_id'];
    // protected $with = ['proposals'];

    public function owner()
    {
        return $this->hasMany(BandOwners::class,'band_id')->orderBy('created_at')->limit(1);
    }

    public function owners()
    {
        return $this->hasMany(BandOwners::class,'band_id');
    }

    public function member()
    {
        return $this->hasMany(BandMembers::class,'band_id')->orderBy('created_at')->limit(1);
    }
    public function members()
    {
        return $this->hasMany(BandMembers::class,'band_id');
    }

    public function everyone()
    {
        $owners = collect($this->owners);
        $members = collect($this->members);
        
        
        return $owners->merge($members);
    }

    public function stripe_accounts()
    {
        return $this->hasOne(stripe_accounts::class,'band_id');
    }

    public function invites()
    {
        return $this->hasMany(Invitations::class,'band_id');
    }
    public function invitations()
    {
        return $this->hasMany(Invitations::class,'band_id');
    }

    public function pendingInvites()
    {
        return $this->invitations()->where('pending','=','1');
    }

    public function proposals()
    {
        return $this->hasMany(Proposals::class,'band_id')->orderBy('created_at','desc');
    }
    public function completedProposals()
    {
        return $this->hasMany(Proposals::class,'band_id')->where('phase_id','=','6')->with('invoices')->orderBy('name','asc');
    }

    public function events()
    {
        return $this->hasMany(BandEvents::class,'band_id');
    }
    public function colorways()
    {
        // belongsToMany(Bands::class,'band_owners','user_id','band_id')
        return $this->hasMany(Colorways::class,'band_id');
    }

    public function payments()
    {
        return $this->hasManyThrough(ProposalPayments::class,Proposals::class,'band_id','proposal_id');
    }

}
