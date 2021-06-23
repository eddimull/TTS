<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventContacts extends Model
{
    use HasFactory;
    protected $fillable = ['event_id','name','email','phonenumber'];
}
