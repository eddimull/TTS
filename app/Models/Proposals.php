<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposals extends Model
{
    use HasFactory;

    protected $table = 'proposals';
    protected $with = ['band','proposal_contacts','phase','author','event_type','recurring_dates','contract'];



    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    public function contract()
    {
        return $this->hasOne(Contracts::class,'proposal_id');
    }

    public function proposal_contacts()
    {
        return $this->hasMany(ProposalContacts::class,'proposal_id');
    }

    public function recurring_dates()
    {
        return $this->hasMany(recurring_proposal_dates::class,'proposal_id');
    }
    
    public function invoices()
    {
        return $this->hasMany(Invoices::class,'proposal_id');
    }

    public function phase()
    {
        return $this->belongsTo(ProposalPhases::class,'phase_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class,'author_id');
    }

    public function event_type()
    {
        return $this->belongsTo(EventTypes::class);
    }

    public function stripe_customers()
    {
        return $this->hasMany(stripe_customers::class,'proposal_id');
    }



}
