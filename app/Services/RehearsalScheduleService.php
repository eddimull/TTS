<?php

namespace App\Services;

use App\Models\RehearsalSchedule;
use App\Models\Rehearsal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RehearsalScheduleService
{
    /**
     * Generate upcoming rehearsal instances from active schedules for given band IDs
     * This creates virtual rehearsal event objects based on the schedule's frequency
     * 
     * @param array $bandIds Array of band IDs to generate rehearsals for
     * @param Carbon|null $startDate Starting date (default: now)
     * @param int $weeksAhead Number of weeks to look ahead (default: 12)
     * @return Collection Collection of virtual event objects
     */
    public function generateUpcomingRehearsals(array $bandIds, Carbon $startDate = null, int $weeksAhead = 12): Collection
    {
        if (empty($bandIds)) {
            return collect();
        }

        $startDate = $startDate ?? Carbon::now();
        $endDate = $startDate->copy()->addWeeks($weeksAhead);

        // Get all active rehearsal schedules for the user's bands
        $schedules = RehearsalSchedule::whereIn('band_id', $bandIds)
            ->where('active', true)
            ->get();

        $generatedRehearsals = collect();

        foreach ($schedules as $schedule) {
            $instances = $this->generateInstancesForSchedule($schedule, $startDate, $endDate);
            $generatedRehearsals = $generatedRehearsals->merge($instances);
        }

        return $generatedRehearsals;
    }

    /**
     * Generate rehearsal instances for a specific schedule
     * 
     * @param RehearsalSchedule $schedule
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection
     */
    protected function generateInstancesForSchedule(RehearsalSchedule $schedule, Carbon $startDate, Carbon $endDate): Collection
    {
        // Get existing rehearsals for this schedule to avoid duplicates
        $existingRehearsals = Rehearsal::where('rehearsal_schedule_id', $schedule->id)
            ->whereHas('events', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
            })
            ->with('events')
            ->get();

        $existingDates = $existingRehearsals->flatMap(function ($rehearsal) {
            return $rehearsal->events->pluck('date');
        })->map(function ($date) {
            // Handle both Carbon instances and string dates
            if ($date instanceof Carbon) {
                return $date->toDateString();
            }
            return Carbon::parse($date)->toDateString();
        })->unique()->values()->toArray();

        $instances = collect();
        $currentDate = $startDate->copy();

        // Generate instances based on frequency
        switch ($schedule->frequency) {
            case 'daily':
                $instances = $this->generateDailyInstances($schedule, $currentDate, $endDate, $existingDates);
                break;
            case 'weekly':
                $instances = $this->generateWeeklyInstances($schedule, $currentDate, $endDate, $existingDates);
                break;
            case 'monthly':
                $instances = $this->generateMonthlyInstances($schedule, $currentDate, $endDate, $existingDates);
                break;
            case 'weekday':
                $instances = $this->generateWeekdayInstances($schedule, $currentDate, $endDate, $existingDates);
                break;
            case 'custom':
                // For custom frequency, don't auto-generate - rely on manually created rehearsals
                break;
        }

        return $instances;
    }

    /**
     * Generate daily rehearsal instances
     */
    protected function generateDailyInstances(RehearsalSchedule $schedule, Carbon $startDate, Carbon $endDate, array $existingDates): Collection
    {
        $instances = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->toDateString();
            
            if (!in_array($dateString, $existingDates)) {
                $instances->push($this->createVirtualEvent($schedule, $currentDate));
            }
            
            $currentDate->addDay();
        }

        return $instances;
    }

    /**
     * Generate weekday (Mon-Fri) rehearsal instances
     */
    protected function generateWeekdayInstances(RehearsalSchedule $schedule, Carbon $startDate, Carbon $endDate, array $existingDates): Collection
    {
        $instances = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Only include Monday through Friday
            if ($currentDate->isWeekday()) {
                $dateString = $currentDate->toDateString();
                
                if (!in_array($dateString, $existingDates)) {
                    $instances->push($this->createVirtualEvent($schedule, $currentDate));
                }
            }
            
            $currentDate->addDay();
        }

        return $instances;
    }

    /**
     * Generate weekly rehearsal instances
     */
    protected function generateWeeklyInstances(RehearsalSchedule $schedule, Carbon $startDate, Carbon $endDate, array $existingDates): Collection
    {
        $instances = collect();
        
        // Check if using new selected_days array or legacy day_of_week
        $selectedDays = $schedule->selected_days ?? [];
        
        // If no selected_days but has day_of_week (legacy), use that
        if (empty($selectedDays) && $schedule->day_of_week) {
            $selectedDays = [$schedule->day_of_week];
        }
        
        if (empty($selectedDays)) {
            return $instances;
        }

        // Convert day names to numbers
        $targetDays = array_map(function($day) {
            return $this->convertDayNameToNumber($day);
        }, $selectedDays);

        $currentDate = $startDate->copy();

        // Generate instances for all selected days
        while ($currentDate->lte($endDate)) {
            // Check if current day matches any selected days
            if (in_array($currentDate->dayOfWeek, $targetDays)) {
                $dateString = $currentDate->toDateString();
                
                // Only create if not already exists
                if (!in_array($dateString, $existingDates)) {
                    $instances->push($this->createVirtualEvent($schedule, $currentDate));
                }
            }
            
            $currentDate->addDay();
        }

        return $instances;
    }

    /**
     * Generate monthly rehearsal instances
     */
    protected function generateMonthlyInstances(RehearsalSchedule $schedule, Carbon $startDate, Carbon $endDate, array $existingDates): Collection
    {
        $instances = collect();
        
        // Determine if using pattern-based or day-of-month
        $pattern = $schedule->monthly_pattern;
        
        if (!$pattern) {
            return $instances;
        }

        $currentDate = $startDate->copy()->startOfMonth();

        while ($currentDate->lte($endDate)) {
            $monthDate = null;

            if ($pattern === 'day_of_month') {
                // Specific day of month
                $dayOfMonth = $schedule->day_of_month;
                if ($dayOfMonth && $dayOfMonth <= $currentDate->daysInMonth) {
                    $monthDate = $currentDate->copy()->day($dayOfMonth);
                }
            } else {
                // Pattern-based: first, second, third, fourth, last + weekday
                if (!$schedule->monthly_weekday) {
                    $currentDate->addMonth();
                    continue;
                }

                $targetDayOfWeek = $this->convertDayNameToNumber($schedule->monthly_weekday);
                $monthDate = $this->findPatternDayInMonth($currentDate, $pattern, $targetDayOfWeek);
            }

            if ($monthDate && $monthDate->gte($startDate) && $monthDate->lte($endDate)) {
                $dateString = $monthDate->toDateString();
                
                if (!in_array($dateString, $existingDates)) {
                    $instances->push($this->createVirtualEvent($schedule, $monthDate));
                }
            }

            $currentDate->addMonth();
        }

        return $instances;
    }

    /**
     * Find a specific pattern day in a month (first/second/third/fourth/last weekday)
     */
    protected function findPatternDayInMonth(Carbon $monthStart, string $pattern, int $targetDayOfWeek): ?Carbon
    {
        $date = $monthStart->copy()->startOfMonth();
        
        if ($pattern === 'last') {
            // Find last occurrence of the weekday
            $date = $monthStart->copy()->endOfMonth();
            while ($date->dayOfWeek !== $targetDayOfWeek) {
                $date->subDay();
                if ($date->month !== $monthStart->month) {
                    return null;
                }
            }
            return $date;
        }

        // Find first, second, third, or fourth occurrence
        $occurrenceMap = [
            'first' => 1,
            'second' => 2,
            'third' => 3,
            'fourth' => 4,
        ];

        $targetOccurrence = $occurrenceMap[$pattern] ?? 1;
        $occurrenceCount = 0;

        while ($date->month === $monthStart->month) {
            if ($date->dayOfWeek === $targetDayOfWeek) {
                $occurrenceCount++;
                if ($occurrenceCount === $targetOccurrence) {
                    return $date;
                }
            }
            $date->addDay();
        }

        return null;
    }

    /**
     * Convert day name string to Carbon day of week number
     * @param string|int $day Day name ('monday', 'tuesday', etc.) or number (0-6)
     * @return int Carbon day number (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
     */
    protected function convertDayNameToNumber($day): int
    {
        // If already a number, return it
        if (is_numeric($day)) {
            return (int) $day;
        }

        // Map day names to Carbon constants
        $dayMap = [
            'sunday' => Carbon::SUNDAY,
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
        ];

        $dayLower = strtolower($day);
        
        return $dayMap[$dayLower] ?? Carbon::MONDAY; // Default to Monday if not found
    }

    /**
     * Create a virtual event object from a rehearsal schedule
     * This mimics the structure of events returned by User->getEventsAttribute()
     */
    protected function createVirtualEvent(RehearsalSchedule $schedule, Carbon $date): array
    {
        // Parse default time if available, otherwise use a default rehearsal time
        $time = '19:00:00'; // Default to 7 PM
        if ($schedule->default_time) {
            $time = Carbon::parse($schedule->default_time)->format('H:i:s');
        }

        return [
            'id' => null, // Virtual event has no ID
            'key' => 'virtual-rehearsal-' . $schedule->id . '-' . $date->format('Y-m-d'),
            'title' => $schedule->name,
            'date' => $date->toDateString(),
            'time' => $time,
            'event_type_id' => $this->getRehearsalEventTypeId(),
            'event_type_name' => 'Rehearsal',
            'band_id' => $schedule->band_id,
            'booking_name' => 'Rehearsal: ' . $schedule->name,
            'booking_id' => null,
            'venue_name' => $schedule->location_name,
            'venue_address' => $schedule->location_address,
            'event_source' => 'rehearsal_schedule',
            'is_cancelled' => false,
            'notes' => $schedule->notes,
            'additional_data' => null,
            'is_virtual' => true, // Flag to indicate this is a generated event
            'rehearsal_schedule_id' => $schedule->id,
            'rehearsal_schedule_name' => $schedule->name,
            'eventable_id' => null, // Virtual events don't have a rehearsal yet
            'eventable_type' => null, // No eventable type yet
            'contacts' => [], // Virtual events have no contacts
        ];
    }

    /**
     * Get the event type ID for rehearsals
     * Cached to avoid multiple DB queries
     */
    protected function getRehearsalEventTypeId(): ?int
    {
        static $rehearsalTypeId = null;
        
        if ($rehearsalTypeId === null) {
            $rehearsalType = \App\Models\EventTypes::where('name', 'Rehearsal')->first();
            $rehearsalTypeId = $rehearsalType ? $rehearsalType->id : null;
        }
        
        return $rehearsalTypeId;
    }
}
