<?php

namespace App\Services;

use App\Models\BandEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\WeeklyAdvance;
use Illuminate\Support\Facades\Config;
use Spatie\GoogleCalendar\Event as CalendarEvent;

class CalendarService{

    protected $band;

   public function __construct($band)
   {
       $this->band = $band;
   }

   public function syncEvents()
   {
    $BandEvents = $this->band->events;
    if(count($BandEvents) > 0)
    {
       foreach($BandEvents as $event)
       {
           $this->writeEventToCalendar($event);
           sleep(1);//to prevent google rate limiting tts.band
       }        
    }
   }

   public function writeEventToCalendar($event)
   {
    if($this->band->calendar_id !== '' && $this->band->calendar_id !== null)
    {

        Config::set('google-calendar.service_account_credentials_json',storage_path('/app/google-calendar/service-account-credentials.json'));
        Config::set('google-calendar.calendar_id',$this->band->calendar_id);
        
        // dd(Carbon::parse($event->event_time));

        if($event->google_calendar_event_id !== null)
        {
            $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
        }
        else
        {
            $calendarEvent = new CalendarEvent;
        }
        $calendarEvent->name = $event->event_name;

        $startTime = Carbon::parse($event->event_time);
        $endDateTimeFixed = date('Y-m-d',strtotime($event->event_time)) . ' ' . date('H:i:s', strtotime($event->end_time));
        if($endDateTimeFixed < $startTime)//when events end after midnight
        {
            $endDateTimeFixed = date('Y-m-d',strtotime($event->event_time . ' +1 day')) . ' ' . date('H:i:s', strtotime($event->end_time));
        }
        $endTime = Carbon::parse($endDateTimeFixed);
        $calendarEvent->startDateTime = $startTime;
        $calendarEvent->endDateTime = $endTime;   
        $calendarEvent->description =  $event->event_type->name . "\n\n" . $event->venue_name . "\n\n" . $event->address_street . "\n\n" . $event->zip . "\n\n" . $event->city . "\n\n" . $event->advanceURL();
        $google_id = $calendarEvent->save();  
        $event->google_calendar_event_id = $google_id->id;
        $event->save();
    }
   }

   public function writeProposalToCalendar($event)
   {
    if($this->band->calendar_id !== '' && $this->band->calendar_id !== null)
    {

        Config::set('google-calendar.service_account_credentials_json',storage_path('/app/google-calendar/service-account-credentials.json'));
        Config::set('google-calendar.calendar_id',$this->band->calendar_id);
        
        // dd(Carbon::parse($event->event_time));

        if($event->google_calendar_event_id !== null)
        {
            $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
        }
        else
        {
            $calendarEvent = new CalendarEvent;
        }
        $calendarEvent->name = $event->event_name;

        $startTime = Carbon::parse($event->event_time);
        $endDateTimeFixed = date('Y-m-d',strtotime($event->event_time)) . ' ' . date('H:i:s', strtotime($event->end_time));
        if($endDateTimeFixed < $startTime)//when events end after midnight
        {
            $endDateTimeFixed = date('Y-m-d',strtotime($event->event_time . ' +1 day')) . ' ' . date('H:i:s', strtotime($event->end_time));
        }
        $endTime = Carbon::parse($endDateTimeFixed);
        $calendarEvent->startDateTime = $startTime;
        $calendarEvent->endDateTime = $endTime;   
        $calendarEvent->description = $event->advanceURL();
        $calendarEvent->color = 2;
        $google_id = $calendarEvent->save();  
        $event->google_calendar_event_id = $google_id->id;
        $event->save();
    }
   }
}