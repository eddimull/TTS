<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProposalContacts extends Pivot
{
    use HasFactory;

    protected $table = 'proposal_contacts';
    protected $fillable = ['proposal_id', 'name', 'email', 'phonenumber'];
}
