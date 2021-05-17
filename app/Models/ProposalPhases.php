<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalPhases extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function proposal()
    {
        return $this->belongsTo(Proposals::class);
    }
}
