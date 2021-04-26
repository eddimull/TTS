<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDistanceForMembers extends Model
{
    use HasFactory;
    protected $table = 'event_distance_for_members';
    protected $fillable = ['user_id','event_id','miles','minutes'];
}
