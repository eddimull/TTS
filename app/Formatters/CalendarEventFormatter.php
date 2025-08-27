<?php

namespace App\Formatters;

use Carbon\Carbon;
use App\Models\Events;
use App\Models\Bookings;

class CalendarEventFormatter
{
    public static function formatEventDescription(Events $event)
    {
        $elements = [];
        
        // Event Type
        if ($event->type) {
            $elements['Event Type'] = $event->type->name;
        }
        
        // Venue information
        if ($event->eventable) {
            if ($event->eventable->venue_name) {
                $elements['Venue'] = $event->eventable->venue_name;
            }
            if ($event->eventable->venue_address) {
                $elements['Address'] = $event->eventable->venue_address;
            }
        }
        
        // Notes (strip HTML tags)
        if ($event->notes) {
            $elements['Notes'] = strip_tags($event->notes);
        }
        
        // Timeline - format the times array
        if (isset($event->additional_data->times) && is_array($event->additional_data->times)) {
            $timeline = collect($event->additional_data->times)
                ->sortBy('time')
                ->map(function ($timeEntry) {
                    $time = Carbon::parse($timeEntry->time)->format('g:i A');
                    return "  {$time} - {$timeEntry->title}";
                })
                ->implode("\n");
            
            if ($timeline) {
                $elements['Timeline'] = "\n" . $timeline;
            }
        }
        
        // Attire (strip HTML tags)
        if (isset($event->additional_data->attire) && !empty($event->additional_data->attire)) {
            $elements['Attire'] = strip_tags($event->additional_data->attire);
        }
        
        // Additional conditions
        if (isset($event->additional_data->outside) && $event->additional_data->outside) {
            $elements['Conditions'] = 'Outside event';
        }
        
        if (isset($event->additional_data->backline_provided) && $event->additional_data->backline_provided) {
            $elements['Backline'] = 'Provided';
        }
        
        if (isset($event->additional_data->production_needed) && $event->additional_data->production_needed) {
            $elements['Production'] = 'Required';
        }
        
        // Advance URL
        $elements['Advance URL'] = $event->advanceURL();
        
        return collect($elements)
            ->filter() // Remove empty values
            ->map(fn($value, $key) => "{$key}: {$value}")
            ->implode("\n\n");
    }

    public static function formatBookingDescription(Bookings $booking)
    {
        $description = "Status: " . ucfirst($booking->status) . "\n\n";
        $description .= "Venue: " . $booking->venue_name . "\n";
        
        if($booking->venue_address) {
            $description .= "Address: " . $booking->venue_address . "\n";
        }
        
        if($booking->price) {
            $description .= "Price: $" . number_format($booking->price, 2) . "\n";
        }

        $description .= "Duration: " . $booking->duration . " hours\n";

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

    public static function formatCalendar($calendar)
    {
        return [
            'id' => $calendar->id,
            'name' => $calendar->name,
            'description' => $calendar->description,
        ];
    }
}