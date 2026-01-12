<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandPayoutConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'name',
        'is_active',
        'band_cut_type',
        'band_cut_value',
        'band_cut_tier_config',
        'member_payout_type',
        'tier_config',
        'regular_member_count',
        'production_member_count',
        'production_member_types',
        'member_specific_config',
        'include_owners',
        'include_members',
        'minimum_payout',
        'notes',
        'use_payment_groups',
        'payment_group_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'band_cut_value' => 'decimal:2',
        'band_cut_tier_config' => 'array',
        'tier_config' => 'array',
        'production_member_types' => 'array',
        'member_specific_config' => 'array',
        'include_owners' => 'boolean',
        'include_members' => 'boolean',
        'minimum_payout' => 'decimal:2',
        'regular_member_count' => 'integer',
        'production_member_count' => 'integer',
        'use_payment_groups' => 'boolean',
        'payment_group_config' => 'array',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class);
    }

    /**
     * Calculate payout distribution for a given amount
     *
     * @param float $totalAmount The amount to distribute
     * @param array|null $memberCounts Optional array with ['owners' => int, 'members' => int] to avoid N+1 queries
     * @param Bookings|null $booking Optional booking to check event attendance for weighted payouts
     */
    public function calculatePayouts(float $totalAmount, ?array $memberCounts = null, ?Bookings $booking = null): array
    {
        $result = [
            'total_amount' => $totalAmount,
            'band_cut' => 0,
            'distributable_amount' => $totalAmount,
            'member_payouts' => [],
            'payment_group_payouts' => [],
            'total_member_payout' => 0,
            'remaining' => 0,
        ];

        // Calculate band's cut
        if ($this->band_cut_type === 'percentage') {
            $result['band_cut'] = ($totalAmount * $this->band_cut_value) / 100;
        } elseif ($this->band_cut_type === 'fixed') {
            $result['band_cut'] = $this->band_cut_value;
        } elseif ($this->band_cut_type === 'tiered' && $this->band_cut_tier_config && is_array($this->band_cut_tier_config)) {
            $applicableTier = $this->findApplicableTier($totalAmount, $this->band_cut_tier_config);
            if ($applicableTier) {
                if ($applicableTier['type'] === 'percentage') {
                    $result['band_cut'] = ($totalAmount * $applicableTier['value']) / 100;
                } else {
                    $result['band_cut'] = $applicableTier['value'];
                }
            }
        }

        $result['distributable_amount'] = $totalAmount - $result['band_cut'];

        // If using payment groups, calculate based on groups
        if ($this->use_payment_groups && $this->payment_group_config && is_array($this->payment_group_config)) {
            return $this->calculatePayoutsWithGroups($result);
        }

        // Check if we have member-specific configurations
        if ($this->member_specific_config && is_array($this->member_specific_config) && count($this->member_specific_config) > 0) {
            // Use member-specific configurations
            foreach ($this->member_specific_config as $memberConfig) {
                $payoutType = $memberConfig['payout_type'] ?? 'equal_split';
                $amount = 0;

                if ($payoutType === 'percentage') {
                    $amount = ($result['distributable_amount'] * $memberConfig['value']) / 100;
                } elseif ($payoutType === 'fixed') {
                    $amount = $memberConfig['value'];
                } elseif ($payoutType === 'equal_split') {
                    // This will be calculated after we know total equal split members
                    continue;
                }

                $result['member_payouts'][] = [
                    'type' => $memberConfig['member_type'] ?? 'member',
                    'name' => $memberConfig['name'] ?? 'Unknown',
                    'user_id' => $memberConfig['user_id'] ?? null,
                    'payout_type' => $payoutType,
                    'amount' => max($amount, $this->minimum_payout),
                ];
            }

            // Handle production member types
            if ($this->production_member_types && is_array($this->production_member_types)) {
                foreach ($this->production_member_types as $prodMember) {
                    $payoutType = $prodMember['type'] ?? 'fixed';
                    $amount = 0;

                    if ($payoutType === 'percentage') {
                        $amount = ($result['distributable_amount'] * $prodMember['value']) / 100;
                    } else {
                        $amount = $prodMember['value'];
                    }

                    $result['member_payouts'][] = [
                        'type' => 'production',
                        'name' => $prodMember['name'] ?? 'Production Member',
                        'payout_type' => $payoutType,
                        'amount' => max($amount, $this->minimum_payout),
                    ];
                }
            }

            $result['total_member_payout'] = array_sum(array_column($result['member_payouts'], 'amount'));
        } else {
            // Fallback to old calculation methods
            $memberCount = 0;
            $attendanceWeights = collect();

            // Check if booking has attendance tracking across events
            if ($booking) {
                $attendanceWeights = $this->calculateAttendanceWeights($booking);
                $memberCount = $attendanceWeights->count();
            } else {
                // Use default band member counts
                if ($memberCounts !== null) {
                    if ($this->include_owners) {
                        $memberCount += $memberCounts['owners'] ?? 0;
                    }
                    if ($this->include_members) {
                        $memberCount += $memberCounts['members'] ?? 0;
                    }
                } else {
                    // Fallback to querying (will cause N+1 if used in loops)
                    if ($this->include_owners) {
                        $memberCount += $this->band->owners()->count();
                    }
                    if ($this->include_members) {
                        $memberCount += $this->band->members()->count();
                    }
                }

                if ($this->production_member_count > 0) {
                    $memberCount += $this->production_member_count;
                }
            }

            if ($memberCount > 0) {
                switch ($this->member_payout_type) {
                    case 'equal_split':
                        // If using attendance-based weights
                        if ($attendanceWeights->isNotEmpty()) {
                            $totalWeight = $attendanceWeights->sum('weight');

                            foreach ($attendanceWeights as $attendance) {
                                // Calculate weighted payout
                                $weightedAmount = ($result['distributable_amount'] * $attendance['weight']) / $totalWeight;

                                // Use custom payout if set
                                if ($attendance['custom_payout']) {
                                    $amount = $attendance['custom_payout'];
                                } else {
                                    $amount = $weightedAmount;
                                }

                                $result['member_payouts'][] = [
                                    'type' => $attendance['type'],
                                    'name' => $attendance['name'],
                                    'user_id' => $attendance['user_id'],
                                    'roster_member_id' => $attendance['roster_member_id'],
                                    'amount' => max($amount, $this->minimum_payout),
                                    'payout_type' => 'attendance_weighted',
                                    'events_attended' => $attendance['events_attended'],
                                    'total_events' => $attendance['total_events'],
                                    'weight' => $attendance['weight'],
                                ];
                            }
                        } else {
                            // Use default band member counting
                            $perMemberAmount = $result['distributable_amount'] / $memberCount;
                            $ownerCount = $memberCounts !== null ? ($memberCounts['owners'] ?? 0) : $this->band->owners()->count();
                            $memberOnlyCount = $memberCounts !== null ? ($memberCounts['members'] ?? 0) : $this->band->members()->count();

                            for ($i = 0; $i < $memberCount; $i++) {
                                $result['member_payouts'][] = [
                                    'type' => $i < $ownerCount ? 'owner' :
                                             ($i < ($ownerCount + $memberOnlyCount) ? 'member' : 'production'),
                                    'amount' => $perMemberAmount,
                                    'payout_type' => 'equal_split',
                                ];
                            }
                        }
                        $result['total_member_payout'] = array_sum(array_column($result['member_payouts'], 'amount'));
                        break;

                    case 'tiered':
                        if ($this->tier_config && is_array($this->tier_config)) {
                            $applicableTier = $this->findApplicableTier($totalAmount);
                            if ($applicableTier) {
                                if ($applicableTier['type'] === 'percentage') {
                                    $perMemberAmount = ($result['distributable_amount'] * $applicableTier['value']) / (100 * $memberCount);
                                } else {
                                    $perMemberAmount = $applicableTier['value'] / $memberCount;
                                }

                                // If using attendance-based weights
                                if ($attendanceWeights->isNotEmpty()) {
                                    $totalWeight = $attendanceWeights->sum('weight');

                                    foreach ($attendanceWeights as $attendance) {
                                        // Calculate weighted payout
                                        $weightedAmount = ($result['distributable_amount'] * $attendance['weight']) / $totalWeight;

                                        // Use custom payout if set, otherwise weighted amount
                                        if ($attendance['custom_payout']) {
                                            $amount = $attendance['custom_payout'];
                                        } else {
                                            $amount = $weightedAmount;
                                        }

                                        $result['member_payouts'][] = [
                                            'type' => $attendance['type'],
                                            'name' => $attendance['name'],
                                            'user_id' => $attendance['user_id'],
                                            'roster_member_id' => $attendance['roster_member_id'],
                                            'amount' => max($amount, $this->minimum_payout),
                                            'payout_type' => 'attendance_weighted_tiered',
                                            'events_attended' => $attendance['events_attended'],
                                            'total_events' => $attendance['total_events'],
                                            'weight' => $attendance['weight'],
                                        ];
                                    }
                                } else {
                                    // Use default band member counting
                                    $ownerCount = $memberCounts !== null ? ($memberCounts['owners'] ?? 0) : $this->band->owners()->count();
                                    $memberOnlyCount = $memberCounts !== null ? ($memberCounts['members'] ?? 0) : $this->band->members()->count();

                                    for ($i = 0; $i < $memberCount; $i++) {
                                        $result['member_payouts'][] = [
                                            'type' => $i < $ownerCount ? 'owner' :
                                                     ($i < ($ownerCount + $memberOnlyCount) ? 'member' : 'production'),
                                            'amount' => max($perMemberAmount, $this->minimum_payout),
                                            'payout_type' => 'tiered',
                                        ];
                                    }
                                }
                                $result['total_member_payout'] = array_sum(array_column($result['member_payouts'], 'amount'));
                            }
                        }
                        break;
                }
            }
        }

        $result['remaining'] = $result['distributable_amount'] - $result['total_member_payout'];

        return $result;
    }

    /**
     * Calculate payouts using payment groups
     * 
     * Groups are allocated SEQUENTIALLY based on display_order:
     * 1. Start with distributable_amount (after band cut)
     * 2. Allocate to first group (fixed or percentage of remaining)
     * 3. Subtract allocation from remaining
     * 4. Allocate to second group from what's left
     * 5. Continue until all groups processed
     * 
     * This allows formulas like: (net - band_cut - production_group) / player_group
     */
    private function calculatePayoutsWithGroups(array $result): array
    {
        $remainingAmount = $result['distributable_amount'];
        $paymentGroups = BandPaymentGroup::where('band_id', $this->band_id)
            ->where('is_active', true)
            ->with('users')
            ->orderBy('display_order')
            ->get();

        foreach ($paymentGroups as $group) {
            $groupConfig = collect($this->payment_group_config)->firstWhere('group_id', $group->id);
            
            if (!$groupConfig) {
                continue;
            }

            // Calculate group allocation from REMAINING amount (sequential allocation)
            $groupAllocation = 0;
            if ($groupConfig['allocation_type'] === 'percentage') {
                $groupAllocation = ($remainingAmount * $groupConfig['allocation_value']) / 100;
            } elseif ($groupConfig['allocation_type'] === 'fixed') {
                $groupAllocation = $groupConfig['allocation_value'];
            }

            // Calculate individual member payouts within the group
            $groupPayoutResult = $group->calculateGroupPayout($groupAllocation);
            
            $result['payment_group_payouts'][] = $groupPayoutResult;
            
            // Add to member payouts
            foreach ($groupPayoutResult['payouts'] as $payout) {
                $result['member_payouts'][] = [
                    'type' => 'payment_group',
                    'group_name' => $group->name,
                    'name' => $payout['user_name'],
                    'user_id' => $payout['user_id'],
                    'payout_type' => $payout['payout_type'],
                    'amount' => max($payout['amount'], $this->minimum_payout),
                ];
            }
            
            $result['total_member_payout'] += $groupPayoutResult['total'];
            
            // SUBTRACT this group's allocation from remaining for next group
            $remainingAmount -= $groupPayoutResult['total'];
        }

        $result['remaining'] = $remainingAmount;
        return $result;
    }

    /**
     * Find the applicable tier based on the total amount
     */
    private function findApplicableTier(float $amount, ?array $tierConfig = null): ?array
    {
        $tiers = $tierConfig ?? $this->tier_config;
        
        if (!$tiers || !is_array($tiers)) {
            return null;
        }

        foreach ($tiers as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;
            
            if ($amount >= $min && $amount <= $max) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Calculate attendance weights for all attendees across booking's events
     *
     * Returns collection with:
     * - name: Display name
     * - user_id: User ID if applicable
     * - roster_member_id: Roster member ID
     * - type: member/substitute/etc
     * - events_attended: Number of events attended
     * - total_events: Total events in booking
     * - weight: Attendance weight (1.0 = attended all events)
     * - custom_payout: Custom payout amount if set (in dollars)
     */
    private function calculateAttendanceWeights(Bookings $booking): \Illuminate\Support\Collection
    {
        $events = $booking->events()->with('eventMembers.rosterMember.user')->get();
        $totalEvents = $events->count();

        if ($totalEvents === 0) {
            return collect();
        }

        // Aggregate attendance across all events
        $attendance = [];

        foreach ($events as $event) {
            foreach ($event->eventMembers as $eventMember) {
                // Only count members who attended or are confirmed (skip absent/excused)
                if ($eventMember->isAbsent()) {
                    continue;
                }

                // Use user_id as primary key to avoid counting same person twice
                // Fallback to roster_member_id for non-registered roster members
                // Fallback to event_member.id for custom subs with no IDs
                if ($eventMember->user_id) {
                    $key = 'user_' . $eventMember->user_id;
                } elseif ($eventMember->roster_member_id) {
                    $key = 'roster_' . $eventMember->roster_member_id;
                } else {
                    $key = 'event_member_' . $eventMember->id;
                }

                if (!isset($attendance[$key])) {
                    // Determine member type:
                    // - Has rosterMember + isUser() → 'member' (regular band member)
                    // - Has rosterMember + NOT isUser() → 'substitute' (roster sub/guest)
                    // - No rosterMember + has user_id → 'member' (registered user added directly)
                    // - No rosterMember + no user_id → 'substitute' (custom name sub)
                    $type = $eventMember->rosterMember
                        ? ($eventMember->rosterMember->isUser() ? 'member' : 'substitute')
                        : ($eventMember->user_id ? 'member' : 'substitute');

                    // Get role/instrument - prioritize event member role, then roster member role
                    $role = $eventMember->role;
                    if (!$role && $eventMember->rosterMember) {
                        $role = $eventMember->rosterMember->role;
                    }

                    $attendance[$key] = [
                        'roster_member_id' => $eventMember->roster_member_id,
                        'user_id' => $eventMember->user_id,
                        'name' => $eventMember->display_name,
                        'role' => $role,
                        'type' => $type,
                        'events_attended' => 0,
                        'total_events' => $totalEvents,
                        'custom_payout' => null,
                        'custom_payout_sum' => 0,
                    ];
                }

                $attendance[$key]['events_attended']++;

                // Track custom payouts (we'll average them or use the most common)
                if ($eventMember->payout_amount) {
                    $attendance[$key]['custom_payout_sum'] += $eventMember->payout_amount / 100; // Convert cents to dollars
                }
            }
        }

        // Calculate weights and finalize custom payouts
        return collect($attendance)->map(function ($item) {
            $item['weight'] = $item['events_attended'] / $item['total_events'];

            // If custom payouts were set, average them across attended events
            if ($item['custom_payout_sum'] > 0) {
                $item['custom_payout'] = $item['custom_payout_sum'] / $item['events_attended'];
            }

            unset($item['custom_payout_sum']);

            return $item;
        })->values();
    }
}