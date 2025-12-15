<?php

namespace App\Services;

use App\Models\BandEvents;
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
                        
                        return [
                            'id' => $attachment->id,
                            'filename' => $attachment->filename,
                            'mime_type' => $attachment->mime_type,
                            'file_size' => $attachment->file_size,
                            'formatted_size' => round($bytes, 2) . ' ' . $units[$i],
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
}
