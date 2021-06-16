<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contracts extends Model
{
    use HasFactory;

    protected $fillable = ['proposal_id','envelope_id','status','image_url'];


    public function proposal()
    {
        return $this->belongsTo(Proposals::class,'proposal_id');
    }
}
