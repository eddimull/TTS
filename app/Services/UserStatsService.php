<?php

namespace App\Services;

use App\Models\User;
use App\Models\Events;
use App\Models\EventDistanceForMembers;
use App\Services\MileageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UserStatsService
{
    protected User $user;
    protected ?array $cachedPivots = null;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Fetch owner/member/sub pivot rows for all user bands in 3 queries, cached for the request.
     */
    protected function getBandPivots(array $bandIds): array
    {
        if ($this->cachedPivots !== null) {
            return $this->cachedPivots;
        }

        $this->cachedPivots = [
            'owners'  => DB::table('band_owners')->where('user_id', $this->user->id)->whereIn('band_id', $bandIds)->get()->keyBy('band_id'),
            'members' => DB::table('band_members')->where('user_id', $this->user->id)->whereIn('band_id', $bandIds)->get()->keyBy('band_id'),
            'subs'    => DB::table('band_subs')->where('user_id', $this->user->id)->whereIn('band_id', $bandIds)->get()->keyBy('band_id'),
        ];

        return $this->cachedPivots;
    }

    /**
     * Get comprehensive user stats including payments, travel, and locations
     */
    public function getUserStats(): array
    {
        return [
            'payments' => $this->getPaymentStats(),
            'travel' => $this->getTravelStats(),
            'locations' => $this->getEventLocations(),
        ];
    }

    /**
     * Get payment statistics for the user - their personal share of band earnings from bookings
     */
    protected function getPaymentStats(): array
    {
        $bandEarnings = [];
        $yearEarnings = [];
        $bookingsByYear = [];
        $totalBookingCount = 0;

        // Get all bands the user owns, is a member of, or subs for — subs can
        // have a payout share when the band's flow config includes them.
        // Use collection-level load methods so both calls batch across all bands
        // rather than firing per-model queries (N+1).
        $allBands = $this->getUserBands();
        $allBands->loadMissing(['activePayoutConfig']);
        $allBands->loadCount(['owners', 'members']);

        // Pre-calculate user's membership status and join dates for each band to avoid N+1 queries
        $userBandStatus = [];
        $bandJoinDates = [];

        $bandIds = $allBands->pluck('id')->toArray();
        // getBandPivots queries band_owners, band_members, and band_subs and
        // caches the result — derive owner/member presence from the same rows
        // rather than issuing two more pluck queries.
        $pivots = $this->getBandPivots($bandIds);

        foreach ($allBands as $band) {
            $userBandStatus[$band->id] = [
                'is_owner'  => $pivots['owners']->has($band->id),
                'is_member' => $pivots['members']->has($band->id),
            ];
            $bandJoinDates[$band->id] = $this->getUserJoinDateFromPivots(
                $pivots['owners']->get($band->id),
                $pivots['members']->get($band->id),
                $pivots['subs']->get($band->id),
            );
        }

        foreach ($allBands as $band) {
            // Use cached join date
            $joinDate = $bandJoinDates[$band->id];

            // Get all bookings for this band after the user joined.
            // date column was removed from bookings (moved to events); use created_at as a proxy.
            // Eager load payouts to avoid N+1 queries.
            // For sub-only users, scope to bookings that contain at least one
            // event they were assigned to (mirrors getTravelStats scoping).
            $allowedEventIds = $this->getAllowedEventIds($band->id);

            $bookingsQuery = \App\Models\Bookings::where('band_id', $band->id)
                ->where('created_at', '>=', $joinDate)
                ->whereIn('status', ['confirmed', 'pending'])
                ->with([
                    'payout.adjustments',
                    'events.eventMembers.bandRole',
                    'events.eventMembers.rosterMember.bandRole',
                    'events.eventMembers.rosterMember.user',
                ])
                ->orderBy('created_at', 'desc');

            if ($allowedEventIds !== null) {
                $bookingsQuery->whereHas('events', fn ($q) => $q->whereIn('events.id', $allowedEventIds));
            }

            $bookings = $bookingsQuery->get();

            $bandTotal = 0;

            foreach ($bookings as $booking) {
                // Calculate user's share of this booking
                $userShare = $this->calculateUserShareFromBooking($band, $booking, $userBandStatus[$band->id] ?? []);

                if ($userShare > 0) {
                    $bandTotal += $userShare;
                    $totalBookingCount++;

                    // Add to year earnings
                    $year = $booking->start_date?->year;
                    if (!isset($yearEarnings[$year])) {
                        $yearEarnings[$year] = 0;
                    }
                    $yearEarnings[$year] += $userShare;

                    // Add detailed booking info to year grouping
                    if (!isset($bookingsByYear[$year])) {
                        $bookingsByYear[$year] = [];
                    }

                    $primary = $booking->events()->orderBy('date')->orderBy('id')->first();
                    $bookingsByYear[$year][] = [
                        'id' => $booking->id,
                        'booking_name' => $booking->name,
                        'band_name' => $band->name,
                        'band_id' => $band->id,
                        'venue_name' => $booking->venue_summary ?? 'TBD',
                        'venue_address' => $primary?->venue_address ?? '',
                        'date' => $booking->start_date?->format('Y-m-d'),
                        'status' => $booking->status,
                        'total_price' => number_format(floatval($booking->price), 2, '.', ''),
                        'user_share' => number_format($userShare / 100, 2, '.', ''),
                    ];
                }
            }

            if ($bandTotal > 0) {
                $bandEarnings[$band->id] = [
                    'band_id' => $band->id,
                    'band_name' => $band->name,
                    'total' => number_format($bandTotal / 100, 2, '.', ''),
                    'booking_count' => $bookings->count(),
                ];
            }
        }

        // Format year earnings
        $byYear = collect($yearEarnings)
            ->map(function ($total, $year) {
                return [
                    'year' => $year,
                    'total' => number_format($total / 100, 2, '.', ''),
                ];
            })
            ->sortByDesc('year')
            ->values()
            ->toArray();

        // Format bookings by year - sort years descending and bookings by date descending
        $formattedBookingsByYear = collect($bookingsByYear)
            ->map(function ($bookings, $year) {
                return [
                    'year' => $year,
                    'bookings' => collect($bookings)->sortByDesc('date')->values()->toArray(),
                    'year_total' => number_format(
                        collect($bookings)->sum(function ($b) {
                            return floatval($b['user_share']);
                        }),
                        2,
                        '.',
                        ''
                    ),
                    'booking_count' => count($bookings),
                ];
            })
            ->sortByDesc('year')
            ->values()
            ->toArray();

        $totalEarnings = array_sum($yearEarnings);

        return [
            'total_earnings' => number_format($totalEarnings / 100, 2, '.', ''),
            'by_year' => $byYear,
            'by_band' => array_values($bandEarnings),
            'booking_count' => $totalBookingCount,
            'bookings_by_year' => $formattedBookingsByYear,
        ];
    }

    /**
     * Get all bands the user owns, is a member of, or subs for
     */
    protected function getUserBands()
    {
        return $this->user->allBands()->unique('id');
    }

    /**
     * Get event IDs this user is allowed to see for a given band.
     * Subs only see events they are specifically assigned to.
     * Owners/members see all band events.
     */
    protected function getAllowedEventIds(int $bandId): ?array
    {
        $isSub = $this->user->isSubOfBand($bandId)
            && !$this->user->ownsBand($bandId)
            && !$this->user->isPartOfBand($bandId);

        if (!$isSub) {
            return null; // null = no restriction
        }

        // Mirror UserEventsService::getSubEvents: union accepted event_subs
        // invitations with event_members rows where roster_member_id is NULL
        // (direct sub assignment without an invitation flow).
        $fromInvitations = DB::table('event_subs')
            ->where('user_id', $this->user->id)
            ->where('band_id', $bandId)
            ->where('pending', false)
            ->pluck('event_id')
            ->all();

        $fromEventMembers = DB::table('event_members')
            ->where('user_id', $this->user->id)
            ->where('band_id', $bandId)
            ->whereNull('roster_member_id')
            ->whereNull('deleted_at')
            ->pluck('event_id')
            ->all();

        return array_values(array_unique(array_merge($fromInvitations, $fromEventMembers)));
    }

    /**
     * Derive the user's join date for a band from pre-fetched pivot rows.
     */
    protected function getUserJoinDateFromPivots($ownerPivot, $memberPivot, $subPivot): Carbon
    {
        $ownerDate = $ownerPivot ? Carbon::parse($ownerPivot->created_at) : null;
        $memberDate = $memberPivot ? Carbon::parse($memberPivot->created_at) : null;

        if ($ownerDate && $memberDate) {
            return $ownerDate->lt($memberDate) ? $ownerDate : $memberDate;
        }

        $subDate = $subPivot ? Carbon::parse($subPivot->created_at) : null;

        return $ownerDate ?? $memberDate ?? $subDate ?? Carbon::now();
    }

    /**
     * Calculate the user's share of a booking based on its price
     *
     * @param mixed $band Band model with eager loaded relationships
     * @param mixed $booking Booking model with eager loaded payout
     * @param array $userBandStatus Array with ['is_owner' => bool, 'is_member' => bool]
     */
    protected function calculateUserShareFromBooking($band, $booking, array $userBandStatus = []): float
    {
        // Use eager loaded payout to avoid N+1 query
        $payout = $booking->payout;

        // First priority: Check if there's a saved payout calculation result (from flow-based or payment groups)
        if ($payout && isset($payout->calculation_result['member_payouts'])) {
            $memberPayouts = $payout->calculation_result['member_payouts'];
            $userTotal = 0;

            // Sum all payouts for this user (they might appear in multiple payout groups)
            foreach ($memberPayouts as $memberPayout) {
                if (isset($memberPayout['user_id']) && $memberPayout['user_id'] == $this->user->id) {
                    $userTotal += $memberPayout['amount'];
                }
            }

            if ($userTotal > 0) {
                // Return amount in cents (payout amount is in dollars)
                return $userTotal * 100;
            }

            // User not in any payout groups for this booking
            return 0;
        }

        // Second priority: Calculate using active payout config
        $payoutConfig = $band->activePayoutConfig;

        // Use adjusted amount if payout exists, otherwise use base price
        $bookingPrice = $payout
            ? $payout->adjusted_amount_float
            : (is_string($booking->price) ? floatval($booking->price) : $booking->price);

        if ($payoutConfig) {
            // Check if this config uses flow_diagram (new system)
            if ($payoutConfig->flow_diagram && is_array($payoutConfig->flow_diagram) && isset($payoutConfig->flow_diagram['nodes'])) {
                // Flow-based calculation - need to calculate on the fly
                $distribution = $payoutConfig->calculatePayouts($bookingPrice, null, $booking);

                if (isset($distribution['member_payouts'])) {
                    $userTotal = 0;

                    // Sum all payouts for this user
                    foreach ($distribution['member_payouts'] as $memberPayout) {
                        if (isset($memberPayout['user_id']) && $memberPayout['user_id'] == $this->user->id) {
                            $userTotal += $memberPayout['amount'];
                        }
                    }

                    if ($userTotal > 0) {
                        // Return amount in cents (payout amount is in dollars)
                        return $userTotal * 100;
                    }
                }

                // User not in any payout groups
                return 0;
            }

            // Old payment groups system (before flow diagrams)
            // Pass member counts to avoid N+1 queries
            $memberCounts = [
                'owners' => $band->owners_count ?? (isset($band->owners_count) ? $band->owners_count : $band->owners()->count()),
                'members' => $band->members_count ?? (isset($band->members_count) ? $band->members_count : $band->members()->count()),
            ];
            $distribution = $payoutConfig->calculatePayouts($bookingPrice, $memberCounts);

            // Check if payouts have user_ids (payment groups) or not (old equal split)
            $hasUserIds = !empty($distribution['member_payouts']) && isset($distribution['member_payouts'][0]['user_id']);

            if ($hasUserIds) {
                // Payment groups: Find this user's share by user_id
                $userTotal = 0;
                foreach ($distribution['member_payouts'] as $memberPayout) {
                    if (isset($memberPayout['user_id']) && $memberPayout['user_id'] == $this->user->id) {
                        $userTotal += $memberPayout['amount'];
                    }
                }

                if ($userTotal > 0) {
                    // Return amount in cents (payout amount is in dollars)
                    return $userTotal * 100;
                }

                // User not in payment groups
                return 0;
            } else {
                // Old system: payouts by type (owner/member) without user_ids
                // Use pre-calculated status to avoid N+1 queries
                $isOwner = $userBandStatus['is_owner'] ?? false;
                $isMember = $userBandStatus['is_member'] ?? false;

                if (!$isOwner && !$isMember) {
                    return 0;
                }

                // Find payouts for this user's type
                $userType = $isOwner ? 'owner' : 'member';
                $typePayouts = array_filter($distribution['member_payouts'], function($payout) use ($userType) {
                    return isset($payout['type']) && $payout['type'] === $userType;
                });

                if (empty($typePayouts)) {
                    return 0;
                }

                // Use cached counts to avoid N+1 queries (with fallback for tests)
                if ($userType === 'owner') {
                    $typeCount = isset($band->owners_count) ? $band->owners_count : $band->owners()->count();
                } else {
                    $typeCount = isset($band->members_count) ? $band->members_count : $band->members()->count();
                }

                // Sum all payouts for this type and divide by number of users of this type
                $totalForType = array_sum(array_column($typePayouts, 'amount'));
                $sharePerPerson = $typeCount > 0 ? $totalForType / $typeCount : 0;

                return $sharePerPerson * 100; // Convert to cents
            }
        } else {
            // No payout config - divide equally among all members
            // Use cached counts to avoid N+1 queries (with fallback for tests)
            $ownerCount = isset($band->owners_count) ? $band->owners_count : $band->owners()->count();
            $memberCount = isset($band->members_count) ? $band->members_count : $band->members()->count();
            $totalMembers = $ownerCount + $memberCount;

            if ($totalMembers > 0) {
                // Return equal share in cents (booking price is in dollars)
                return ($bookingPrice * 100) / $totalMembers;
            }

            return 0;
        }
    }

    /**
     * Get travel statistics for the user - only for events after joining each band
     */
    protected function getTravelStats(): array
    {
        // Get all bands the user owns or is a member of (simple query, no eager loading needed)
        $allBands = $this->getUserBands();

        if ($allBands->isEmpty()) {
            return [
                'total_miles' => 0,
                'total_minutes' => 0,
                'event_count' => 0,
            ];
        }

        // Get event IDs for events after user joined each band
        // Cache join dates to avoid N+1 queries
        $validEventIds = [];
        $bandJoinDates = [];
        $bandIds = $allBands->pluck('id')->toArray();
        $pivots = $this->getBandPivots($bandIds);
        foreach ($allBands as $band) {
            $bandJoinDates[$band->id] = $this->getUserJoinDateFromPivots(
                $pivots['owners']->get($band->id),
                $pivots['members']->get($band->id),
                $pivots['subs']->get($band->id),
            );
        }

        $mileageService = new MileageService();
        Auth::setUser($this->user);

        // Collect all attended events with their band name for per-event detail rows
        $attendedEventsMeta = collect(); // [event_id => ['event' => ..., 'band_name' => ...]]

        foreach ($allBands as $band) {
            $joinDate = $bandJoinDates[$band->id];

            // Get full event models with eventables so MileageService can read venue addresses.
            // Also eager-load the user's EventMember record to check attendance.
            $allowedEventIds = $this->getAllowedEventIds($band->id);

            $bandEventsQuery = Events::whereHasMorph('eventable', [\App\Models\Bookings::class, \App\Models\BandEvents::class], function ($query) use ($band) {
                $query->where('band_id', $band->id);
            })
            ->with([
                'eventable',
                'eventMembers' => function ($query) {
                    $query->where('user_id', $this->user->id);
                },
            ])
            ->where('date', '>=', $joinDate)
            ->where('date', '<=', Carbon::now());

            if ($allowedEventIds !== null) {
                $bandEventsQuery->whereIn('events.id', $allowedEventIds);
            }

            $bandEvents = $bandEventsQuery->get();

            // Include events where the user has no roster entry (pre-roster legacy events)
            // or is explicitly confirmed/attended. Exclude only absent/excused.
            $attendedEvents = $bandEvents->filter(function ($event) {
                $member = $event->eventMembers->first();
                return $member === null || in_array($member->attendance_status, ['confirmed', 'attended']);
            });

            $validEventIds = array_merge($validEventIds, $attendedEvents->pluck('id')->toArray());

            foreach ($attendedEvents as $event) {
                $attendedEventsMeta->put($event->id, ['event' => $event, 'band_name' => $band->name]);
            }

            // Calculate mileage for attended events that don't have a cached distance yet,
            // falling back to band address if the user has no address on file
            $attendedEventIds = $attendedEvents->pluck('id')->toArray();
            $cachedDistances = EventDistanceForMembers::where('user_id', $this->user->id)
                ->whereIn('event_id', $attendedEventIds)
                ->whereNotNull('miles')
                ->get()
                ->keyBy('event_id');

            $eventsNeedingDistance = $attendedEvents->filter(function ($event) use ($cachedDistances) {
                $existing = $cachedDistances->get($event->id);
                return $existing === null || $existing->created_at < $event->updated_at;
            });

            if ($eventsNeedingDistance->isNotEmpty()) {
                $mileageService->handle($eventsNeedingDistance, $band);
            }
        }

        $uniqueEventIds = array_unique($validEventIds);

        // Get distances only for valid events with distance tracking
        $distances = EventDistanceForMembers::where('user_id', $this->user->id)
            ->whereIn('event_id', $uniqueEventIds)
            ->whereNotNull('miles')
            ->get();

        $totalMiles = $distances->sum('miles');
        $totalMinutes = $distances->sum('minutes');

        $distancesByEventId = $distances->keyBy('event_id');

        // Build per-year breakdown with per-event detail rows
        $yearStats = [];
        foreach ($uniqueEventIds as $eventId) {
            $meta = $attendedEventsMeta->get($eventId);
            if (!$meta) {
                continue;
            }
            $event = $meta['event'];
            $year = $event->date->year;
            $dist = $distancesByEventId->get($eventId);

            $eventable = $event->eventable;
            // venue_name/venue_address now live on the Events row for Bookings;
            // for BandEvents they still live on the eventable.
            $venueName = $event->venue_name ?? $eventable?->venue_name ?? null;
            $venueAddress = $event->venue_address
                ?? $eventable?->venue_address
                ?? (isset($eventable->address_street)
                    ? trim($eventable->address_street . ' ' . ($eventable->city ?? '') . ' ' . ($eventable->zip ?? ''))
                    : null);

            if (!isset($yearStats[$year])) {
                $yearStats[$year] = ['miles' => 0, 'minutes' => 0, 'event_count' => 0, 'events' => []];
            }
            $yearStats[$year]['event_count']++;
            if ($dist) {
                $yearStats[$year]['miles'] += $dist->miles;
                $yearStats[$year]['minutes'] += $dist->minutes;
            }
            $yearStats[$year]['events'][] = [
                'date'          => $event->date->format('Y-m-d'),
                'title'         => $event->title,
                'band_name'     => $meta['band_name'],
                'venue_name'    => $venueName ?? 'TBD',
                'venue_address' => $venueAddress ?? '',
                'miles'         => $dist ? round((float) $dist->miles, 1) : null,
                'hours'         => $dist ? round($dist->minutes / 60, 1) : null,
            ];
        }

        $byYear = collect($yearStats)
            ->map(function ($data, $year) {
                return [
                    'year'        => $year,
                    'total_miles' => round($data['miles'], 1),
                    'total_hours' => round($data['minutes'] / 60, 1),
                    'event_count' => $data['event_count'],
                    'events'      => collect($data['events'])->sortByDesc('date')->values()->toArray(),
                ];
            })
            ->sortByDesc('year')
            ->values()
            ->toArray();

        return [
            'total_miles'   => round((float) $totalMiles, 1),
            'total_minutes' => (int) $totalMinutes,
            'total_hours'   => round($totalMinutes / 60, 1),
            'event_count'   => count($uniqueEventIds),
            'by_year'       => $byYear,
        ];
    }

    /**
     * Get event locations for map display - only events after joining each band
     */
    protected function getEventLocations(): array
    {
        // Get all bands the user owns or is a member of (simple query, no eager loading needed)
        $allBands = $this->getUserBands();

        if ($allBands->isEmpty()) {
            return [];
        }

        // Cache join dates to avoid N+1 queries
        $bandJoinDates = [];
        $bandIds = $allBands->pluck('id')->toArray();
        $pivots = $this->getBandPivots($bandIds);
        foreach ($allBands as $band) {
            $bandJoinDates[$band->id] = $this->getUserJoinDateFromPivots(
                $pivots['owners']->get($band->id),
                $pivots['members']->get($band->id),
                $pivots['subs']->get($band->id),
            );
        }

        $allEvents = collect();

        foreach ($allBands as $band) {
            $joinDate = $bandJoinDates[$band->id];
            $allowedEventIds = $this->getAllowedEventIds($band->id);

            $withClause = [
                'eventable',
                'eventMembers' => function ($query) {
                    $query->where('user_id', $this->user->id);
                },
            ];

            // Get events from bookings for this band after join date
            $bookingEventsQuery = Events::whereHasMorph('eventable', [\App\Models\Bookings::class], function ($query) use ($band) {
                $query->where('band_id', $band->id);
            })
            ->with($withClause)
            ->where('date', '>=', $joinDate)
            ->where('date', '<=', Carbon::now());

            if ($allowedEventIds !== null) {
                $bookingEventsQuery->whereIn('events.id', $allowedEventIds);
            }

            // Get events from legacy band_events for this band after join date
            $bandEventEventsQuery = Events::whereHasMorph('eventable', [\App\Models\BandEvents::class], function ($query) use ($band) {
                $query->where('band_id', $band->id);
            })
            ->with($withClause)
            ->where('date', '>=', $joinDate)
            ->where('date', '<=', Carbon::now());

            if ($allowedEventIds !== null) {
                $bandEventEventsQuery->whereIn('events.id', $allowedEventIds);
            }

            $bandEvents = $bookingEventsQuery->get()->concat($bandEventEventsQuery->get())
                ->filter(function ($event) {
                    $member = $event->eventMembers->first();
                    return $member === null || in_array($member->attendance_status, ['confirmed', 'attended']);
                });

            $allEvents = $allEvents->concat($bandEvents);
        }

        // Sort by date descending and limit to 100 for performance
        // venue_name / venue_address now live on the Events row (moved from bookings)
        $locations = $allEvents
            ->sortByDesc('date')
            ->take(100)
            ->filter(function ($event) {
                // Try event-own fields first, fall back to eventable (BandEvents still uses eventable)
                $venueName = $event->venue_name ?? $event->eventable?->venue_name ?? null;
                return $venueName && $venueName !== 'TBD'
                    && ($event->venue_address ?? $event->eventable?->venue_address ?? null);
            })
            ->map(function ($event) {
                $venueName    = $event->venue_name ?? $event->eventable?->venue_name;
                $venueAddress = $event->venue_address ?? $event->eventable?->venue_address;
                return [
                    'title'        => $event->title,
                    'venue_name'   => $venueName,
                    'venue_address' => $venueAddress,
                    'date'         => $event->date->format('Y-m-d'),
                    'full_address' => $venueName . ', ' . $venueAddress,
                ];
            })
            ->unique('full_address') // Remove duplicate locations
            ->values()
            ->toArray();

        return $locations;
    }
}
