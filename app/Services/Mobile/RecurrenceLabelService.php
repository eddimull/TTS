<?php

namespace App\Services\Mobile;

use App\Models\RehearsalSchedule;
use Illuminate\Support\Carbon;

/**
 * Formats a rehearsal schedule's recurrence columns into a human-readable
 * label, e.g. "Every Tuesday at 7:00 PM". Pure; no DB access.
 */
class RecurrenceLabelService
{
    public function format(RehearsalSchedule $schedule): string
    {
        $time = $this->timeSuffix($schedule);

        return match ($schedule->frequency) {
            'daily'   => 'Every day' . $time,
            'weekday' => 'Weekdays' . $time,
            'weekly'  => $this->weeklyLabel($schedule, $time),
            'monthly' => $this->monthlyLabel($schedule, $time),
            default   => 'Custom schedule',
        };
    }

    private function timeSuffix(RehearsalSchedule $schedule): string
    {
        if (! $schedule->default_time) {
            return '';
        }

        return ' at ' . Carbon::parse($schedule->default_time)->format('g:i A');
    }

    private function weeklyLabel(RehearsalSchedule $schedule, string $time): string
    {
        $days = $schedule->selected_days
            ?: ($schedule->day_of_week ? [$schedule->day_of_week] : []);

        if (empty($days)) {
            return 'Weekly' . $time;
        }

        $names = array_map(fn ($d) => ucfirst(strtolower($d)), $days);

        return 'Every ' . $this->joinNames($names) . $time;
    }

    private function monthlyLabel(RehearsalSchedule $schedule, string $time): string
    {
        if ($schedule->monthly_pattern === 'day_of_month' && $schedule->day_of_month) {
            return 'Monthly on the ' . $this->ordinal((int) $schedule->day_of_month) . $time;
        }

        if ($schedule->monthly_pattern && $schedule->monthly_weekday) {
            return ucfirst($schedule->monthly_pattern) . ' '
                . ucfirst(strtolower($schedule->monthly_weekday))
                . ' of each month' . $time;
        }

        return 'Monthly' . $time;
    }

    private function joinNames(array $names): string
    {
        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' & ' . $last;
    }

    private function ordinal(int $n): string
    {
        if (in_array($n % 100, [11, 12, 13], true)) {
            return $n . 'th';
        }

        return $n . match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
