<?php

namespace App\Services;

use App\Models\BandEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\WeeklyAdvance;

class AdvanceReminderService{

    protected $band;

   public function __construct($band)
   {
       $this->band = $band;
   }

   public function searchAndSend()
   {
    $BandEvents = BandEvents::whereBetween('event_time',[Carbon::now(),Carbon::now()->addWeek()])->where('band_id',$this->band->id)->get();
    if(count($BandEvents) > 0)
    {
        $owners = $this->band->owners;
        foreach($owners as $owner)
        {
            Mail::to($owner->user->email)->send(
                new WeeklyAdvance($BandEvents)
            );
        }

        $members = $this->band->members;
        foreach($members as $member)
        {
            Mail::to($member->user->email)->send(
                new WeeklyAdvance($BandEvents)
            );
        }
        
    }
   }
}