<?php

namespace App\Services;

use App\Models\BandEvents;
use App\Models\Events;
use App\Models\LiveSetlistSession;
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

        $user = Auth::user();

        // Check if user is ONLY a sub (has sub role and no band ownership/membership)
        // Use cached relationship properties to avoid duplicate queries within the same request
        $isSub = $user->hasRole('sub');
        $ownedBands = $user->bandOwner->pluck('id')->toArray();
        $memberBands = $user->bandMember->pluck('id')->toArray();
        $hasBandAccess = !empty($ownedBands) || !empty($memberBands);

        // If user is only a sub, show only events they're invited to
        if ($isSub && !$hasBandAccess) {
            return $this->getSubEvents($afterDate, $beforeDate, $limit);
        }

        $events = $user->getEventsAttribute($afterDate, true, $beforeDate, $limit); //why isn't the laravel magic happening where I can just specify 'events'
        
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
        
        // Reuse band IDs already queried above
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

        // Attach active live session info so the card footer can show a "Join" link
        $eventIds = $events->pluck('id')->filter()->unique()->values()->all();
        if (!empty($eventIds)) {
            $liveSessions = LiveSetlistSession::whereIn('event_id', $eventIds)
                ->whereIn('status', ['active', 'paused'])
                ->get()
                ->keyBy('event_id');

            $events = $events->map(function ($event) use ($liveSessions) {
                $id = is_array($event) ? ($event['id'] ?? null) : ($event->id ?? null);
                if ($id && $liveSessions->has($id)) {
                    if (is_array($event)) {
                        $event['live_session_id'] = $liveSessions[$id]->id;
                    } else {
                        $event->live_session_id = $liveSessions[$id]->id;
                    }
                }
                return $event;
            });
        }

        // Attach roster member counts
        if (!empty($eventIds)) {
            $rosterCounts = DB::table('event_members')
                ->whereIn('event_id', $eventIds)
                ->whereNull('deleted_at')
                ->selectRaw("event_id, SUM(CASE WHEN roster_member_id IS NOT NULL AND attendance_status NOT IN ('absent', 'excused') THEN 1 ELSE 0 END) as roster_count, SUM(CASE WHEN roster_member_id IS NULL THEN 1 ELSE 0 END) as sub_count, SUM(CASE WHEN roster_member_id IS NOT NULL AND attendance_status IN ('absent', 'excused') THEN 1 ELSE 0 END) as absent_count")
                ->groupBy('event_id')
                ->get()
                ->keyBy('event_id');

            $events = $events->map(function ($event) use ($rosterCounts) {
                $id = is_array($event) ? ($event['id'] ?? null) : ($event->id ?? null);
                $counts = $id ? $rosterCounts->get($id) : null;
                if (is_array($event)) {
                    $event['roster_count'] = $counts ? (int) $counts->roster_count : 0;
                    $event['sub_count'] = $counts ? (int) $counts->sub_count : 0;
                    $event['absent_count'] = $counts ? (int) $counts->absent_count : 0;
                } else {
                    $event->roster_count = $counts ? (int) $counts->roster_count : 0;
                    $event->sub_count = $counts ? (int) $counts->sub_count : 0;
                    $event->absent_count = $counts ? (int) $counts->absent_count : 0;
                }
                return $event;
            });
        }

        return $events;
    }

    public function getUpcomingCharts()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // Get all user's band IDs (use cached relationship properties to avoid duplicate queries)
        $ownedBands = $user->bandOwner->pluck('id')->toArray();
        $memberBands = $user->bandMember->pluck('id')->toArray();
        $bandIds = array_merge($ownedBands, $memberBands);

        // Check if user is ONLY a sub
        $isSub = $user->hasRole('sub');
        $hasBandAccess = !empty($bandIds);

        // If user is only a sub, show charts only from events they're invited to
        if ($isSub && !$hasBandAccess) {
            return $this->getSubUpcomingCharts($today);
        }

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

    /**
     * Get events for users who are only subs
     */
    protected function getSubEvents($afterDate = null, $beforeDate = null, $limit = null)
    {
        $user = Auth::user();

        // Get event IDs the user is subbing for
        $eventSubsQuery = DB::table('event_subs')
            ->where('user_id', $user->id)
            ->where('pending', false); // Only accepted invitations

        if ($afterDate) {
            $eventSubsQuery->join('events', 'event_subs.event_id', '=', 'events.id')
                ->where('events.date', '>=', $afterDate->toDateString());
        }

        if ($beforeDate) {
            if (!$afterDate) {
                $eventSubsQuery->join('events', 'event_subs.event_id', '=', 'events.id');
            }
            $eventSubsQuery->where('events.date', '<', $beforeDate->toDateString());
        }

        $eventIds = $eventSubsQuery->pluck('event_id')->toArray();

        if (empty($eventIds)) {
            return collect();
        }

        // Get events the user is subbing for
        $events = Events::whereIn('id', $eventIds)
            ->with(['eventable'])
            ->get();

        // Load attachments for all events
        $attachmentsData = DB::table('event_attachments')
            ->whereIn('event_id', $eventIds)
            ->get()
            ->groupBy('event_id');

        // Add attachments to events and flatten venue fields from eventable
        $events = $events->map(function($event) use ($attachmentsData) {
            $eventId = $event->id;

            if (isset($attachmentsData[$eventId])) {
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

                $event->attachments = $attachments;
            }

            // Flatten venue fields so the dashboard card (event.venue_name) works like regular events
            // Also suppress payment appends on Bookings — not needed on the dashboard and cause N+1 queries
            if ($event->eventable) {
                if ($event->eventable_type === 'App\\Models\\Bookings') {
                    $event->eventable->makeHidden(['amount_paid', 'amount_due', 'is_paid']);
                }
                $event->venue_name = $event->eventable->venue_name ?? null;
                $event->venue_address = $event->eventable->venue_address ?? null;
                $event->band_id = $event->eventable->band_id ?? null;
            }

            return $event;
        });

        // Load contacts for booking events
        $bookingIds = $events->where('eventable_type', 'App\\Models\\Bookings')
            ->pluck('eventable_id')
            ->filter()
            ->unique()
            ->toArray();

        if (!empty($bookingIds)) {
            $contacts = DB::table('booking_contacts')
                ->join('contacts', 'booking_contacts.contact_id', '=', 'contacts.id')
                ->whereIn('booking_id', $bookingIds)
                ->get()
                ->groupBy('booking_id');

            $events = $events->map(function ($event) use ($contacts) {
                if ($event->eventable_type === 'App\\Models\\Bookings') {
                    $event->contacts = $contacts->get($event->eventable_id, collect());
                }
                return $event;
            });
        }

        // Sort by date
        $events = $events->sortBy('date')->values();

        return $events;
    }

    /**
     * Get upcoming charts for users who are only subs
     */
    protected function getSubUpcomingCharts($today)
    {
        $user = Auth::user();

        // Get event IDs the user is subbing for (accepted invitations only)
        $eventIds = DB::table('event_subs')
            ->join('events', 'event_subs.event_id', '=', 'events.id')
            ->where('event_subs.user_id', $user->id)
            ->where('event_subs.pending', false)
            ->where('events.date', '>=', $today)
            ->pluck('event_subs.event_id')
            ->toArray();

        if (empty($eventIds)) {
            return collect();
        }

        // Query upcoming booking events the user is subbing for
        $bookingEvents = Events::join('bookings', function($join) {
                $join->on('events.eventable_id', '=', 'bookings.id')
                     ->where('events.eventable_type', '=', 'App\\Models\\Bookings');
            })
            ->whereIn('events.id', $eventIds)
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

        // Query upcoming rehearsal events the user is subbing for
        $rehearsalEvents = Events::join('rehearsals', function($join) {
                $join->on('events.eventable_id', '=', 'rehearsals.id')
                     ->where('events.eventable_type', '=', 'App\\Models\\Rehearsal');
            })
            ->whereIn('events.id', $eventIds)
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

        // Extract charts and songs from each event (same logic as parent method)
        $items = [];
        foreach ($upcomingEvents as $event) {
            $chartsSource = null;
            $songsSource = null;

            if (isset($event->rehearsal_additional_data)) {
                $rehearsalData = is_string($event->rehearsal_additional_data)
                    ? json_decode($event->rehearsal_additional_data)
                    : $event->rehearsal_additional_data;

                $chartsSource = $rehearsalData->charts ?? null;
                $songsSource = $rehearsalData->songs ?? null;
            }
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

            // Extract songs
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
