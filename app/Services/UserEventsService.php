<?php

namespace App\Services;

use App\Models\BandEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UserEventsService
{
    public function getEvents()
    {
        $afterDate = Carbon::now()->subHours(72);
        $events = Auth::user()->getEventsAttribute($afterDate, true); //why isn't the laravel magic happening where I can just specify 'events'
        
        // Get user's band IDs
        $user = Auth::user();
        $ownedBands = $user->bandOwner()->pluck('bands.id')->toArray();
        $memberBands = $user->bandMember()->pluck('bands.id')->toArray();
        $bandIds = array_merge($ownedBands, $memberBands);
        
        // Generate virtual rehearsal events from schedules
        if (!empty($bandIds)) {
            $rehearsalService = new RehearsalScheduleService();
            $virtualRehearsals = $rehearsalService->generateUpcomingRehearsals($bandIds, $afterDate);
            
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
