<?php 

namespace App\Services;
use App\Models\BandEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserEventsService{
    public function getEvents()
    {
        return Auth::user()->events;
    }
}