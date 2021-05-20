<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProposalContacts extends Pivot
{
    //
    protected $table = 'proposal_contacts';
    protected $fillable = ['proposal_id','name','email','phonenumber'];
}
