<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\Events;
use App\Models\Rehearsal;
use App\Models\EventTypes;
use App\Models\RehearsalSchedule;
use App\Services\MediaLibraryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RehearsalController extends Controller
{
    /**
     * Display a listing of rehearsals for a schedule
     */
    public function index(Bands $band, RehearsalSchedule $rehearsalSchedule): Response
    {
        $userCan = Auth::user()->canRead('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized access to rehearsals');
        }

        $rehearsals = $rehearsalSchedule->rehearsals()
            ->with(['events', 'associations.associable.events'])
            ->get();

        return Inertia::render('Rehearsals/RehearsalList', [
            'band' => $band,
            'schedule' => $rehearsalSchedule,
            'rehearsals' => $rehearsals,
            'canWrite' => Auth::user()->canWrite('rehearsals', $band->id),
        ]);
    }

    /**
     * Show the form for creating a new rehearsal
     */
    public function create(Bands $band, RehearsalSchedule $rehearsalSchedule): Response
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to create rehearsals');
        }

        $eventTypes = EventTypes::all();
        
        // Get upcoming events from bookings for association
        $upcomingEvents = Events::join('bookings', 'events.eventable_id', '=', 'bookings.id')
            ->where('events.eventable_type', 'App\\Models\\Bookings')
            ->where('bookings.band_id', $band->id)
            ->where('events.date', '>=', Carbon::now())
            ->where('bookings.status', '!=', 'cancelled')
            ->orderBy('events.date', 'asc')
            ->select([
                'events.id',
                'events.title',
                'events.date',
                'bookings.name as booking_name',
                'bookings.venue_name',
                'events.notes'
            ])
            ->get();

        return Inertia::render('Rehearsals/RehearsalForm', [
            'band' => $band,
            'schedule' => $rehearsalSchedule,
            'rehearsal' => null,
            'eventTypes' => $eventTypes,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    /**
     * Store a newly created rehearsal with an event
     */
    public function store(Request $request, Bands $band, RehearsalSchedule $rehearsalSchedule)
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to create rehearsals');
        }

        $validated = $request->validate([
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'additional_data' => 'nullable|array',
            'is_cancelled' => 'nullable|boolean',
            // Event data
            'event_title' => 'required|string|max:255',
            'event_type_id' => 'nullable|exists:event_types,id',
            'event_date' => 'required|date',
            'event_time' => 'required|date_format:H:i',
            'event_notes' => 'nullable|string',
            'event_additional_data' => 'nullable|array',
            // Associations
            'associated_events' => 'nullable|array',
            'associated_events.*' => 'exists:events,id',
        ]);

        // Get or set the Rehearsal event type ID
        if (isset($validated['event_type_id'])) {
            $eventTypeId = $validated['event_type_id'];
        } else {
            $rehearsalType = EventTypes::where('name', 'Rehearsal')->first();
            if (!$rehearsalType) {
                abort(500, 'Rehearsal event type not found. Please run database seeders.');
            }
            $eventTypeId = $rehearsalType->id;
        }

        // Create the event first
        $rehearsal = $rehearsalSchedule->rehearsals()->create([
            'band_id' => $band->id,
            'venue_name' => $validated['venue_name'] ?? null,
            'venue_address' => $validated['venue_address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'additional_data' => $validated['additional_data'] ?? null,
            'is_cancelled' => $validated['is_cancelled'] ?? false,
        ]);

        // Create the event for this rehearsal
        $event = $rehearsal->events()->create([
            'title' => $validated['event_title'],
            'event_type_id' => $eventTypeId,
            'date' => Carbon::parse($validated['event_date']),
            'time' => $validated['event_time'],
            'notes' => $validated['event_notes'] ?? null,
            'additional_data' => $validated['event_additional_data'] ?? null,
            'key' => Str::uuid(),
        ]);

        // Auto-create media folder for event if portal access is enabled
        if ($event->enable_portal_media_access) {
            $mediaService = app(MediaLibraryService::class);
            $folderPath = $mediaService->createEventFolder($event);
            $event->update(['media_folder_path' => $folderPath]);
        }

        // Associate with events if provided
        if (!empty($validated['associated_events'])) {
            foreach ($validated['associated_events'] as $eventId) {
                $rehearsal->associations()->create([
                    'associable_type' => 'App\\Models\\Events',
                    'associable_id' => $eventId,
                ]);
            }
        }

        // Google Calendar sync is handled automatically by EventObserver

        return redirect()
            ->route('dashboard')
            ->with('success', 'Rehearsal created successfully');
    }

    /**
     * Display the specified rehearsal
     */
    public function show(Bands $band, RehearsalSchedule $rehearsalSchedule, Rehearsal $rehearsal): Response
    {
        $userCan = Auth::user()->canRead('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized access to rehearsal');
        }

        $rehearsal->load(['events', 'associations.associable', 'rehearsalSchedule']);

        return Inertia::render('Rehearsals/RehearsalDetail', [
            'band' => $band,
            'schedule' => $rehearsalSchedule,
            'rehearsal' => $rehearsal,
            'canWrite' => Auth::user()->canWrite('rehearsals', $band->id),
        ]);
    }

    /**
     * Show the form for editing the rehearsal
     */
    public function edit(Bands $band, RehearsalSchedule $rehearsalSchedule, Rehearsal $rehearsal): Response
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to edit rehearsal');
        }

        $eventTypes = EventTypes::all();
        $rehearsal->load(['events', 'associations.associable']);
        
        // Get upcoming events from bookings for association
        $upcomingEvents = Events::join('bookings', 'events.eventable_id', '=', 'bookings.id')
            ->where('events.eventable_type', 'App\\Models\\Bookings')
            ->where('bookings.band_id', $band->id)
            ->where('events.date', '>=', Carbon::now())
            ->where('bookings.status', '!=', 'cancelled')
            ->orderBy('events.date', 'asc')
            ->select([
                'events.id',
                'events.title',
                'events.date',
                'bookings.name as booking_name',
                'bookings.venue_name',
                'events.notes'
            ])
            ->get();

        return Inertia::render('Rehearsals/RehearsalForm', [
            'band' => $band,
            'schedule' => $rehearsalSchedule,
            'rehearsal' => $rehearsal,
            'eventTypes' => $eventTypes,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    /**
     * Update the specified rehearsal
     */
    public function update(Request $request, Bands $band, RehearsalSchedule $rehearsalSchedule, Rehearsal $rehearsal)
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to update rehearsal');
        }

        $validated = $request->validate([
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'additional_data' => 'nullable|array',
            'is_cancelled' => 'nullable|boolean',
            // Event data
            'event_title' => 'required|string|max:255',
            'event_type_id' => 'nullable|exists:event_types,id',
            'event_date' => 'required|date',
            'event_time' => 'required|date_format:H:i',
            'event_notes' => 'nullable|string',
            'event_additional_data' => 'nullable|array',
            // Associations
            'associated_events' => 'nullable|array',
            'associated_events.*' => 'exists:events,id',
        ]);

        // Get or set the Rehearsal event type ID
        $eventTypeId = $validated['event_type_id'] ?? EventTypes::where('name', 'Rehearsal')->first()->id;

        // Update rehearsal
        $rehearsal->update([
            'venue_name' => $validated['venue_name'] ?? null,
            'venue_address' => $validated['venue_address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'additional_data' => $validated['additional_data'] ?? null,
            'is_cancelled' => $validated['is_cancelled'] ?? false,
        ]);

        // Update or create event
        $event = $rehearsal->events()->first();
        if ($event) {
            $event->update([
                'title' => $validated['event_title'],
                'event_type_id' => $eventTypeId,
                'date' => Carbon::parse($validated['event_date']),
                'time' => $validated['event_time'],
                'notes' => $validated['event_notes'] ?? null,
                'additional_data' => $validated['event_additional_data'] ?? null,
            ]);

            // Create folder if event doesn't have one and portal access is enabled
            if ($event->enable_portal_media_access && !$event->media_folder_path) {
                $mediaService = app(MediaLibraryService::class);
                $folderPath = $mediaService->createEventFolder($event);
                $event->update(['media_folder_path' => $folderPath]);
            }
        } else {
            $event = $rehearsal->events()->create([
                'title' => $validated['event_title'],
                'event_type_id' => $eventTypeId,
                'date' => Carbon::parse($validated['event_date']),
                'time' => $validated['event_time'],
                'notes' => $validated['event_notes'] ?? null,
                'additional_data' => $validated['event_additional_data'] ?? null,
                'key' => Str::uuid(),
            ]);

            // Auto-create media folder for new event if portal access is enabled
            if ($event->enable_portal_media_access) {
                $mediaService = app(MediaLibraryService::class);
                $folderPath = $mediaService->createEventFolder($event);
                $event->update(['media_folder_path' => $folderPath]);
            }
        }

        // Update associations
        $rehearsal->associations()->delete();
        if (!empty($validated['associated_events'])) {
            foreach ($validated['associated_events'] as $eventId) {
                $rehearsal->associations()->create([
                    'associable_type' => 'App\\Models\\Events',
                    'associable_id' => $eventId,
                ]);
            }
        }

        // Google Calendar sync is handled automatically by EventObserver

        return redirect()
            ->route('dashboard')
            ->with('success', 'Rehearsal updated successfully');
    }

    /**
     * Remove the specified rehearsal
     */
    public function destroy(Bands $band, RehearsalSchedule $rehearsalSchedule, Rehearsal $rehearsal)
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to delete rehearsal');
        }

                // Google Calendar deletion is handled automatically by EventObserver when events are deleted
        $rehearsal->delete(); // This will cascade delete the events, triggering the observer

        return redirect()
            ->route('dashboard')
            ->with('success', 'Rehearsal deleted successfully');
    }

    /**
     * Toggle the cancelled status of a rehearsal
     */
    public function toggleCancelled(Bands $band, RehearsalSchedule $rehearsalSchedule, Rehearsal $rehearsal)
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to cancel/uncancel rehearsal');
        }

        $rehearsal->is_cancelled = !$rehearsal->is_cancelled;
        $rehearsal->save();

        // Google Calendar sync is handled automatically by EventObserver when event is updated

        $status = $rehearsal->is_cancelled ? 'cancelled' : 'reactivated';
        
        return back()->with('success', "Rehearsal {$status} successfully");
    }

    /**
     * Get rehearsal data for API (used by dashboard modal)
     */
    public function getRehearsalData($rehearsalId)
    {
        $rehearsal = Rehearsal::with([
            'events', 
            'associations.associable',
            'rehearsalSchedule'
        ])->findOrFail($rehearsalId);
        
        $band = $rehearsal->rehearsalSchedule->band;
        
        // Check permissions
        $userCan = Auth::user()->canRead('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized access to rehearsal');
        }

        $eventTypes = EventTypes::all();
        
        // Get upcoming events from bookings for association
        $upcomingEvents = Events::join('bookings', 'events.eventable_id', '=', 'bookings.id')
            ->where('events.eventable_type', 'App\\Models\\Bookings')
            ->where('bookings.band_id', $band->id)
            ->where('events.date', '>=', Carbon::now())
            ->where('bookings.status', '!=', 'cancelled')
            ->orderBy('events.date', 'asc')
            ->select([
                'events.id',
                'events.title',
                'events.date',
                'bookings.name as booking_name',
                'bookings.venue_name',
                'events.notes'
            ])
            ->get();

        return response()->json([
            'rehearsal' => $rehearsal,
            'schedule' => $rehearsal->rehearsalSchedule,
            'band' => $band,
            'eventTypes' => $eventTypes,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    /**
     * Get rehearsal schedule data for creating a new rehearsal (used by dashboard modal for virtual rehearsals)
     */
    public function getRehearsalScheduleData($rehearsalScheduleId, $bandId)
    {
        $schedule = RehearsalSchedule::findOrFail($rehearsalScheduleId);
        $band = Bands::findOrFail($bandId);
        
        // Check permissions
        $userCan = Auth::user()->canRead('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized access to rehearsal schedule');
        }

        // Verify the schedule belongs to this band
        if ($schedule->band_id != $band->id) {
            abort(403, 'Rehearsal schedule does not belong to this band');
        }

        $eventTypes = EventTypes::all();
        
        // Get upcoming events from bookings for association
        $upcomingEvents = Events::join('bookings', 'events.eventable_id', '=', 'bookings.id')
            ->where('events.eventable_type', 'App\\Models\\Bookings')
            ->where('bookings.band_id', $band->id)
            ->where('events.date', '>=', Carbon::now())
            ->where('bookings.status', '!=', 'cancelled')
            ->orderBy('events.date', 'asc')
            ->select([
                'events.id',
                'events.title',
                'events.date',
                'bookings.name as booking_name',
                'bookings.venue_name',
                'events.notes'
            ])
            ->get();

        return response()->json([
            'schedule' => $schedule,
            'band' => $band,
            'eventTypes' => $eventTypes,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
