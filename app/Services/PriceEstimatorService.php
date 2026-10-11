<?php

namespace App\Services;

use App\Models\BandPricingSettings;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventTypes;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Booking price estimator.
 *
 * Hybrid model: a baseline derived from the band's own booking history
 * (normalized for season / weekday / duration and inflated to today's
 * dollars, recency-weighted), optionally overridden per event type, then
 * adjusted by band-editable multipliers and fees.
 *
 * All money values handled here are DOLLARS as floats. Booking prices are
 * read straight from the `bookings.price` column (stored as cents) rather
 * than through the Price cast, which returns a formatted string.
 */
class PriceEstimatorService
{
    public const DEFAULT_SETTINGS = [
        'season_multipliers' => [
            '1' => 1.0, '2' => 1.0, '3' => 1.1, '4' => 1.1, '5' => 1.1, '6' => 0.9,
            '7' => 0.9, '8' => 0.9, '9' => 1.0, '10' => 1.1, '11' => 1.1, '12' => 1.05,
        ],
        'day_of_week_multipliers' => [
            '0' => 0.9, '1' => 0.8, '2' => 0.8, '3' => 0.8, '4' => 0.85, '5' => 0.95, '6' => 1.0,
        ],
        'special_dates' => [
            ['date' => '12-31', 'label' => "New Year's Eve", 'multiplier' => 1.5],
        ],
        'standard_hours' => 4,
        'standard_hours_by_type' => [],
        'overtime_pct_per_hour' => 15,
        'min_duration_factor' => 0.75,
        'short_notice_days' => 30,
        'short_notice_multiplier' => 1.0,
        'annual_increase_pct' => 3,
        'free_radius_miles' => 50,
        'per_mile_rate' => 2.0,
        'lodging_threshold_miles' => 150,
        'lodging_fee' => 0,
        'add_ons' => [
            ['key' => 'production', 'label' => 'Production (sound & lights)', 'amount' => 1500],
            ['key' => 'ceremony', 'label' => 'Ceremony music', 'amount' => 750],
            ['key' => 'outdoor', 'label' => 'Outdoor event', 'amount' => 250],
        ],
        'base_rates' => [],
        'round_to' => 50,
    ];

    /** Settings that are keyed maps (merged key-by-key over the defaults on read). */
    public const MAP_KEYS = ['season_multipliers', 'day_of_week_multipliers', 'standard_hours_by_type', 'base_rates'];

    /** Settings that are lists (replaced wholesale). */
    public const LIST_KEYS = ['special_dates', 'add_ons'];

    public const MIN_TYPE_SAMPLES = 3;
    public const HIGH_CONFIDENCE_SAMPLES = 10;
    public const RECENCY_HALF_LIFE_YEARS = 2;
    public const DISTANCE_CACHE_DAYS = 30;
    public const MAX_COMPARABLES = 5;

    private const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    private const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    // ─────────────────────────────────────────────────────────────────────
    // Settings
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The band's effective settings: stored overrides merged over defaults.
     */
    public function settingsFor(Bands $band): array
    {
        $stored = BandPricingSettings::where('band_id', $band->id)->first()?->settings ?? [];

        return $this->mergeSettings(self::DEFAULT_SETTINGS, is_array($stored) ? $stored : []);
    }

    /**
     * Persist a (partial) settings update. Every top-level key provided
     * replaces the stored value for that key; keys not provided are kept.
     * Returns the merged effective settings.
     */
    public function saveSettings(Bands $band, array $incoming): array
    {
        $record = BandPricingSettings::firstOrNew(['band_id' => $band->id]);
        $stored = is_array($record->settings) ? $record->settings : [];

        foreach ($incoming as $key => $value) {
            if (!array_key_exists($key, self::DEFAULT_SETTINGS)) {
                continue;
            }
            $stored[$key] = $this->sanitizeSetting($key, $value);
        }

        $record->settings = $stored;
        $record->save();

        return $this->settingsFor($band);
    }

