<?php

namespace App\Formatters;

use Carbon\Carbon;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Rehearsal;

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

    public static function formatRehearsalDescription(Rehearsal $rehearsal)
    {
        $elements = [];
        
        // Rehearsal Schedule info
        if ($rehearsal->rehearsalSchedule) {
            $elements['Rehearsal Schedule'] = $rehearsal->rehearsalSchedule->name;
            
            if ($rehearsal->rehearsalSchedule->frequency) {
                $elements['Frequency'] = ucfirst($rehearsal->rehearsalSchedule->frequency);
            }
        }
        
        // Venue information
        if ($rehearsal->venue_name) {
            $elements['Venue'] = $rehearsal->venue_name;
        } elseif ($rehearsal->rehearsalSchedule && $rehearsal->rehearsalSchedule->location_name) {
            $elements['Venue'] = $rehearsal->rehearsalSchedule->location_name;
        }
        
        if ($rehearsal->venue_address) {
            $elements['Address'] = $rehearsal->venue_address;
        } elseif ($rehearsal->rehearsalSchedule && $rehearsal->rehearsalSchedule->location_address) {
            $elements['Address'] = $rehearsal->rehearsalSchedule->location_address;
        }
        
        // Notes (convert HTML to properly formatted text) - for rehearsals, this is typically songs to work on
        if ($rehearsal->notes) {
            // Convert HTML to text while preserving structure
            $cleanNotes = $rehearsal->notes;
            
            // Convert HTML paragraphs to line breaks
            $cleanNotes = preg_replace('/<\/p>\s*<p>/', "\n\n", $cleanNotes);
            $cleanNotes = preg_replace('/<p[^>]*>/', '', $cleanNotes);
            $cleanNotes = str_replace('</p>', '', $cleanNotes);
            
            // Convert HTML list items to bullet points
            $cleanNotes = preg_replace('/<li[^>]*>/', "\n  • ", $cleanNotes);
            $cleanNotes = str_replace('</li>', '', $cleanNotes);
            
            // Remove remaining HTML tags
            $cleanNotes = strip_tags($cleanNotes);
            
            // Clean up extra whitespace and normalize line breaks
            $cleanNotes = preg_replace('/\n\s*\n\s*\n/', "\n\n", $cleanNotes);
            $cleanNotes = trim($cleanNotes);
            
            $elements['Songs to Work On'] = $cleanNotes;
        }
        
        // Structured song playlists from additional_data
        if (isset($rehearsal->additional_data->songs) && is_array($rehearsal->additional_data->songs)) {
            $songPlaylists = collect($rehearsal->additional_data->songs)
                ->map(function ($playlist) {
                    return "  {$playlist->title}: {$playlist->url}";
                })
                ->implode("\n");
            
            if ($songPlaylists) {
                $elements['Spotify Playlists'] = "\n" . $songPlaylists;
            }
        }
        
        // Charts from additional_data
        if (isset($rehearsal->additional_data->charts) && is_array($rehearsal->additional_data->charts)) {
            $chartsList = collect($rehearsal->additional_data->charts)
                ->map(function ($chart) {
                    $composer = isset($chart->composer) ? " by {$chart->composer}" : '';
                    $arranger = isset($chart->arranger) ? " (arr. {$chart->arranger})" : '';
                    return "  {$chart->title}{$composer}{$arranger}";
                })
                ->implode("\n");
            
            if ($chartsList) {
                $elements['Charts to Practice'] = "\n" . $chartsList;
            }
        }
        
        // Schedule notes
        if ($rehearsal->rehearsalSchedule && $rehearsal->rehearsalSchedule->notes) {
            $elements['Schedule Notes'] = strip_tags($rehearsal->rehearsalSchedule->notes);
        }
        
        // Associated bookings/events
        $associations = $rehearsal->associations()->with('associable')->get();
        if ($associations->count() > 0) {
            $assocList = $associations->map(function ($assoc) {
                if ($assoc->associable_type === 'App\\Models\\Bookings') {
                    return "Booking: " . $assoc->associable->name;
                } elseif ($assoc->associable_type === 'App\\Models\\Events') {
                    return "Event: " . $assoc->associable->title;
                }
                return null;
            })->filter()->implode("\n  ");
            
            if ($assocList) {
                $elements['Preparing For'] = "\n  " . $assocList;
            }
        }
        
        // Additional data from JSON field
        if (isset($rehearsal->additional_data->setlist) && !empty($rehearsal->additional_data->setlist)) {
            $elements['Setlist'] = strip_tags($rehearsal->additional_data->setlist);
        }
        
        if (isset($rehearsal->additional_data->attendees) && is_array($rehearsal->additional_data->attendees)) {
            $attendees = implode(', ', $rehearsal->additional_data->attendees);
            if ($attendees) {
                $elements['Expected Attendees'] = $attendees;
            }
        }
        
        return collect($elements)
            ->filter() // Remove empty values
            ->map(fn($value, $key) => "{$key}: {$value}")
            ->implode("\n\n");
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