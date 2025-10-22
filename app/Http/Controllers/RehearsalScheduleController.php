<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\RehearsalSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RehearsalScheduleController extends Controller
{
    /**
     * Display a listing of rehearsal schedules for a band or all bands
     */
    public function index(Bands $band = null): Response
    {
        // If no band specified, show all bands with their schedules
        if (!$band) {
            $userBands = Auth::user()->bands();
            
            // Filter bands where user can read rehearsals
            $bands = $userBands->filter(function ($b) {
                return Auth::user()->canRead('rehearsals', $b->id);
            })->map(function ($b) {
                $b->load([
                    'rehearsalSchedules' => function ($query) {
                        $query->with(['rehearsals' => function ($q) {
                            $q->with('events')->latest();
                        }])->withCount('rehearsals');
                    }
                ]);
                $b->canWrite = Auth::user()->canWrite('rehearsals', $b->id);
                return $b;
            });

            return Inertia::render('Rehearsals/Index', [
                'bands' => $bands,
                'band' => null,
                'schedules' => [],
            ]);
        }

        // Single band view
        $userCan = Auth::user()->canRead('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized access to rehearsal schedules');
        }

        $schedules = $band->rehearsalSchedules()
            ->with(['rehearsals' => function ($query) {
                $query->with('events')->latest();
            }])
            ->withCount('rehearsals')
            ->get();

        return Inertia::render('Rehearsals/Index', [
            'band' => $band,
            'bands' => collect([]),
            'schedules' => $schedules,
            'canWrite' => Auth::user()->canWrite('rehearsals', $band->id),
        ]);
    }

    /**
     * Show the form for creating a new rehearsal schedule
     */
    public function create(Bands $band): Response
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to create rehearsal schedules');
        }

        return Inertia::render('Rehearsals/ScheduleForm', [
            'band' => $band,
            'schedule' => null,
        ]);
    }

    /**
     * Store a newly created rehearsal schedule
     */
    public function store(Request $request, Bands $band)
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to create rehearsal schedules');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly,weekday,custom',
            'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'selected_days' => 'nullable|array',
            'selected_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'monthly_pattern' => 'nullable|in:day_of_month,first,second,third,fourth,last',
            'monthly_weekday' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'default_time' => 'nullable|date_format:H:i:s,H:i',
            'location_name' => 'nullable|string|max:255',
            'location_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $schedule = $band->rehearsalSchedules()->create($validated);

        return redirect()
            ->route('rehearsal-schedules.show', ['band' => $band->id, 'rehearsal_schedule' => $schedule->id])
            ->with('success', 'Rehearsal schedule created successfully');
    }

    /**
     * Display the specified rehearsal schedule
     */
    public function show(Bands $band, RehearsalSchedule $rehearsalSchedule): Response
    {
        $userCan = Auth::user()->canRead('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized access to rehearsal schedule');
        }

        $rehearsalSchedule->load([
            'rehearsals' => function ($query) {
                $query->with(['events' => function ($q) {
                    $q->orderBy('date', 'desc');
                }])->latest();
            }
        ]);

        return Inertia::render('Rehearsals/ScheduleDetail', [
            'band' => $band,
            'schedule' => $rehearsalSchedule,
            'canWrite' => Auth::user()->canWrite('rehearsals', $band->id),
        ]);
    }

    /**
     * Show the form for editing the rehearsal schedule
     */
    public function edit(Bands $band, RehearsalSchedule $rehearsalSchedule): Response
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to edit rehearsal schedule');
        }

        return Inertia::render('Rehearsals/ScheduleForm', [
            'band' => $band,
            'schedule' => $rehearsalSchedule,
        ]);
    }

    /**
     * Update the specified rehearsal schedule
     */
    public function update(Request $request, Bands $band, RehearsalSchedule $rehearsalSchedule)
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to update rehearsal schedule');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly,weekday,custom',
            'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'selected_days' => 'nullable|array',
            'selected_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'monthly_pattern' => 'nullable|in:day_of_month,first,second,third,fourth,last',
            'monthly_weekday' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'default_time' => 'nullable|date_format:H:i:s,H:i',
            'location_name' => 'nullable|string|max:255',
            'location_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $rehearsalSchedule->update($validated);

        return redirect()
            ->route('rehearsal-schedules.show', ['band' => $band->id, 'rehearsal_schedule' => $rehearsalSchedule->id])
            ->with('success', 'Rehearsal schedule updated successfully');
    }

    /**
     * Remove the specified rehearsal schedule
     */
    public function destroy(Bands $band, RehearsalSchedule $rehearsalSchedule)
    {
        $userCan = Auth::user()->canWrite('rehearsals', $band->id);
        
        if (!$userCan) {
            abort(403, 'Unauthorized to delete rehearsal schedule');
        }

        $rehearsalSchedule->delete();

        return redirect()
            ->route('rehearsal-schedules.index', ['band' => $band->id])
            ->with('success', 'Rehearsal schedule deleted successfully');
    }
}