    /**
     * GET/PUT pricing-settings response body.
     */
    public function settingsPayload(Bands $band): array
    {
        $settings = $this->settingsFor($band);

        return [
            'settings' => $this->presentSettings($settings),
            'event_types' => EventTypes::query()
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->name])
                ->all(),
            'history' => $this->historySummary($band, $settings),
        ];
    }

    public function mergeSettings(array $defaults, array $stored): array
    {
        $merged = $defaults;

        foreach ($stored as $key => $value) {
            if (!array_key_exists($key, $defaults)) {
                continue;
            }
            if (in_array($key, self::MAP_KEYS, true)) {
                $merged[$key] = array_replace($defaults[$key], $this->sanitizeSetting($key, $value));
            } else {
                $merged[$key] = $this->sanitizeSetting($key, $value);
            }
        }

        return $merged;
    }

    /**
     * Shape settings for JSON: keyed maps become objects (so "0".."6" and an
     * empty map don't serialize as arrays) and numbers get stable types.
     */
    public function presentSettings(array $settings): array
    {
        $out = [];
        foreach (self::DEFAULT_SETTINGS as $key => $_) {
            $value = $settings[$key] ?? self::DEFAULT_SETTINGS[$key];

            if (in_array($key, self::MAP_KEYS, true)) {
                $map = [];
                foreach ($value as $k => $v) {
                    $map[(string) $k] = (float) $v;
                }
                $out[$key] = (object) $map;
            } elseif ($key === 'special_dates') {
                $out[$key] = array_values(array_map(fn ($d) => [
                    'date' => (string) ($d['date'] ?? ''),
                    'label' => (string) ($d['label'] ?? ''),
                    'multiplier' => (float) ($d['multiplier'] ?? 1.0),
                ], $value));
            } elseif ($key === 'add_ons') {
                $out[$key] = array_values(array_map(fn ($a) => [
                    'key' => (string) ($a['key'] ?? ''),
                    'label' => (string) ($a['label'] ?? ''),
                    'amount' => (float) ($a['amount'] ?? 0),
                ], $value));
            } elseif ($key === 'short_notice_days') {
                $out[$key] = (int) $value;
            } else {
                $out[$key] = (float) $value;
            }
        }

        return $out;
    }

    private function sanitizeSetting(string $key, mixed $value): mixed
    {
        switch ($key) {
            case 'season_multipliers':
                return $this->sanitizeMap($value, fn ($k) => $k >= 1 && $k <= 12);
            case 'day_of_week_multipliers':
                return $this->sanitizeMap($value, fn ($k) => $k >= 0 && $k <= 6);
            case 'standard_hours_by_type':
            case 'base_rates':
                return $this->sanitizeMap($value, fn ($k) => $k >= 1);
            case 'special_dates':
                return array_values(array_filter(array_map(function ($d) {
                    if (!is_array($d) || empty($d['date'])) {
                        return null;
                    }

                    return [
                        'date' => (string) $d['date'],
                        'label' => (string) ($d['label'] ?? ''),
                        'multiplier' => (float) ($d['multiplier'] ?? 1.0),
                    ];
                }, is_array($value) ? $value : [])));
            case 'add_ons':
                return array_values(array_filter(array_map(function ($a) {
                    if (!is_array($a) || !isset($a['key']) || $a['key'] === '') {
                        return null;
                    }

                    return [
                        'key' => (string) $a['key'],
                        'label' => (string) ($a['label'] ?? $a['key']),
                        'amount' => (float) ($a['amount'] ?? 0),
                    ];
                }, is_array($value) ? $value : [])));
            case 'short_notice_days':
                return (int) $value;
            default:
                return is_numeric($value) ? $value + 0 : self::DEFAULT_SETTINGS[$key];
        }
    }

    /** Keep only integer-like keys passing $keyFilter with numeric, non-null values. */
    private function sanitizeMap(mixed $value, callable $keyFilter): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (!is_numeric($k) || (string) (int) $k !== (string) $k || !$keyFilter((int) $k)) {
                continue;
            }
            if ($v === null || $v === '' || !is_numeric($v)) {
                continue;
            }
            $out[(string) (int) $k] = (float) $v;
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // History
    // ─────────────────────────────────────────────────────────────────────

    /**
     * One row per eligible booking (non-cancelled, price > 0, with a dated
     * primary event). The primary event is the booking's earliest event by
     * (date, id), matching Bookings::primaryEvent().
     *
     * @return Collection<int, array{booking_id:int,name:string,event_type_id:int,price:float,date:Carbon,start_time:?string,end_time:?string,venue_name:string}>
     */
    public function historyRows(Bands $band): Collection
    {
        return DB::table('bookings')
            ->join('events', function ($join) {
                $join->on('events.eventable_id', '=', 'bookings.id')
                    ->where('events.eventable_type', '=', Bookings::class);
            })
            ->where('bookings.band_id', $band->id)
            ->whereNotIn('bookings.status', ['cancelled', 'deleted'])
            ->where('bookings.price', '>', 0)
            ->whereNotNull('events.date')
            ->orderBy('events.date')
            ->orderBy('events.id')
            ->get([
                'bookings.id as booking_id',
                'bookings.name',
                'bookings.event_type_id',
                'bookings.price',
                'events.date',
                'events.start_time',
                'events.end_time',
                'events.venue_name',
            ])
            ->unique('booking_id')
            ->values()
            ->map(fn ($row) => [
                'booking_id' => (int) $row->booking_id,
                'name' => (string) ($row->name ?? ''),
                'event_type_id' => (int) $row->event_type_id,
                // bookings.price is stored in cents
                'price' => ((float) $row->price) / 100,
                'date' => Carbon::parse($row->date)->startOfDay(),
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'venue_name' => (string) ($row->venue_name ?? ''),
            ]);
    }

    /**
     * Normalize a history row to a "standard gig, today's dollars" price and
     * compute its recency weight.
     *
     * @return array{value:float,weight:float,years_ago:float}
     */
    public function normalizeSample(array $row, array $settings, CarbonInterface $today): array
    {
        /** @var Carbon $date */
        $date = $row['date'];

        $season = $this->seasonMultiplier($settings, $date);
        $dow = $this->dayOfWeekMultiplier($settings, $date);
        $hours = self::hoursBetween($row['start_time'] ?? null, $row['end_time'] ?? null);
        $duration = $hours === null
            ? 1.0
            : $this->durationFactor($settings, $hours, $this->standardHoursFor($settings, (int) $row['event_type_id']));

        $divisor = $season * $dow * $duration;
        if ($divisor <= 0) {
            $divisor = 1.0;
        }

        $yearsAgo = max(0.0, ($today->getTimestamp() - $date->getTimestamp()) / 86400 / 365.25);
        $inflation = (1 + ((float) $settings['annual_increase_pct']) / 100) ** $yearsAgo;

        return [
            'value' => ($row['price'] / $divisor) * $inflation,
            'weight' => 0.5 ** ($yearsAgo / self::RECENCY_HALF_LIFE_YEARS),
            'years_ago' => $yearsAgo,
        ];
    }

    /**
     * Per-event-type learned baselines: [{event_type_id, sample_size, baseline}].
     */
    public function historySummary(Bands $band, ?array $settings = null): array
    {
        $settings ??= $this->settingsFor($band);
        $today = Carbon::today();

        return $this->historyRows($band)
            ->groupBy('event_type_id')
            ->map(function (Collection $rows, $typeId) use ($settings, $today) {
                $samples = $rows->map(fn ($r) => $this->normalizeSample($r, $settings, $today))->all();

                return [
                    'event_type_id' => (int) $typeId,
                    'sample_size' => count($samples),
                    'baseline' => round(self::weightedPercentile($samples, 0.5), 2),
                ];
            })
            ->sortBy('event_type_id')
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Estimate
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  array{event_type_id:int,date:string,start_time?:?string,end_time?:?string,duration_hours?:?float,venue_address?:?string,distance_miles?:?float,add_ons?:?array,exclude_booking_id?:?int}  $input
     */
    public function estimate(Bands $band, array $input): array
    {
        $settings = $this->settingsFor($band);
        $today = Carbon::today();
        $date = Carbon::createFromFormat('Y-m-d', $input['date'])->startOfDay();
        $typeId = (int) $input['event_type_id'];
        $typeName = EventTypes::query()->whereKey($typeId)->value('name') ?? 'Event';

        $standardHours = $this->standardHoursFor($settings, $typeId);
        $hours = self::hoursBetween($input['start_time'] ?? null, $input['end_time'] ?? null);
        if ($hours === null && isset($input['duration_hours']) && is_numeric($input['duration_hours']) && $input['duration_hours'] > 0) {
            $hours = (float) $input['duration_hours'];
        }
        $hours ??= $standardHours;

        $warnings = [];
        if ($this->hasDateConflict($band, $date, $input['exclude_booking_id'] ?? null)) {
            $warnings[] = [
                'code' => 'date_conflict',
                'message' => 'You already have a booking on ' . $date->format('F j, Y') . '.',
            ];
        }

        // ── Baseline ───────────────────────────────────────────────────
        $rows = $this->historyRows($band);
        $allSamples = $rows->map(fn ($r) => $this->normalizeSample($r, $settings, $today))->all();
        $typeRows = $rows->filter(fn ($r) => $r['event_type_id'] === $typeId)->values();
        $typeSamples = $typeRows->map(fn ($r) => $this->normalizeSample($r, $settings, $today))->all();
        $typeCount = count($typeSamples);

        $manualRate = $settings['base_rates'][(string) $typeId] ?? $settings['base_rates'][$typeId] ?? null;
        $baselineLabel = '';

        if ($manualRate !== null && (float) $manualRate > 0) {
            $source = 'manual';
            $baseline = (float) $manualRate;
            $sampleSize = $typeCount;
            $confidence = $typeCount >= self::HIGH_CONFIDENCE_SAMPLES ? 'high' : 'medium';
            if ($typeCount >= self::MIN_TYPE_SAMPLES) {
                $median = self::weightedPercentile($typeSamples, 0.5);
                $lowRatio = $median > 0 ? self::weightedPercentile($typeSamples, 0.25) / $median : 0.9;
                $highRatio = $median > 0 ? self::weightedPercentile($typeSamples, 0.75) / $median : 1.1;
            } else {
                $lowRatio = 0.9;
                $highRatio = 1.1;
            }
            $lowBase = $baseline * $lowRatio;
            $highBase = $baseline * $highRatio;
            $baselineLabel = "{$typeName} base rate (manual)";
        } elseif ($typeCount >= self::MIN_TYPE_SAMPLES) {
            $source = 'history';
            $sampleSize = $typeCount;
            $baseline = self::weightedPercentile($typeSamples, 0.5);
            $lowBase = self::weightedPercentile($typeSamples, 0.25);
            $highBase = self::weightedPercentile($typeSamples, 0.75);
            $confidence = $typeCount >= self::HIGH_CONFIDENCE_SAMPLES ? 'high' : 'medium';
            $baselineLabel = "{$typeName} baseline ({$typeCount} past " . ($typeCount === 1 ? 'booking' : 'bookings') . ')';
        } elseif (count($allSamples) > 0) {
            $source = 'band_average';
            $sampleSize = count($allSamples);
            $baseline = self::weightedPercentile($allSamples, 0.5);
            $lowBase = self::weightedPercentile($allSamples, 0.25);
            $highBase = self::weightedPercentile($allSamples, 0.75);
            $confidence = 'low';
            $baselineLabel = "Band average baseline ({$sampleSize} past " . ($sampleSize === 1 ? 'booking' : 'bookings') . ')';
            $warnings[] = [
                'code' => 'low_sample',
                'message' => $typeCount === 0
                    ? "No past {$typeName} bookings with a price yet, so this uses your band-wide average."
                    : "Only {$typeCount} past {$typeName} " . ($typeCount === 1 ? 'booking' : 'bookings') . ' with a price, so this uses your band-wide average.',
            ];
        } else {
            $warnings[] = [
                'code' => 'no_baseline',
                'message' => 'Not enough booking history to estimate a price. Set a base rate for this event type in your pricing settings.',
            ];

            return [
                'estimate' => null,
                'low' => null,
                'high' => null,
                'confidence' => 'low',
                'baseline_source' => 'none',
                'sample_size' => 0,
                'baseline' => null,
                'duration_hours' => round($hours, 2),
                'distance_miles' => isset($input['distance_miles']) && is_numeric($input['distance_miles'])
                    ? round((float) $input['distance_miles'], 1)
                    : null,
                'breakdown' => [],
                'warnings' => $warnings,
                'comparables' => [],
            ];
        }

        $breakdown = [[
            'key' => 'baseline',
            'label' => $baselineLabel,
            'type' => 'base',
            'factor' => null,
            'amount' => round($baseline, 2),
        ]];

        // ── Multipliers (sequential) ───────────────────────────────────
        $running = $baseline;
        $product = 1.0;
        $applyMultiplier = function (string $key, string $label, float $factor, bool $alwaysShow) use (&$running, &$product, &$breakdown) {
            $delta = $running * ($factor - 1);
            $running *= $factor;
            $product *= $factor;
            if ($alwaysShow || abs($factor - 1.0) > 1e-9) {
                $breakdown[] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => 'multiplier',
                    'factor' => round($factor, 4),
                    'amount' => round($delta, 2),
                ];
            }
        };

        $season = $this->seasonMultiplier($settings, $date);
        $seasonLabel = self::MONTHS[$date->month] . ($season > 1 ? ' (peak season)' : ($season < 1 ? ' (off season)' : ''));
        $applyMultiplier('season', $seasonLabel, $season, true);

        $applyMultiplier('day_of_week', self::WEEKDAYS[$date->dayOfWeek], $this->dayOfWeekMultiplier($settings, $date), true);

        $special = $this->matchSpecialDate($settings, $date);
        if ($special !== null) {
            $applyMultiplier('special_date', $special['label'] !== '' ? $special['label'] : 'Special date', (float) $special['multiplier'], false);
        }

        $durationFactor = $this->durationFactor($settings, $hours, $standardHours);
        $applyMultiplier(
            'duration',
            self::formatHours($hours) . ' (standard ' . self::formatHours($standardHours) . ')',
            $durationFactor,
            false
        );

        $daysOut = (int) floor(($date->getTimestamp() - $today->getTimestamp()) / 86400);
        if ($daysOut >= 0 && $daysOut < (int) $settings['short_notice_days']) {
            $applyMultiplier(
                'short_notice',
                "Short notice ({$daysOut} " . ($daysOut === 1 ? 'day' : 'days') . ' out)',
                (float) $settings['short_notice_multiplier'],
                false
            );
        }

        // ── Fees ───────────────────────────────────────────────────────
        $fees = 0.0;
        $distanceMiles = null;

        if (isset($input['distance_miles']) && is_numeric($input['distance_miles'])) {
            $distanceMiles = (float) $input['distance_miles'];
        } elseif (!empty($input['venue_address'])) {
            $origin = $this->bandOrigin($band);
            if ($origin === null) {
                $warnings[] = [
                    'code' => 'band_address_missing',
                    'message' => "Add your band's address in band settings to calculate travel fees.",
                ];
            } else {
                $distanceMiles = $this->lookupDistanceMiles($origin, (string) $input['venue_address']);
                if ($distanceMiles === null) {
                    $warnings[] = [
                        'code' => 'distance_unavailable',
                        'message' => "Couldn't determine the distance to the venue, so no travel fee was added.",
                    ];
                }
            }
        }

        if ($distanceMiles !== null) {
            $travel = max(0.0, $distanceMiles - (float) $settings['free_radius_miles']) * 2 * (float) $settings['per_mile_rate'];
            if ($travel > 0) {
                $fees += $travel;
                $breakdown[] = [
                    'key' => 'travel',
                    'label' => 'Travel (' . self::formatNumber(round($distanceMiles, 1)) . ' mi one-way)',
                    'type' => 'fee',
                    'factor' => null,
                    'amount' => round($travel, 2),
                ];
            }

            $lodgingFee = (float) $settings['lodging_fee'];
            if ($distanceMiles > (float) $settings['lodging_threshold_miles'] && $lodgingFee > 0) {
                $fees += $lodgingFee;
                $breakdown[] = [
                    'key' => 'lodging',
                    'label' => 'Lodging',
                    'type' => 'fee',
                    'factor' => null,
                    'amount' => round($lodgingFee, 2),
                ];
            }
        }

        $selectedAddOns = array_values(array_unique(array_map('strval', $input['add_ons'] ?? [])));
        foreach ($settings['add_ons'] as $addOn) {
            if (!in_array((string) $addOn['key'], $selectedAddOns, true)) {
                continue;
            }
            $amount = (float) $addOn['amount'];
            $fees += $amount;
            $breakdown[] = [
                'key' => 'addon:' . $addOn['key'],
                'label' => (string) $addOn['label'],
                'type' => 'fee',
                'factor' => null,
                'amount' => round($amount, 2),
            ];
        }

        // ── Totals ─────────────────────────────────────────────────────
        $roundTo = (float) $settings['round_to'];
        $estimate = self::roundTo($running + $fees, $roundTo);
        $low = min($estimate, self::roundTo($lowBase * $product + $fees, $roundTo));
        $high = max($estimate, self::roundTo($highBase * $product + $fees, $roundTo));

        return [
            'estimate' => $estimate,
            'low' => $low,
            'high' => $high,
            'confidence' => $confidence,
            'baseline_source' => $source,
            'sample_size' => $sampleSize,
            'baseline' => round($baseline, 2),
            'duration_hours' => round($hours, 2),
            'distance_miles' => $distanceMiles === null ? null : round($distanceMiles, 1),
            'breakdown' => $breakdown,
            'warnings' => $warnings,
            'comparables' => $this->comparables($typeRows, $date),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Weighted percentile over [['value'=>float,'weight'=>float], ...].
     * Returns the smallest value whose cumulative weight reaches p × total.
     */
    public static function weightedPercentile(array $samples, float $p): float
    {
        $samples = array_values(array_filter($samples, fn ($s) => ($s['weight'] ?? 0) > 0));
        if (count($samples) === 0) {
            return 0.0;
        }
        usort($samples, fn ($a, $b) => $a['value'] <=> $b['value']);

        $total = array_sum(array_column($samples, 'weight'));
        $target = $p * $total;
        $cumulative = 0.0;
        foreach ($samples as $sample) {
            $cumulative += $sample['weight'];
            if ($cumulative >= $target - 1e-9) {
                return (float) $sample['value'];
            }
        }

        return (float) end($samples)['value'];
    }

    /**
     * Hours between two "HH:MM[:SS]" times. End before start crosses
     * midnight (+24h). Missing/unparseable or zero-length → null.
     */
    public static function hoursBetween(?string $start, ?string $end): ?float
    {
        $startMinutes = self::minutesOfDay($start);
        $endMinutes = self::minutesOfDay($end);
        if ($startMinutes === null || $endMinutes === null) {
            return null;
        }
        $diff = $endMinutes - $startMinutes;
        if ($diff < 0) {
            $diff += 24 * 60;
        }

        return $diff === 0 ? null : $diff / 60;
    }

    public static function roundTo(float $value, float $step): float
    {
        if ($step <= 0) {
            return round($value, 2);
        }

        return (float) (round($value / $step) * $step);
    }

    public function durationFactor(array $settings, float $hours, float $standardHours): float
    {
        $factor = 1 + ((float) $settings['overtime_pct_per_hour']) / 100 * ($hours - $standardHours);

        return max((float) $settings['min_duration_factor'], $factor);
    }

    public function standardHoursFor(array $settings, int $eventTypeId): float
    {
        $byType = $settings['standard_hours_by_type'] ?? [];
        $value = $byType[(string) $eventTypeId] ?? $byType[$eventTypeId] ?? null;

        return (float) ($value !== null && $value > 0 ? $value : $settings['standard_hours']);
    }

    public function seasonMultiplier(array $settings, CarbonInterface $date): float
    {
        return (float) ($settings['season_multipliers'][(string) $date->month] ?? $settings['season_multipliers'][$date->month] ?? 1.0);
    }

    public function dayOfWeekMultiplier(array $settings, CarbonInterface $date): float
    {
        return (float) ($settings['day_of_week_multipliers'][(string) $date->dayOfWeek] ?? $settings['day_of_week_multipliers'][$date->dayOfWeek] ?? 1.0);
    }

    /** First special date matching (MM-DD recurring or YYYY-MM-DD one-off). */
    public function matchSpecialDate(array $settings, CarbonInterface $date): ?array
    {
        foreach ($settings['special_dates'] ?? [] as $special) {
            $pattern = (string) ($special['date'] ?? '');
            if ($pattern === $date->format('m-d') || $pattern === $date->format('Y-m-d')) {
                return [
                    'date' => $pattern,
                    'label' => (string) ($special['label'] ?? ''),
                    'multiplier' => (float) ($special['multiplier'] ?? 1.0),
                ];
            }
        }

        return null;
    }

    private function hasDateConflict(Bands $band, CarbonInterface $date, mixed $excludeBookingId): bool
    {
        return DB::table('events')
            ->join('bookings', function ($join) {
                $join->on('events.eventable_id', '=', 'bookings.id')
                    ->where('events.eventable_type', '=', Bookings::class);
            })
            ->where('bookings.band_id', $band->id)
            ->whereNotIn('bookings.status', ['cancelled', 'deleted'])
            ->where('events.date', $date->toDateString())
            ->when($excludeBookingId, fn ($q) => $q->where('bookings.id', '!=', (int) $excludeBookingId))
            ->exists();
    }

    private function comparables(Collection $typeRows, CarbonInterface $date): array
    {
        return $typeRows
            ->sort(function ($a, $b) use ($date) {
                $aSameMonth = $a['date']->month === $date->month ? 0 : 1;
                $bSameMonth = $b['date']->month === $date->month ? 0 : 1;
                if ($aSameMonth !== $bSameMonth) {
                    return $aSameMonth <=> $bSameMonth;
                }

                return $b['date']->getTimestamp() <=> $a['date']->getTimestamp();
            })
            ->take(self::MAX_COMPARABLES)
            ->map(fn ($r) => [
                'booking_id' => $r['booking_id'],
                'name' => $r['name'],
                'date' => $r['date']->toDateString(),
                'price' => round((float) $r['price'], 2),
                'venue_name' => $r['venue_name'],
            ])
            ->values()
            ->all();
    }

    private function bandOrigin(Bands $band): ?string
    {
        if (!$band->address || !$band->city || !$band->state) {
            return null;
        }

        return trim($band->address . ' ' . $band->city . ' ' . $band->state . ' ' . ($band->zip ?? ''));
    }

    /**
     * One-way driving miles via Google Distance Matrix (same facade as
     * MileageService). Successful lookups are cached for 30 days; failures
     * are not cached and return null.
     */
    protected function lookupDistanceMiles(string $origin, string $destination): ?float
    {
        $cacheKey = 'price_estimator:distance:' . md5(mb_strtolower(trim($origin)) . '|' . mb_strtolower(trim($destination)));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (float) $cached;
        }

        try {
            $response = json_decode(\GoogleMaps::load('distancematrix')
                ->setParamByKey('origins', $origin)
                ->setParamByKey('destinations', $destination)
                ->setParamByKey('units', 'imperial')
                ->get());
        } catch (\Throwable $e) {
            Log::warning('PriceEstimator distance lookup failed', ['error' => $e->getMessage()]);

            return null;
        }

        $element = $response->rows[0]->elements[0] ?? null;
        if (!$element || ($element->status ?? null) !== 'OK') {
            return null;
        }

        $miles = null;
        if (isset($element->distance->value) && is_numeric($element->distance->value)) {
            $miles = ((float) $element->distance->value) / 1609.344;
        } elseif (isset($element->distance->text)) {
            $parsed = preg_replace('/[^\d.]/', '', (string) $element->distance->text);
            $miles = is_numeric($parsed) ? (float) $parsed : null;
        }

        if ($miles === null) {
            return null;
        }

        Cache::put($cacheKey, $miles, now()->addDays(self::DISTANCE_CACHE_DAYS));

        return $miles;
    }

    private static function minutesOfDay(?string $time): ?int
    {
        if ($time === null || !preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return $h * 60 + $min;
    }

    private static function formatHours(float $hours): string
    {
        return self::formatNumber($hours) . ' ' . (abs($hours - 1.0) < 1e-9 ? 'hour' : 'hours');
    }

    private static function formatNumber(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
