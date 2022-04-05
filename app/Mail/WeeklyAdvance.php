<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class WeeklyAdvance extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($events)
    {
        $this->events = $events;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $message = "You are scheduled for one gig this week";
        if(count($this->events) > 1)
        {
            $message = "You are scheduled for " . count($this->events) . " gigs this week";
        }
        foreach($this->events as $event)
        {
            $event->formattedDate = Carbon::parse($event->event_time)->format('Y/m/d (D)');
            $event->type = $event->event_type->name;
            $event->advance = $event->advanceURL();
        }
        return $this->markdown('email.weekly_advance')->with([
            'events'=>$this->events,
            'message'=>$message
        ]);
    }
}
