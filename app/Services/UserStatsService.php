<?php

namespace App\Services;

use App\Models\User;
use App\Models\Events;
use App\Models\EventDistanceForMembers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UserStatsService
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
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

        // Get all bands the user owns or is a member of
        // Eager load counts and payout configs to avoid N+1 queries in payout calculations
        $ownedBands = $this->user->bandOwner()
            ->withCount(['owners', 'members'])
            ->with('activePayoutConfig')
            ->get();
        $memberBands = $this->user->bandMember()
            ->withCount(['owners', 'members'])
            ->with('activePayoutConfig')
            ->get();

        $allBands = $ownedBands->merge($memberBands)->unique('id');
        
        // Pre-calculate user's membership status and join dates for each band to avoid N+1 queries
        $userBandStatus = [];
        $bandJoinDates = [];
        foreach ($allBands as $band) {
            $userBandStatus[$band->id] = [
                'is_owner' => $ownedBands->contains('id', $band->id),
                'is_member' => $memberBands->contains('id', $band->id),
            ];
            $bandJoinDates[$band->id] = $this->getUserJoinDate($band);
        }

        foreach ($allBands as $band) {
            // Use cached join date
            $joinDate = $bandJoinDates[$band->id];

            // Get all bookings for this band after the user joined
            // Eager load payouts to avoid N+1 queries
            $bookings = \App\Models\Bookings::where('band_id', $band->id)
                ->where('date', '>=', $joinDate)
                ->whereIn('status', ['confirmed', 'pending']) // Only count confirmed and pending bookings
                ->with(['payout.adjustments'])
                ->orderBy('date', 'desc')
                ->get();

            $bandTotal = 0;

            foreach ($bookings as $booking) {
                // Calculate user's share of this booking
                $userShare = $this->calculateUserShareFromBooking($band, $booking, $userBandStatus[$band->id] ?? []);

                if ($userShare > 0) {
                    $bandTotal += $userShare;
                    $totalBookingCount++;

                    // Add to year earnings
                    $year = $booking->date->year;
                    if (!isset($yearEarnings[$year])) {
                        $yearEarnings[$year] = 0;
                    }
                    $yearEarnings[$year] += $userShare;

                    // Add detailed booking info to year grouping
                    if (!isset($bookingsByYear[$year])) {
                        $bookingsByYear[$year] = [];
                    }

                    $bookingsByYear[$year][] = [
                        'id' => $booking->id,
                        'booking_name' => $booking->name,
                        'band_name' => $band->name,
                        'band_id' => $band->id,
                        'venue_name' => $booking->venue_name ?? 'TBD',
                        'venue_address' => $booking->venue_address ?? '',
                        'date' => $booking->date->format('Y-m-d'),
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
     * Get all bands the user owns or is a member of
     */
    protected function getUserBands()
    {
        $ownedBands = $this->user->bandOwner()->get();
        $memberBands = $this->user->bandMember()->get();
        return $ownedBands->merge($memberBands)->unique('id');
    }

    /**
     * Get the user's join date for a specific band
     */
    protected function getUserJoinDate($band): Carbon
    {
        // Check if user is an owner
        $ownerPivot = DB::table('band_owners')
            ->where('band_id', $band->id)
            ->where('user_id', $this->user->id)
            ->first();

        // Check if user is a member
        $memberPivot = DB::table('band_members')
            ->where('band_id', $band->id)
            ->where('user_id', $this->user->id)
            ->first();

        // Return the earliest date (owner or member, whichever came first)
        $ownerDate = $ownerPivot ? Carbon::parse($ownerPivot->created_at) : null;
        $memberDate = $memberPivot ? Carbon::parse($memberPivot->created_at) : null;

        if ($ownerDate && $memberDate) {
            return $ownerDate->lt($memberDate) ? $ownerDate : $memberDate;
        }

        return $ownerDate ?? $memberDate ?? Carbon::now();
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
        foreach ($allBands as $band) {
            $bandJoinDates[$band->id] = $this->getUserJoinDate($band);
        }

        foreach ($allBands as $band) {
            $joinDate = $bandJoinDates[$band->id];

            // Get events for this band after the user joined (only past events)
            $bandEvents = Events::whereHasMorph('eventable', [\App\Models\Bookings::class, \App\Models\BandEvents::class], function ($query) use ($band) {
                $query->where('band_id', $band->id);
            })
            ->where('date', '>=', $joinDate)
            ->where('date', '<=', Carbon::now()) // Only count past events
            ->pluck('id')
            ->toArray();

            $validEventIds = array_merge($validEventIds, $bandEvents);
        }

        // Get distances only for valid events with distance tracking
        $distances = EventDistanceForMembers::where('user_id', $this->user->id)
            ->whereIn('event_id', $validEventIds)
            ->whereNotNull('miles')
            ->get();

        $totalMiles = $distances->sum('miles');
        $totalMinutes = $distances->sum('minutes');

        // Count all valid events (not just those with distance tracking)
        $eventCount = count(array_unique($validEventIds));

        return [
            'total_miles' => (int) $totalMiles,
            'total_minutes' => (int) $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'event_count' => $eventCount,
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
        foreach ($allBands as $band) {
            $bandJoinDates[$band->id] = $this->getUserJoinDate($band);
        }

        $allEvents = collect();

        foreach ($allBands as $band) {
            $joinDate = $bandJoinDates[$band->id];

            // Get events from bookings for this band after join date
            $bookingEvents = Events::whereHasMorph('eventable', [\App\Models\Bookings::class], function ($query) use ($band) {
                $query->where('band_id', $band->id);
            })
            ->with(['eventable' => function ($query) {
                $query->select('id', 'venue_name', 'venue_address', 'band_id');
            }])
            ->where('date', '>=', $joinDate)
            ->where('date', '<=', Carbon::now())
            ->get();

            // Get events from legacy band_events for this band after join date
            $bandEventEvents = Events::whereHasMorph('eventable', [\App\Models\BandEvents::class], function ($query) use ($band) {
                $query->where('band_id', $band->id);
            })
            ->with(['eventable' => function ($query) {
                $query->select('id', 'venue_name', 'venue_address', 'band_id');
            }])
            ->where('date', '>=', $joinDate)
            ->where('date', '<=', Carbon::now())
            ->get();

            $allEvents = $allEvents->concat($bookingEvents)->concat($bandEventEvents);
        }

        // Sort by date descending and limit to 100 for performance
        $locations = $allEvents
            ->sortByDesc('date')
            ->take(100)
            ->filter(function ($event) {
                return $event->eventable
                    && $event->eventable->venue_name
                    && $event->eventable->venue_address
                    && $event->eventable->venue_name !== 'TBD';
            })
            ->map(function ($event) {
                return [
                    'title' => $event->title,
                    'venue_name' => $event->eventable->venue_name,
                    'venue_address' => $event->eventable->venue_address,
                    'date' => $event->date->format('Y-m-d'),
                    'full_address' => $event->eventable->venue_name . ', ' . $event->eventable->venue_address,
                ];
            })
            ->unique('full_address') // Remove duplicate locations
            ->values()
            ->toArray();

        return $locations;
    }
}
