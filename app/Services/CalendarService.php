<?php

namespace App\Services;

use App\Models\BandEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\WeeklyAdvance;
use Illuminate\Support\Facades\Config;
use Spatie\GoogleCalendar\Event as CalendarEvent;
use Spatie\GoogleCalendar\GoogleCalendar;
use Spatie\GoogleCalendar\GoogleCalendarFactory;
use Illuminate\Support\Facades\Log;

class CalendarService{

    protected $band;
    protected $googleCalendar = false;

   public function __construct($band)
   {
       $this->band = $band;
       $this->setGoogleCalendar();
   }

   private function setGoogleCalendar()
   {
       if ($this->band->calendar_id) {
           // Use the factory to create a GoogleCalendar instance for this specific calendar
           Config::set('google-calendar.calendar_id', $this->band->calendar_id);
           $this->googleCalendar = GoogleCalendarFactory::createForCalendarId($this->band->calendar_id);
       }
   }

   public function syncEvents()
   {
    // Sync existing band events
    $BandEvents = $this->band->events;
    if(count($BandEvents) > 0)
    {
       foreach($BandEvents as $event)
       {
           $this->writeEventToCalendar($event);
           sleep(0.1);//to prevent google rate limiting tts.band
       }
    }
    
    // Sync bookings
    $this->syncBookings();
}

/**
 * Sync all bookings to Google Calendar with status-based color coding
 */
public function syncBookings()
{
    $bookings = $this->band->bookings;
    if(count($bookings) > 0)
    {
        foreach($bookings as $booking)
        {
            $this->writeBookingToCalendar($booking);
            sleep(1);//to prevent google rate limiting
        }
    }
}

/**
 * Write a booking to Google Calendar with status-based color coding
 */
public function writeBookingToCalendar($booking)
{
    if(!$this->googleCalendar) return;
        // Check if booking already has a calendar event
        if($booking->google_calendar_event_id !== null)
        {
            $calendarEvent = CalendarEvent::find($booking->google_calendar_event_id, $this->band->calendar_id);
        }
        else
        {
            $calendarEvent = new CalendarEvent;
        }

        $calendarEvent->googleCalendarId = $this->band->calendar_id;

        // Set event name with status indicator
        $calendarEvent->name = $booking->name . ' (' . ucfirst($booking->status) . ')';

        $startTime = Carbon::parse($booking->start_date_time);
        $endTime = Carbon::parse($booking->end_date_time);
        
        // Handle events that end after midnight
        if($endTime < $startTime)
        {
            $endTime = $endTime->addDay();
        }

        $calendarEvent->startDateTime = $startTime;
        $calendarEvent->endDateTime = $endTime;

        // Set color based on booking status
        $calendarEvent->color = $this->getBookingStatusColor($booking->status);

        // Create description with booking details
        $description = $this->buildBookingDescription($booking);
        $calendarEvent->description = $description;

        $google_id = $calendarEvent->save();
        $booking->google_calendar_event_id = $google_id->id;
        $booking->save();
    
}

/**
 * Get Google Calendar color code based on booking status
 */
private function getBookingStatusColor($status)
{
    switch($status) {
        case 'draft':
            return 8; // Gray
        case 'pending':
            return 5; // Yellow/Orange
        case 'confirmed':
            return 10; // Green
        default:
            return 1; // Default blue
    }
}

/**
 * Build description for booking calendar event
 */
private function buildBookingDescription($booking)
{
    $description = "Status: " . ucfirst($booking->status) . "\n\n";
    $description .= "Venue: " . $booking->venue_name . "\n";
    
    if($booking->venue_address) {
        $description .= "Address: " . $booking->venue_address . "\n";
    }
    
    if($booking->price) {
        $description .= "Price: $" . number_format($booking->price, 2) . "\n";
    }
    
    $description .= "Duration: " . (Carbon::parse($booking->end_time)->diffInHours(Carbon::parse($booking->start_time))) . " hours\n";
    
    if($booking->notes) {
        $description .= "\nNotes: " . strip_tags($booking->notes) . "\n";
    }
    
    // Add contact information if available
    if($booking->contacts && count($booking->contacts) > 0) {
        $description .= "\nContacts:\n";
        foreach($booking->contacts as $contact) {
            $description .= "- " . $contact->name . " (" . $contact->email . ")";
            if($contact->phone) {
                $description .= " - " . $contact->phone;
            }
            $description .= "\n";
        }
    }
    
    return $description;
}

/**
 * Update a specific booking in Google Calendar
 */
public function updateBookingInCalendar($booking)
{
    $this->writeBookingToCalendar($booking);
}

/**
 * Delete a booking from Google Calendar
 */
public function deleteBookingFromCalendar($booking)
{
    if($booking->google_calendar_event_id && $this->band->calendar_id)
    {
        try {
            $calendarEvent = CalendarEvent::find($booking->google_calendar_event_id, $this->band->calendar_id);
            if($calendarEvent) {
                $calendarEvent->delete();
            }
            
            $booking->google_calendar_event_id = null;
            $booking->save();
        } catch (\Exception $e) {
            Log::error('Failed to delete booking from calendar: ' . $e->getMessage());
        }
    }
}

