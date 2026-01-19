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
        'flow_diagram',
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
        'flow_diagram' => 'array',
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
        // If this config has a flow_diagram, use flow-based calculation
        if ($this->flow_diagram && is_array($this->flow_diagram) && isset($this->flow_diagram['nodes'])) {
            return $this->calculateFromFlow($totalAmount, $booking);
        }

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
            return $this->calculatePayoutsWithGroups($result, $booking);
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
                                    'role' => $attendance['role'] ?? null,
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
                                            'role' => $attendance['role'] ?? null,
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
    private function calculatePayoutsWithGroups(array $result, ?Bookings $booking = null): array
    {
        // Calculate attendance weights with role information if booking is provided
        $attendanceData = collect();
        if ($booking) {
            $attendanceData = $this->calculateAttendanceWeights($booking);
        }

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
            $groupPayoutResult = $group->calculateGroupPayout($groupAllocation, $attendanceData);
            
            $result['payment_group_payouts'][] = $groupPayoutResult;
            
            // Add to member payouts
            foreach ($groupPayoutResult['payouts'] as $payout) {
                $result['member_payouts'][] = [
                    'type' => 'payment_group',
                    'group_name' => $group->name,
                    'name' => $payout['user_name'],
                    'user_id' => $payout['user_id'],
                    'role' => $payout['role'] ?? null,
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

        // Calculate total value across all events
        $totalValue = $events->sum(function ($event) {
            if ($event->value === null) {
                return 0;
            }
            return is_string($event->value) ? floatval($event->value) : $event->value;
        });

        // If no events have values, fall back to equal weighting
        $useValueWeighting = $totalValue > 0;

        // Aggregate attendance across all events
        $attendance = [];

        foreach ($events as $event) {
            // Get event value for weighting (convert to float)
            $eventValue = 0;
            if ($useValueWeighting && $event->value !== null) {
                $eventValue = is_string($event->value) ? floatval($event->value) : $event->value;
            }

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

                    // Get role/instrument using the role_name accessor which handles fallbacks
                    $role = $eventMember->role_name;
                    $bandRoleId = $eventMember->band_role_id;

                    // Fallback band_role_id from rosterMember if not set directly
                    if (!$bandRoleId && $eventMember->rosterMember) {
                        $bandRoleId = $eventMember->rosterMember->band_role_id;
                    }

                    $attendance[$key] = [
                        'roster_member_id' => $eventMember->roster_member_id,
                        'user_id' => $eventMember->user_id,
                        'name' => $eventMember->display_name,
                        'role' => $role,
                        'band_role_id' => $bandRoleId,
                        'type' => $type,
                        'events_attended' => 0,
                        'total_events' => $totalEvents,
                        'value_attended' => 0, // Track total value of events attended
                        'custom_payout' => null,
                        'custom_payout_sum' => 0,
                    ];
                }

                $attendance[$key]['events_attended']++;

                // Track value of events attended
                if ($useValueWeighting) {
                    $attendance[$key]['value_attended'] += $eventValue;
                }

                // Track custom payouts (we'll average them or use the most common)
                if ($eventMember->payout_amount) {
                    $attendance[$key]['custom_payout_sum'] += $eventMember->payout_amount / 100; // Convert cents to dollars
                }
            }
        }

        // Calculate weights and finalize custom payouts
        return collect($attendance)->map(function ($item) use ($totalValue, $useValueWeighting) {
            // Use value-based weighting if events have values, otherwise use count-based
            if ($useValueWeighting) {
                $item['weight'] = $totalValue > 0 ? $item['value_attended'] / $totalValue : 0;
            } else {
                $item['weight'] = $item['events_attended'] / $item['total_events'];
            }

            // If custom payouts were set, average them across attended events
            if ($item['custom_payout_sum'] > 0) {
                $item['custom_payout'] = $item['custom_payout_sum'] / $item['events_attended'];
            }

            unset($item['custom_payout_sum']);
            unset($item['value_attended']); // Clean up internal tracking field

            return $item;
        })->values();
    }

    /**
     * Calculate payouts from flow diagram
     */
    private function calculateFromFlow(float $totalAmount, ?Bookings $booking = null): array
    {
        // Ensure band relationship is loaded for member access
        if (!$this->relationLoaded('band')) {
            $this->load('band.owners', 'band.members');
        } elseif (!$this->band->relationLoaded('owners') || !$this->band->relationLoaded('members')) {
            $this->band->load('owners', 'members');
        }

        $nodes = $this->flow_diagram['nodes'] ?? [];
        $edges = $this->flow_diagram['edges'] ?? [];

        $result = [
            'total_amount' => $totalAmount,
            'band_cut' => 0,
            'distributable_amount' => $totalAmount,
            'member_payouts' => [],
            'payment_group_payouts' => [],
            'total_member_payout' => 0,
            'remaining' => 0,
        ];

        // Get attendance data with roles if booking provided
        $attendanceData = collect();
        if ($booking) {
            $attendanceData = $this->calculateAttendanceWeights($booking);
        }

        // Band cuts will be calculated during traversal
        $remainingAmount = $totalAmount;

        // Build adjacency lists (both directions)
        $adjacency = collect($edges)->groupBy('source'); // outgoing
        $incomingEdges = collect($edges)->groupBy('target'); // incoming

        // Find income node
        $incomeNode = collect($nodes)->firstWhere('type', 'income');
        if (!$incomeNode) {
            return $result;
        }

        // Find all reachable nodes from the income node
        $reachableNodes = collect();
        $findReachable = function($nodeId) use (&$findReachable, &$reachableNodes, $adjacency) {
            if ($reachableNodes->contains($nodeId)) return;
            $reachableNodes->push($nodeId);

            $outgoing = $adjacency->get($nodeId, collect());
            foreach ($outgoing as $edge) {
                $findReachable($edge['target']);
            }
        };
        $findReachable($incomeNode['id']);

        // Track nodes with multiple inputs
        // Only count incoming edges from reachable nodes
        $nodeInputs = [];
        foreach ($nodes as $node) {
            if (!$reachableNodes->contains($node['id'])) continue; // Skip unreachable nodes

            $incoming = $incomingEdges->get($node['id'], collect());
            $reachableIncoming = $incoming->filter(fn($edge) => $reachableNodes->contains($edge['source']));

            if ($reachableIncoming->count() > 1) {
                $nodeInputs[$node['id']] = [
                    'received' => 0,
                    'amounts' => [],
                    'expected' => $reachableIncoming->count(),
                ];
            }
        }

        // Traverse from income node
        $this->traverseFlowNode($incomeNode['id'], $remainingAmount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs);

        // Calculate distributable amount after all band cuts
        $result['distributable_amount'] = $result['total_amount'] - $result['band_cut'];

        $result['total_member_payout'] = collect($result['member_payouts'])->sum('amount');
        $result['remaining'] = $result['distributable_amount'] - $result['total_member_payout'];

        return $result;
    }

    /**
     * Traverse flow nodes recursively
     */
    private function traverseFlowNode(string $nodeId, float $amount, array $nodes, $adjacency, $attendanceData, array &$result, array &$nodeInputs = [], bool $allocationApplied = false): void
    {
        // Check if this node has multiple inputs and needs accumulation
        if (isset($nodeInputs[$nodeId])) {
            $nodeInputs[$nodeId]['amounts'][] = $amount;
            $nodeInputs[$nodeId]['received']++;

            // If we haven't received all inputs yet, wait
            if ($nodeInputs[$nodeId]['received'] < $nodeInputs[$nodeId]['expected']) {
                return; // Don't process yet, more inputs coming
            }

            // All inputs received - sum them up
            $amount = array_sum($nodeInputs[$nodeId]['amounts']);
        }

        $node = collect($nodes)->firstWhere('id', $nodeId);
        if (!$node) return;

        // Bypass deactivated nodes - pass amount through unchanged but still split to multiple outputs
        if (isset($node['data']['deactivated']) && $node['data']['deactivated']) {
            $outgoingEdges = $adjacency->get($nodeId, collect());
            $this->traverseMultipleOutputs($outgoingEdges, $amount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs);
            return;
        }

        // Process income nodes (just pass through)
        if ($node['type'] === 'income') {
            $outgoingEdges = $adjacency->get($nodeId, collect());
            $this->traverseMultipleOutputs($outgoingEdges, $amount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs);
            return;
        }

        // Process bandCut nodes
        if ($node['type'] === 'bandCut') {
            $nodeData = $node['data'];

            // Skip if deactivated
            if ($nodeData['deactivated'] ?? false) {
                // Pass through without taking a cut
                $outgoingEdges = $adjacency->get($nodeId, collect());
                $this->traverseMultipleOutputs($outgoingEdges, $amount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs);
                return;
            }

            // Calculate band cut
            $cutType = $nodeData['cutType'] ?? 'none';
            $cutValue = $nodeData['value'] ?? 0;
            $bandCut = 0;

            if ($cutType === 'percentage') {
                $bandCut = ($amount * $cutValue) / 100;
            } elseif ($cutType === 'fixed') {
                $bandCut = $cutValue;
            }

            // Accumulate band cut
            $result['band_cut'] += $bandCut;
            $amount -= $bandCut;

            // Continue to next nodes
            $outgoingEdges = $adjacency->get($nodeId, collect());
            $this->traverseMultipleOutputs($outgoingEdges, $amount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs);
            return;
        }

        // Process payoutGroup nodes
        if ($node['type'] === 'payoutGroup') {
            $nodeData = $node['data'];

            // Calculate how much this group should take based on allocation settings
            $groupAllocation = $amount;

            // Only apply allocation if it wasn't already applied by parent (multiple outputs scenario)
            if (!$allocationApplied) {
                // Apply incoming allocation if specified (single output scenario)
                $allocationType = $nodeData['incomingAllocationType'] ?? 'remainder';
                $allocationValue = $nodeData['incomingAllocationValue'] ?? 0;

                if ($allocationType === 'percentage') {
                    $groupAllocation = ($amount * $allocationValue) / 100;
                } elseif ($allocationType === 'fixed') {
                    $groupAllocation = min($allocationValue, $amount);
                }
                // 'remainder' type means take everything available
            }

            // Get members based on source type
            $sourceType = $nodeData['sourceType'] ?? 'roster';
            $membersToDistribute = collect();

            if ($sourceType === 'allMembers') {
                // Get all members from band directly
                $allMembersConfig = $nodeData['allMembersConfig'] ?? [];
                if ($allMembersConfig['includeOwners'] ?? true) {
                    foreach ($this->band->owners as $owner) {
                        $membersToDistribute->push([
                            'type' => 'owner',
                            'name' => $owner->name,
                            'user_id' => $owner->id,
                            'roster_member_id' => null,
                            'role' => null,
                            'band_role_id' => null,
                            'events_attended' => 1,
                            'total_events' => 1,
                        ]);
                    }
                }
                if ($allMembersConfig['includeMembers'] ?? true) {
                    foreach ($this->band->members as $member) {
                        $membersToDistribute->push([
                            'type' => 'member',
                            'name' => $member->name,
                            'user_id' => $member->id,
                            'roster_member_id' => null,
                            'role' => null,
                            'band_role_id' => null,
                            'events_attended' => 1,
                            'total_events' => 1,
                        ]);
                    }
                }
                if ($allMembersConfig['includeProduction'] ?? false) {
                    $productionCount = $allMembersConfig['productionCount'] ?? 0;
                    for ($i = 0; $i < $productionCount; $i++) {
                        $membersToDistribute->push([
                            'type' => 'production',
                            'name' => "Production Member " . ($i + 1),
                            'user_id' => null,
                            'roster_member_id' => null,
                            'role' => null,
                            'band_role_id' => null,
                            'events_attended' => 1,
                            'total_events' => 1,
                        ]);
                    }
                }

                $filteredMembers = $membersToDistribute;
            } else {
                // roster source type - filter from attendance data
                $rosterConfig = $nodeData['rosterConfig'] ?? [];
                $filterByRole = $rosterConfig['filterByRole'] ?? [];
                $filterByRoleId = $rosterConfig['filterByRoleId'] ?? [];

                // Backwards compatibility: convert old includeSubstitutes to new memberTypeFilter
                if (isset($rosterConfig['includeSubstitutes']) && !isset($rosterConfig['memberTypeFilter'])) {
                    $memberTypeFilter = $rosterConfig['includeSubstitutes'] ? 'all' : 'members_only';
                } else {
                    $memberTypeFilter = $rosterConfig['memberTypeFilter'] ?? 'all';
                }

                $filteredMembers = $attendanceData->filter(function ($member) use ($filterByRole, $filterByRoleId, $memberTypeFilter) {
                    // Filter by member type
                    if ($memberTypeFilter === 'members_only' && $member['type'] === 'substitute') {
                        return false;
                    }
                    if ($memberTypeFilter === 'substitutes_only' && $member['type'] === 'member') {
                        return false;
                    }

                    // Filter by role if specified
                    // Prefer band_role_id filtering (new way), fall back to text role (old way for backward compatibility)
                    if (!empty($filterByRoleId)) {
                        return in_array($member['band_role_id'], $filterByRoleId);
                    } elseif (!empty($filterByRole)) {
                        return in_array($member['role'], $filterByRole);
                    }

                    return true; // No role filter specified
                });
            }

            // Distribute allocation based on distribution mode
            $distributionMode = $nodeData['distributionMode'] ?? 'equal_split';
            $memberCount = $filteredMembers->count();

            if ($memberCount > 0) {
                if ($distributionMode === 'fixed') {
                    $fixedAmount = $nodeData['fixedAmountPerMember'] ?? 0;
                    $totalPaid = 0;
                    foreach ($filteredMembers as $member) {
                        // Multiply fixed amount by number of events attended
                        // This implements "per-event" payment (e.g., $100 per event × 2 events = $200)
                        $eventsAttended = $member['events_attended'] ?? 1;
                        $memberAmount = $fixedAmount * $eventsAttended;

                        $result['member_payouts'][] = [
                            'type' => $member['type'],
                            'name' => $member['name'],
                            'user_id' => $member['user_id'],
                            'roster_member_id' => $member['roster_member_id'],
                            'role' => $member['role'],
                            'amount' => max($memberAmount, $this->minimum_payout),
                            'payout_type' => 'fixed',
                            'events_attended' => $member['events_attended'] ?? null,
                            'total_events' => $member['total_events'] ?? null,
                            'weight' => $member['weight'] ?? null,
                        ];
                        $totalPaid += $memberAmount;
                    }
                    $groupAllocation = $totalPaid;
                } elseif ($distributionMode === 'equal_split') {
                    $perMemberAmount = $groupAllocation / $memberCount;
                    foreach ($filteredMembers as $member) {
                        $result['member_payouts'][] = [
                            'type' => $member['type'],
                            'name' => $member['name'],
                            'user_id' => $member['user_id'],
                            'roster_member_id' => $member['roster_member_id'],
                            'role' => $member['role'],
                            'amount' => max($perMemberAmount, $this->minimum_payout),
                            'payout_type' => 'equal_split',
                            'events_attended' => $member['events_attended'] ?? null,
                            'total_events' => $member['total_events'] ?? null,
                            'weight' => $member['weight'] ?? null,
                        ];
                    }
                }
            }

            // Continue with remaining amount
            $remainingAmount = $amount - $groupAllocation;

            // Traverse outgoing edges with multiple output support
            $outgoingEdges = $adjacency->get($nodeId, collect());
            $this->traverseMultipleOutputs($outgoingEdges, $remainingAmount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs);
        } else {
            // For non-payoutGroup nodes, traverse to next nodes
            $outgoingEdges = $adjacency->get($nodeId, collect());
            $this->traverseMultipleOutputs($outgoingEdges, $amount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs);
        }
    }

    /**
     * Handle multiple outputs from a single node
     * All allocations are calculated from the SAME input amount
     */
    private function traverseMultipleOutputs($edges, float $inputAmount, array $nodes, $adjacency, $attendanceData, array &$result, array &$nodeInputs): void
    {
        if ($edges->isEmpty()) {
            return;
        }

        if ($edges->count() === 1) {
            // Single output - pass full amount, let target node handle allocation
            $edge = $edges->first();
            // Mark that allocation was NOT pre-applied (allocationApplied = false)
            $this->traverseFlowNode($edge['target'], $inputAmount, $nodes, $adjacency, $attendanceData, $result, $nodeInputs, false);
            return;
        }

        // Multiple outputs - calculate distributions from SAME input
        $distributions = [];
        $totalAllocated = 0;

        // Collect allocation info for each target
        $targets = $edges->map(function ($edge) use ($nodes) {
            $targetNode = collect($nodes)->firstWhere('id', $edge['target']);
            return [
                'edge' => $edge,
                'allocationType' => $targetNode['data']['incomingAllocationType'] ?? 'remainder',
                'allocationValue' => $targetNode['data']['incomingAllocationValue'] ?? 0,
            ];
        });

        // Separate by allocation type
        $remainderNodes = $targets->where('allocationType', 'remainder');
        $fixedNodes = $targets->where('allocationType', 'fixed');
        $percentageNodes = $targets->where('allocationType', 'percentage');

        // 1. Fixed amounts first (exact amounts)
        foreach ($fixedNodes as $target) {
            $amount = min($target['allocationValue'], $inputAmount - $totalAllocated);
            $distributions[] = [
                'edge' => $target['edge'],
                'amount' => $amount,
            ];
            $totalAllocated += $amount;
        }

        // 2. Percentage amounts (from original input)
        foreach ($percentageNodes as $target) {
            $amount = ($inputAmount * $target['allocationValue']) / 100;
            $distributions[] = [
                'edge' => $target['edge'],
                'amount' => $amount,
            ];
            $totalAllocated += $amount;
        }

        // 3. Remainder nodes split what's left equally
        if ($remainderNodes->isNotEmpty()) {
            $remainingAmount = max(0, $inputAmount - $totalAllocated);
            $perNode = $remainingAmount / $remainderNodes->count();

            foreach ($remainderNodes as $target) {
                $distributions[] = [
                    'edge' => $target['edge'],
                    'amount' => $perNode,
                ];
            }
        }

        // Traverse each branch with its calculated amount
        // Mark that allocation was pre-applied (allocationApplied = true)
        foreach ($distributions as $dist) {
            $this->traverseFlowNode($dist['edge']['target'], $dist['amount'], $nodes, $adjacency, $attendanceData, $result, $nodeInputs, true);
        }
    }
}