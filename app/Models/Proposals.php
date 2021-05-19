<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Proposals extends Pivot
{
    protected $table = 'proposals';
    protected $with = ['band','proposal_contacts','phase','author'];



    public function band()
    {
        return $this->belongsTo(Bands::class);
    }


    public function proposal_contacts()
    {
        return $this->hasMany(ProposalContacts::class,'proposal_id');
    }

    public function phase()
    {
        return $this->belongsTo(ProposalPhases::class,'phase_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class,'author_id');
    }
}
