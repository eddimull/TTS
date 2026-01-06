<?php

namespace App\Services;

use App\Models\BandEvents;
use App\Models\Events;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UserEventsService
{
    public function getEvents($afterDate = null, $beforeDate = null, $limit = null)
    {
        if ($afterDate === null) {
            $afterDate = Carbon::now()->subHours(72);
        }
        
        $events = Auth::user()->getEventsAttribute($afterDate, true, $beforeDate, $limit); //why isn't the laravel magic happening where I can just specify 'events'
        
        // Load attachments for all events
        $eventIds = $events->pluck('id')->filter()->unique()->toArray();
        if (!empty($eventIds)) {
            $attachmentsData = DB::table('event_attachments')
                ->whereIn('event_id', $eventIds)
                ->get()
                ->groupBy('event_id');
            
            // Add attachments to events
            $events = $events->map(function($event) use ($attachmentsData) {
                if (is_array($event)) {
                    $eventId = $event['id'] ?? null;
                } else {
                    $eventId = $event->id ?? null;
                }
                
                if ($eventId && isset($attachmentsData[$eventId])) {
                    $attachments = $attachmentsData[$eventId]->map(function($attachment) {
                        $bytes = $attachment->file_size;
                        $units = ['B', 'KB', 'MB', 'GB'];
                        $i = 0;
                        while ($bytes > 1024 && $i < count($units) - 1) {
                            $bytes /= 1024;
                            $i++;
                        }
                        
                        // Get URL using /images/ route
                        $url = url('/images/' . $attachment->stored_filename);
                        
                        return [
                            'id' => $attachment->id,
                            'filename' => $attachment->filename,
                            'mime_type' => $attachment->mime_type,
                            'file_size' => $attachment->file_size,
                            'formatted_size' => round($bytes, 2) . ' ' . $units[$i],
                            'url' => $url,
                        ];
                    })->toArray();
                    
                    if (is_array($event)) {
                        $event['attachments'] = $attachments;
                    } else {
                        $event->attachments = $attachments;
                    }
                }
                
                return $event;
            });
        }
        
        // Get user's band IDs
        $user = Auth::user();
        $ownedBands = $user->bandOwner()->pluck('bands.id')->toArray();
        $memberBands = $user->bandMember()->pluck('bands.id')->toArray();
        $bandIds = array_merge($ownedBands, $memberBands);
        
        // Generate virtual rehearsal events from schedules
        if (!empty($bandIds)) {
            $rehearsalService = new RehearsalScheduleService();
            $virtualRehearsals = $rehearsalService->generateUpcomingRehearsals($bandIds, $afterDate, $beforeDate);
            
            // Convert Eloquent models to arrays for consistency with virtual rehearsals
            // But preserve nested relationships for rehearsals
            $events = $events->map(function($event) {
                // If it's an Eloquent model, convert to array properly
                if (is_object($event) && method_exists($event, 'toArray')) {
                    $eventArray = $event->toArray();
                    
                    // For rehearsal events, preserve the eventable object if it was already loaded
                    // Use property_exists to avoid triggering the lazy loading relationship
                    if (property_exists($event, 'eventable') && $event->eventable !== null && $event->eventable_type === 'App\\Models\\Rehearsal') {
                        $eventArray['eventable'] = $event->eventable;
                    }
                    
                    return $eventArray;
                }
                // If it's already an array, return as-is
                return $event;
            });
            
            // Merge virtual rehearsals with actual events
            $events = $events->merge($virtualRehearsals);
            
            // Re-sort by date
            $events = $events->sortBy('date')->values();
        }
        
        return $events;
    }

    public function getUpcomingCharts()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // Get all user's band IDs
        $ownedBands = $user->bandOwner()->pluck('bands.id')->toArray();
        $memberBands = $user->bandMember()->pluck('bands.id')->toArray();
        $bandIds = array_merge($ownedBands, $memberBands);

        if (empty($bandIds)) {
            return collect();
        }

        // Query all upcoming booking events
        $bookingEvents = Events::join('bookings', function($join) {
                $join->on('events.eventable_id', '=', 'bookings.id')
                     ->where('events.eventable_type', '=', 'App\\Models\\Bookings');
            })
            ->whereIn('bookings.band_id', $bandIds)
            ->where('events.date', '>=', $today)
            ->select([
                'events.id',
                'events.title',
                'events.date',
                'events.time',
                'events.additional_data',
                'bookings.venue_name',
                'bookings.band_id'
            ])
            ->get();

        // Query all upcoming rehearsal events
        $rehearsalEvents = Events::join('rehearsals', function($join) {
                $join->on('events.eventable_id', '=', 'rehearsals.id')
                     ->where('events.eventable_type', '=', 'App\\Models\\Rehearsal');
            })
            ->whereIn('rehearsals.band_id', $bandIds)
            ->where('events.date', '>=', $today)
            ->select([
                'events.id',
                'events.title',
                'events.date',
                'events.time',
                'events.additional_data',
                'rehearsals.additional_data as rehearsal_additional_data',
                'rehearsals.venue_name',
                'rehearsals.band_id'
            ])
            ->get();

        // Merge and sort all events
        $upcomingEvents = $bookingEvents->merge($rehearsalEvents)
            ->sortBy([
                ['date', 'asc'],
                ['time', 'asc']
            ])
            ->values();

        // Extract charts and songs from each event
        $items = [];
        foreach ($upcomingEvents as $event) {
            // Determine the source of charts/songs data
            // Rehearsals store directly in additional_data, bookings store in additional_data->performance
            $chartsSource = null;
            $songsSource = null;

            // Check if this is a rehearsal event (has rehearsal_additional_data)
            if (isset($event->rehearsal_additional_data)) {
                // Decode JSON string if needed (when data comes from join query)
                $rehearsalData = is_string($event->rehearsal_additional_data)
                    ? json_decode($event->rehearsal_additional_data)
                    : $event->rehearsal_additional_data;

                $chartsSource = $rehearsalData->charts ?? null;
                $songsSource = $rehearsalData->songs ?? null;
            }
            // Otherwise check booking event structure (additional_data->performance)
            elseif (isset($event->additional_data->performance)) {
                $chartsSource = $event->additional_data->performance->charts ?? null;
                $songsSource = $event->additional_data->performance->songs ?? null;
            }

            // Extract charts
            if ($chartsSource && is_array($chartsSource)) {
                foreach ($chartsSource as $chart) {
                    $items[] = [
                        'type' => 'chart',
                        'chart_id' => $chart->id ?? null,
                        'title' => $chart->title ?? 'Untitled',
                        'composer' => $chart->composer ?? null,
                        'url' => null,
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'event_date' => $event->date->format('Y-m-d'),
                        'event_time' => $event->time,
                        'venue_name' => $event->venue_name,
                        'band_id' => $event->band_id,
                    ];
                }
            }

            // Extract songs (links)
            if ($songsSource && is_array($songsSource)) {
                foreach ($songsSource as $song) {
                    $items[] = [
                        'type' => 'song',
                        'chart_id' => null,
                        'title' => $song->title ?? 'Untitled',
                        'composer' => null,
                        'url' => $song->url ?? null,
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'event_date' => $event->date->format('Y-m-d'),
                        'event_time' => $event->time,
                        'venue_name' => $event->venue_name,
                        'band_id' => $event->band_id,
                    ];
                }
            }
        }

        return collect($items);
    }
}
