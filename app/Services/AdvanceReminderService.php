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
        $everyone = $this->band->everyone();
        foreach($everyone as $associate)
        {
            Mail::to($associate->user->email)->send(
                new WeeklyAdvance($BandEvents)
            );
        }
        
    }
   }
}