   public function writeEventToCalendar($event)
   {
    if($this->band->calendar_id !== '' && $this->band->calendar_id !== null)
    {
        if($event->google_calendar_event_id !== null)
        {
            $calendarEvent = CalendarEvent::find($event->google_calendar_event_id, $this->band->calendar_id);
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
        if($event->google_calendar_event_id !== null)
        {
            $calendarEvent = CalendarEvent::find($event->google_calendar_event_id, $this->band->calendar_id);
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

   /**
    * Create a new Google Calendar for the band
    */
   public function createBandCalendar()
   {
       try {
           // Create a default GoogleCalendar instance to get the service
           $defaultCalendarId = config('google-calendar.calendar_id');
           $googleCalendar = GoogleCalendarFactory::createForCalendarId($defaultCalendarId);
           $service = $googleCalendar->getService();

           // Create calendar
           $calendar = new \Google\Service\Calendar\Calendar();
           $calendar->setSummary($this->band->name . ' - Bookings & Events');
           $calendar->setDescription('Automated calendar for ' . $this->band->name . ' bookings and events');
           $calendar->setTimeZone('America/New_York');

           $createdCalendar = $service->calendars->insert($calendar);
           
           if (!$createdCalendar || !$createdCalendar->getId()) {
               Log::error('Failed to create calendar - no calendar ID returned');
               return null;
           }

           $calendarId = $createdCalendar->getId();
           Log::info('Created Google Calendar with ID: ' . $calendarId);

           // Update the band with the new calendar ID
           $this->band->calendar_id = $calendarId;
           $this->band->save();

           // Reset the googleCalendar instance to use the new calendar ID
           $this->googleCalendar = null;

           return $calendarId;
           
       } catch (\Exception $e) {
           Log::error('Failed to create Google Calendar: ' . $e->getMessage());
           Log::error('Stack trace: ' . $e->getTraceAsString());
           return null;
       }
   }

   /**
     * Grant calendar access to all band members and owners
     */
    public function grantBandAccess()
    {
        if (!$this->band->calendar_id) {
            Log::error('No calendar ID set for band: ' . $this->band->name);
            return false;
        }

        try {

            // Grant access to all band owners
            foreach ($this->band->owners as $owner) {
                $this->grantUserAccess($this->googleCalendar, $owner->user, 'owner');
            }

            // Grant access to all band members
            foreach ($this->band->members as $member) {
                $this->grantUserAccess($this->googleCalendar, $member->user, 'writer');
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to grant band access to calendar: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Grant calendar access to a specific user
     */
    public function grantUserAccess($service = null, $user = null, $role = 'writer')
    {
        try {
            if (!$this->band->calendar_id) {
                Log::error('No calendar ID set for band: ' . $this->band->name);
                return false;
            }

            // Handle different calling patterns
            if (!$user) {
                // This case handles calls where user is the first argument
                $user = func_get_arg(0);
                $role = func_get_arg(1) ?? 'writer';
            }

            if (!$user || !$user->email) {
                Log::error('No user or email provided for calendar access');
                return false;
            }

            // Map role to Google Calendar ACL role
            $aclRole = $this->mapRoleToAclRole($role);

            // Create ACL rule
            $rule = new \Google\Service\Calendar\AclRule();
            $scope = new \Google\Service\Calendar\AclRuleScope();
            $scope->setType('user');
            $scope->setValue($user->email);
            $rule->setScope($scope);
            $rule->setRole($aclRole);

            // Insert the ACL rule
            $service->acl->insert($this->band->calendar_id, $rule);

            Log::info("Granted {$role} access to {$user->email} for calendar {$this->band->calendar_id}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to grant calendar access to {$user->email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Map application roles to Google Calendar ACL roles
     */
    private function mapRoleToAclRole($role)
    {
        switch ($role) {
            case 'owner':
                return 'owner';
            case 'writer':
                return 'writer';
            case 'reader':
                return 'reader';
            default:
                return 'reader';
        }
    }

    /**
     * Validate that the band's calendar exists and is accessible
     */
    public function validateCalendarAccess()
    {
        try {
            if (!$this->band->calendar_id) {
                return false;
            }

            // Try to get calendar metadata
            $calendar = GoogleCalendarFactory::createForCalendarId($this->band->calendar_id)->getService()->calendars->get($this->band->calendar_id);

            return $calendar !== null;
            
        } catch (\Exception $e) {
            Log::error("Calendar validation failed for band {$this->band->name}: " . $e->getMessage());
            return false;
        }
    }
}
