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
        $ownedBands = $this->user->bandOwner()->get();
        $memberBands = $this->user->bandMember()->get();

        $allBands = $ownedBands->merge($memberBands)->unique('id');

        foreach ($allBands as $band) {
            // Determine user's join date for this band
            $joinDate = $this->getUserJoinDate($band);

            // Get all bookings for this band after the user joined
            $bookings = \App\Models\Bookings::where('band_id', $band->id)
                ->where('date', '>=', $joinDate)
                ->whereIn('status', ['confirmed']) // Only count actual bookings
                ->orderBy('date', 'desc')
                ->get();

            $bandTotal = 0;

            foreach ($bookings as $booking) {
                // Calculate user's share of this booking
                $userShare = $this->calculateUserShareFromBooking($band, $booking);

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
     */
    protected function calculateUserShareFromBooking($band, $booking): float
    {
        // Get the band's payout configuration
        $payoutConfig = \App\Models\BandPayoutConfig::where('band_id', $band->id)
            ->where('is_active', true)
            ->first();

        // Check if booking has a payout with adjustments
        $payout = $booking->payout()->with('adjustments')->first();
        
        // Use adjusted amount if payout exists, otherwise use base price
        $bookingPrice = $payout 
            ? $payout->adjusted_amount_float
            : (is_string($booking->price) ? floatval($booking->price) : $booking->price);

        if ($payoutConfig) {
            // Use the payout config to calculate distribution
            $distribution = $payoutConfig->calculatePayouts($bookingPrice);

            // Check if payouts have user_ids (payment groups) or not (old equal split)
            $hasUserIds = !empty($distribution['member_payouts']) && isset($distribution['member_payouts'][0]['user_id']);

            if ($hasUserIds) {
                // Payment groups: Find this user's share by user_id
                foreach ($distribution['member_payouts'] as $payout) {
                    if (isset($payout['user_id']) && $payout['user_id'] == $this->user->id) {
                        // Return amount in cents (payout amount is in dollars)
                        return $payout['amount'] * 100;
                    }
                }

                // User not in payment groups
                return 0;
            } else {
                // Old system: payouts by type (owner/member) without user_ids
                // Check if user is an owner or member and distribute accordingly
                $isOwner = $band->owners()->where('user_id', $this->user->id)->exists();
                $isMember = $band->members()->where('user_id', $this->user->id)->exists();

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

                // Count how many users of this type exist
                $typeCount = $userType === 'owner' ? $band->owners()->count() : $band->members()->count();

                // Sum all payouts for this type and divide by number of users of this type
                $totalForType = array_sum(array_column($typePayouts, 'amount'));
                $sharePerPerson = $typeCount > 0 ? $totalForType / $typeCount : 0;

                return $sharePerPerson * 100; // Convert to cents
            }
        } else {
            // No payout config - divide equally among all members
            $ownerCount = $band->owners()->count();
            $memberCount = $band->members()->count();
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
        // Get all bands the user owns or is a member of
        $ownedBands = $this->user->bandOwner()->get();
        $memberBands = $this->user->bandMember()->get();
        $allBands = $ownedBands->merge($memberBands)->unique('id');

        if ($allBands->isEmpty()) {
            return [
                'total_miles' => 0,
                'total_minutes' => 0,
                'event_count' => 0,
            ];
        }

        // Get event IDs for events after user joined each band
        $validEventIds = [];

        foreach ($allBands as $band) {
            $joinDate = $this->getUserJoinDate($band);

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
        // Get all bands the user owns or is a member of
        $ownedBands = $this->user->bandOwner()->get();
        $memberBands = $this->user->bandMember()->get();
        $allBands = $ownedBands->merge($memberBands)->unique('id');

        if ($allBands->isEmpty()) {
            return [];
        }

        $allEvents = collect();

        foreach ($allBands as $band) {
            $joinDate = $this->getUserJoinDate($band);

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
