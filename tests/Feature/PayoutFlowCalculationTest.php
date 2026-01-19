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
}
