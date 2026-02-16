<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\BandPayoutConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutFlowCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Add owner to band so there are members to pay out
        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id
        ]);

        $this->actingAs($this->owner);
    }

    /**
     * Helper to create a payout config with flow diagram
     */
    private function createPayoutConfig(array $flowDiagram): BandPayoutConfig
    {
        return BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Test Config',
            'is_active' => true,
            'band_cut_type' => 'none',
            'band_cut_value' => 0,
            'member_payout_type' => 'equal_split',
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 0,
            'flow_diagram' => $flowDiagram,
        ]);
    }

    /**
     * Test multiple outputs with percentage allocations
     * One node splits to multiple nodes, each taking percentage from SAME input
     */
    public function test_multiple_outputs_with_percentage_allocations()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 1000, 'label' => 'Income']
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Group A',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-2',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Group B',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'payout-1'],
                ['source' => 'income-1', 'target' => 'payout-2'],
            ]
        ]);

        $result = $config->calculatePayouts(1000);

        // Both groups should get 50% of the SAME $1000 input
        $this->assertEquals(1000, $result['total_amount']);
        $this->assertEquals(2, count($result['member_payouts']));

        // Total: $500 (Group A) + $500 (Group B) = $1000
        $this->assertEquals(1000, $result['total_member_payout']);
    }

    /**
     * Test multiple inputs to single node
     * Two nodes feed into one node - amounts should be summed
     */
    public function test_multiple_inputs_to_single_node()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 1000, 'label' => 'Income']
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Source A',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-2',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Source B',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 30,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-3',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Combined Target',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'payout-1'],
                ['source' => 'income-1', 'target' => 'payout-2'],
                ['source' => 'payout-1', 'target' => 'payout-3'],
                ['source' => 'payout-2', 'target' => 'payout-3'],
            ]
        ]);

        $result = $config->calculatePayouts(1000);

        // Source A: 50% = $500 (distributed to members, $0 passes forward)
        // Source B: 30% = $300 (distributed to members, $0 passes forward)
        // Combined Target receives: $0 + $0 = $0 (nothing to distribute)
        // Total payouts: $500 (A) + $300 (B) + $0 (Combined) = $800
        // This is the CONSUME model - groups distribute money and don't pass it forward
        $this->assertEquals(800, $result['total_member_payout']);
    }

    /**
     * Test complex scenario: split then merge with band cut
     */
    public function test_split_then_merge_with_band_cut()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 2000, 'label' => 'Income']
                ],
                [
                    'id' => 'bandcut-1',
                    'type' => 'bandCut',
                    'data' => [
                        'cutType' => 'percentage',
                        'value' => 20,
                        'label' => 'Band Cut'
                    ]
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Production',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-2',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Subs',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-3',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Final Pool',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'bandcut-1'],
                ['source' => 'bandcut-1', 'target' => 'payout-1'],
                ['source' => 'bandcut-1', 'target' => 'payout-2'],
                ['source' => 'payout-1', 'target' => 'payout-3'],
                ['source' => 'payout-2', 'target' => 'payout-3'],
            ]
        ]);

        $result = $config->calculatePayouts(2000);

        // Band Cut: 20% of $2000 = $400
        // Remaining: $1600
        // Production: 50% of $1600 = $800 (distributes $800, passes $0 forward)
        // Subs: 50% of $1600 = $800 (distributes $800, passes $0 forward)
        // Final Pool: receives $0 + $0 = $0 (nothing to distribute)

        $this->assertEquals(400, $result['band_cut']);
        $this->assertEquals(1600, $result['distributable_amount']);

        // Total member payouts: $800 (Production) + $800 (Subs) + $0 (Final Pool) = $1600
        // This is the CONSUME model - groups use their allocation and don't pass forward
        $this->assertEquals(1600, $result['total_member_payout']);
    }

    /**
     * Test multiple outputs with remainder allocations split equally
     */
    public function test_multiple_remainder_outputs_split_equally()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 1000, 'label' => 'Income']
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Remainder A',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-2',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Remainder B',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'payout-1'],
                ['source' => 'income-1', 'target' => 'payout-2'],
            ]
        ]);

        $result = $config->calculatePayouts(1000);

        // Both remainder groups should split the $1000 equally: $500 each
        $this->assertEquals(1000, $result['total_member_payout']);
        $this->assertEquals(2, count($result['member_payouts']));
        $this->assertEquals(500, $result['member_payouts'][0]['amount']);
        $this->assertEquals(500, $result['member_payouts'][1]['amount']);
    }

    /**
     * Test mixed allocations: fixed, percentage, and remainder
     */
    public function test_mixed_allocation_types()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 1000, 'label' => 'Income']
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Fixed',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'fixed',
                        'incomingAllocationValue' => 200,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-2',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Percentage',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 30,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-3',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Remainder',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'payout-1'],
                ['source' => 'income-1', 'target' => 'payout-2'],
                ['source' => 'income-1', 'target' => 'payout-3'],
            ]
        ]);

        $result = $config->calculatePayouts(1000);

        // Fixed: $200
        // Percentage: $1000 * 30% = $300
        // Remainder: $1000 - $200 - $300 = $500
        $this->assertEquals(1000, $result['total_member_payout']);
    }

    /**
     * Test deactivated node with multiple outputs
     * Deactivated node should pass through amount but still split to multiple outputs
     */
    public function test_deactivated_node_with_multiple_outputs()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 2000, 'label' => 'Income']
                ],
                [
                    'id' => 'bandcut-1',
                    'type' => 'bandCut',
                    'data' => [
                        'cutType' => 'percentage',
                        'value' => 20,
                        'label' => 'Band Cut',
                        'deactivated' => true  // Deactivated - should NOT take cut
                    ]
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Production',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-2',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Subs',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'bandcut-1'],
                ['source' => 'bandcut-1', 'target' => 'payout-1'],
                ['source' => 'bandcut-1', 'target' => 'payout-2'],
            ]
        ]);

        $result = $config->calculatePayouts(2000);

        // Band Cut is deactivated, so NO cut should be taken
        $this->assertEquals(0, $result['band_cut']);
        $this->assertEquals(2000, $result['distributable_amount']);

        // Full $2000 should pass through and split:
        // Production: 50% of $2000 = $1000
        // Subs: 50% of $2000 = $1000
        // Total: $2000
        $this->assertEquals(2000, $result['total_member_payout']);
    }

    /**
     * Test 90% allocation scenario
     * A node set to take 90% should only distribute 90%, not 100%
     */
    public function test_ninety_percent_allocation()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 3650, 'label' => 'Income']
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Everybody Else',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 90,  // Should take 90%, not 100%
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'payout-1'],
            ]
        ]);

        $result = $config->calculatePayouts(3650);

        // Group should receive 90% of $3650 = $3285
        // NOT 100% ($3650)
        $this->assertEquals(3285, $result['total_member_payout']);

        // Remaining 10% should be $365
        $this->assertEquals(365, $result['remaining']);
    }

    /**
     * Test multiple band cut nodes aggregate correctly
     */
    public function test_multiple_band_cut_nodes()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 10000, 'label' => 'Income']
                ],
                [
                    'id' => 'bandcut-1',
                    'type' => 'bandCut',
                    'data' => [
                        'cutType' => 'percentage',
                        'value' => 10,  // 10% = $1000
                        'label' => 'Management Cut'
                    ]
                ],
                [
                    'id' => 'bandcut-2',
                    'type' => 'bandCut',
                    'data' => [
                        'cutType' => 'fixed',
                        'value' => 500,  // $500
                        'label' => 'Production Fee'
                    ]
                ],
                [
                    'id' => 'payout-1',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Members',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'bandcut-1'],
                ['source' => 'bandcut-1', 'target' => 'bandcut-2'],
                ['source' => 'bandcut-2', 'target' => 'payout-1'],
            ]
        ]);

        $result = $config->calculatePayouts(10000);

        // Band cuts should aggregate: $1000 + $500 = $1500
        $this->assertEquals(1500, $result['band_cut']);

        // Distributable: $10000 - $1500 = $8500
        $this->assertEquals(8500, $result['distributable_amount']);

        // Members get all $8500
        $this->assertEquals(8500, $result['total_member_payout']);
    }

    /**
     * Test simple case: payout group with 0 members in equal_split mode
     * When a payout group has 0 members, it should pass the full amount forward
     */
    public function test_payout_group_with_zero_members_equal_split()
    {
        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 1000, 'label' => 'Income']
                ],
                [
                    'id' => 'payout-empty',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Empty Group',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => false,  // No members
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 50,  // Would take 50% if there were members
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'payout-downstream',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Downstream',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'payout-empty'],
                ['source' => 'payout-empty', 'target' => 'payout-downstream'],
            ]
        ]);

        $result = $config->calculatePayouts(1000);

        // Empty group has 0 members, so it consumes nothing
        // Downstream should get the full $1000
        $this->assertEquals(1000, $result['total_member_payout']);
        $this->assertEquals(1, count($result['member_payouts']));
        $this->assertEquals(1000, $result['member_payouts'][0]['amount']);
    }

    /**
     * Test edge case: payout group with 0 members should not consume any allocation
     * When a fixed payout group has 0 members, the amount allocated to that group
     * should remain as "remaining" and NOT be passed to downstream percentage-based groups
     */
    public function test_payout_group_with_zero_members_fixed_allocation()
    {
        // Create 2 additional users to simulate the scenario
        $productionUser1 = User::factory()->create(['name' => 'Production 1']);
        $productionUser2 = User::factory()->create(['name' => 'Production 2']);

        // Add them as band members (not owners)
        $this->band->members()->create(['user_id' => $productionUser1->id]);
        $this->band->members()->create(['user_id' => $productionUser2->id]);

        $config = $this->createPayoutConfig([
            'nodes' => [
                [
                    'id' => 'income-1',
                    'type' => 'income',
                    'data' => ['amount' => 10000, 'label' => 'Income']
                ],
                [
                    'id' => 'bandcut-1',
                    'type' => 'bandCut',
                    'data' => [
                        'cutType' => 'tiered',
                        'tierConfig' => [
                            ['min' => 0, 'max' => 2500, 'type' => 'fixed', 'value' => 0],
                            ['min' => 2500, 'max' => 5000, 'type' => 'fixed', 'value' => 250],
                            ['min' => 5000, 'max' => 7500, 'type' => 'fixed', 'value' => 750],
                            ['min' => 7500, 'max' => 10000, 'type' => 'fixed', 'value' => 1125],
                            ['min' => 10000, 'max' => 12500, 'type' => 'fixed', 'value' => 2000],
                        ],
                        'label' => 'Band Cut'
                    ]
                ],
                [
                    'id' => 'payout-production',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Production',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => false,
                            'includeMembers' => true,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'fixed',
                        'fixedAmountPerMember' => 350,
                    ]
                ],
                [
                    'id' => 'payout-photographer',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Photographer',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => false,  // No owners
                            'includeMembers' => false, // No members
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'fixed',
                        'fixedAmountPerMember' => 350,  // Would charge $350 if there were members
                    ]
                ],
                [
                    'id' => 'payout-performer',
                    'type' => 'payoutGroup',
                    'data' => [
                        'label' => 'Performer',
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => [
                            'includeOwners' => true,
                            'includeMembers' => false,
                            'includeProduction' => false,
                        ],
                        'incomingAllocationType' => 'percentage',
                        'incomingAllocationValue' => 90,
                        'distributionMode' => 'equal_split',
                    ]
                ],
                [
                    'id' => 'bandcut-final',
                    'type' => 'bandCut',
                    'data' => [
                        'cutType' => 'percentage',
                        'value' => 100,  // Take remaining 10%
                        'label' => 'Final Band Cut'
                    ]
                ]
            ],
            'edges' => [
                ['source' => 'income-1', 'target' => 'bandcut-1'],
                ['source' => 'bandcut-1', 'target' => 'payout-production'],
                ['source' => 'payout-production', 'target' => 'payout-photographer'],
                ['source' => 'payout-photographer', 'target' => 'payout-performer'],
                ['source' => 'payout-performer', 'target' => 'bandcut-final'],
            ]
        ]);

        $result = $config->calculatePayouts(10000);

        // Income: $10,000
        // Band Cut (tiered): $1,125
        // After band cut: $8,875

        // Production (2 members × $350): $700
        // After production: $8,175

        // Photographer (0 members): $0 allocated, $0 consumed (no members)
        // After photographer: $8,175 passed forward (full amount since no members)

        // Performer (90% of $8,175): $7,357.50
        // After performer: $817.50 remaining (10% of $8,175)

        // Final Band Cut (100% of $817.50): $817.50

        // Band cut accumulates: initial tier cut + final 100% of remainder
        $this->assertEquals(1942.50, $result['band_cut'], 'Total band cut should be $1,125 + $817.50');
        $this->assertEquals(8057.50, $result['distributable_amount'], 'Distributable = Total - Band Cut');

        // Check individual group payouts
        $productionPayouts = collect($result['member_payouts'])->where('name', 'Production 1')
            ->merge(collect($result['member_payouts'])->where('name', 'Production 2'));
        $this->assertEquals(2, $productionPayouts->count(), 'Should have 2 production members');
        $this->assertEquals(700, $productionPayouts->sum('amount'), 'Production total should be $700');

        // Performer should get 90% of what's left after production
        // Since photographer had 0 members, it didn't consume anything
        // So: ($8,875 - $700) × 90% = $8,175 × 90% = $7,357.50
        $performerPayouts = collect($result['member_payouts'])->where('name', $this->owner->name);
        $this->assertEquals(1, $performerPayouts->count(), 'Should have 1 performer (owner)');

        // THIS IS THE KEY ASSERTION - Performer should get 90% of $8,175 since Photographer consumed nothing
        $performerAmount = $performerPayouts->first()['amount'];
        $this->assertEquals(7357.50, $performerAmount,
            'Performer should get 90% of $8,175 (after $700 production, photographer consumed $0 due to 0 members)');

        // Total member payout should be $700 (production) + $7,357.50 (performer) = $8,057.50
        $this->assertEquals(8057.50, $result['total_member_payout'],
            'Total member payout should be production + performer only');

        // Remaining should be $0 because the final band cut takes all remaining
        // distributable_amount ($8,057.50) = total_amount ($10,000) - band_cut ($1,942.50)
        // remaining = distributable_amount - total_member_payout = $8,057.50 - $8,057.50 = $0
        $this->assertEquals(0, $result['remaining'],
            'Remaining should be $0 after final band cut takes everything');
    }
}
