<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\BandPayoutConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payout Calculation Parity Tests
 *
 * These integration tests verify that the backend payout calculation
 * produces consistent results across different scenarios.
 *
 * To be used alongside frontend tests (payout-calculation-parity.test.js)
 * to ensure frontend and backend match.
 */
class PayoutCalculationParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and band
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Create band owner record
        \App\Models\BandOwners::create([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id
        ]);

        // Add some members
        $member1 = User::factory()->create(['name' => 'Member 1']);
        $member2 = User::factory()->create(['name' => 'Member 2']);
        \App\Models\BandMembers::create([
            'user_id' => $member1->id,
            'band_id' => $this->band->id
        ]);
        \App\Models\BandMembers::create([
            'user_id' => $member2->id,
            'band_id' => $this->band->id
        ]);
    }

    /**
     * Test simple percentage band cut
     */
    public function test_simple_percentage_band_cut()
    {
        $testAmount = 5000;
        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'position' => ['x' => 0, 'y' => 0],
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'position' => ['x' => 200, 'y' => 0],
                'data' => [
                    'label' => 'Band Cut',
                    'cutType' => 'percentage',
                    'value' => 10
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1']
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                'nodes' => $nodes,
                'edges' => $edges,
                'test_amount' => $testAmount
            ]);

        $response->assertOk();
        $result = $response->json();

        // Expected: 10% of $5000 = $500
        $this->assertEquals(500, $result['band_cut']);
        $this->assertEquals(4500, $result['remaining']);
    }

    /**
     * Test tiered band cut calculation
     * Tiers are FIXED AMOUNTS based on which bracket the booking price falls into
     */
    public function test_tiered_band_cut()
    {
        $testAmount = 3500;
        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'Tiered Band Cut',
                    'cutType' => 'tiered',
                    'tierConfig' => [
                        ['min' => 0, 'max' => 1000, 'type' => 'fixed', 'value' => 0],
                        ['min' => 1001, 'max' => 2000, 'type' => 'fixed', 'value' => 100],
                        ['min' => 2001, 'max' => 5000, 'type' => 'fixed', 'value' => 200],
                        ['min' => 5001, 'max' => PHP_FLOAT_MAX, 'type' => 'fixed', 'value' => 300]
                    ]
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1']
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                'nodes' => $nodes,
                'edges' => $edges,
                'test_amount' => $testAmount
            ]);

        $response->assertOk();
        $result = $response->json();

        // Expected: $3,500 falls in $2,001-5,000 tier → Band cut = $200 (fixed amount)
        $expectedBandCut = 200;

        $this->assertEquals($expectedBandCut, $result['band_cut'],
            "Tiered band cut should be $expectedBandCut but got {$result['band_cut']}");
    }

    /**
     * Test tiered band cut at various price points
     * Verifies correct tier selection based on booking price
     */
    public function test_tiered_band_cut_at_multiple_price_points()
    {
        // Tiers: $0-1000 = $0, $1001-2000 = $100, $2001-5000 = $200, $5001+ = $300
        $testCases = [
            ['amount' => 500, 'expectedBandCut' => 0],      // Falls in tier 1 ($0-1000) → $0
            ['amount' => 1000, 'expectedBandCut' => 0],     // At tier 1 boundary → $0
            ['amount' => 1001, 'expectedBandCut' => 100],   // At tier 2 boundary → $100
            ['amount' => 1500, 'expectedBandCut' => 100],   // Falls in tier 2 ($1001-2000) → $100
            ['amount' => 2500, 'expectedBandCut' => 200],   // Falls in tier 3 ($2001-5000) → $200
            ['amount' => 5000, 'expectedBandCut' => 200],   // At tier 3 upper boundary → $200
            ['amount' => 5001, 'expectedBandCut' => 300],   // At tier 4 boundary → $300
            ['amount' => 10000, 'expectedBandCut' => 300]   // Falls in tier 4 ($5001+) → $300
        ];

        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'Tiered Band Cut',
                    'cutType' => 'tiered',
                    'tierConfig' => [
                        ['min' => 0, 'max' => 1000, 'type' => 'fixed', 'value' => 0],
                        ['min' => 1001, 'max' => 2000, 'type' => 'fixed', 'value' => 100],
                        ['min' => 2001, 'max' => 5000, 'type' => 'fixed', 'value' => 200],
                        ['min' => 5001, 'max' => PHP_FLOAT_MAX, 'type' => 'fixed', 'value' => 300]
                    ]
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1']
        ];

        foreach ($testCases as $testCase) {
            $response = $this->actingAs($this->user)
                ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                    'nodes' => $nodes,
                    'edges' => $edges,
                    'test_amount' => $testCase['amount']
                ]);

            $response->assertOk();
            $result = $response->json();

            $this->assertEquals(
                $testCase['expectedBandCut'],
                $result['band_cut'],
                "For amount \${$testCase['amount']}, expected band cut \${$testCase['expectedBandCut']} but got \${$result['band_cut']}"
            );
        }
    }

    /**
     * Test percentage-based tiered band cut
     * Tiers apply PERCENTAGE cuts based on which bracket the booking price falls into
     */
    public function test_percentage_based_tiered_band_cut()
    {
        $testAmount = 3500;
        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'Percentage Tiered Band Cut',
                    'cutType' => 'tiered',
                    'tierConfig' => [
                        ['min' => 0, 'max' => 1000, 'type' => 'percentage', 'value' => 5],
                        ['min' => 1001, 'max' => 2000, 'type' => 'percentage', 'value' => 10],
                        ['min' => 2001, 'max' => 5000, 'type' => 'percentage', 'value' => 15],
                        ['min' => 5001, 'max' => PHP_FLOAT_MAX, 'type' => 'percentage', 'value' => 20]
                    ]
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1']
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                'nodes' => $nodes,
                'edges' => $edges,
                'test_amount' => $testAmount
            ]);

        $response->assertOk();
        $result = $response->json();

        // Expected: $3,500 falls in $2,001-5,000 tier → 15% of $3,500 = $525
        $expectedBandCut = 525;

        $this->assertEquals($expectedBandCut, $result['band_cut'],
            "Percentage-based tiered band cut should be $expectedBandCut but got {$result['band_cut']}");
    }

    /**
     * Test percentage-based tiered band cut at various price points
     */
    public function test_percentage_tiered_band_cut_at_multiple_price_points()
    {
        // Tiers: $0-1000 = 5%, $1001-2000 = 10%, $2001-5000 = 15%, $5001+ = 20%
        $testCases = [
            ['amount' => 500, 'expectedBandCut' => 25],       // 5% of $500 = $25
            ['amount' => 1000, 'expectedBandCut' => 50],      // 5% of $1,000 = $50
            ['amount' => 1001, 'expectedBandCut' => 100.1],   // 10% of $1,001 = $100.10
            ['amount' => 1500, 'expectedBandCut' => 150],     // 10% of $1,500 = $150
            ['amount' => 2500, 'expectedBandCut' => 375],     // 15% of $2,500 = $375
            ['amount' => 5000, 'expectedBandCut' => 750],     // 15% of $5,000 = $750
            ['amount' => 5001, 'expectedBandCut' => 1000.2],  // 20% of $5,001 = $1,000.20
            ['amount' => 10000, 'expectedBandCut' => 2000]    // 20% of $10,000 = $2,000
        ];

        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'Percentage Tiered Band Cut',
                    'cutType' => 'tiered',
                    'tierConfig' => [
                        ['min' => 0, 'max' => 1000, 'type' => 'percentage', 'value' => 5],
                        ['min' => 1001, 'max' => 2000, 'type' => 'percentage', 'value' => 10],
                        ['min' => 2001, 'max' => 5000, 'type' => 'percentage', 'value' => 15],
                        ['min' => 5001, 'max' => PHP_FLOAT_MAX, 'type' => 'percentage', 'value' => 20]
                    ]
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1']
        ];

        foreach ($testCases as $testCase) {
            $response = $this->actingAs($this->user)
                ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                    'nodes' => $nodes,
                    'edges' => $edges,
                    'test_amount' => $testCase['amount']
                ]);

            $response->assertOk();
            $result = $response->json();

            $this->assertEquals(
                $testCase['expectedBandCut'],
                $result['band_cut'],
                "For amount \${$testCase['amount']}, expected band cut \${$testCase['expectedBandCut']} but got \${$result['band_cut']}"
            );
        }
    }

    /**
     * Test mixed tier types (some percentage, some fixed)
     */
    public function test_mixed_tier_types_band_cut()
    {
        // Tiers: $0-1000 = $50 fixed, $1001-2000 = 10% percentage, $2001-5000 = $300 fixed, $5001+ = 20% percentage
        $testCases = [
            ['amount' => 500, 'expectedBandCut' => 50],       // Fixed $50
            ['amount' => 1000, 'expectedBandCut' => 50],      // Fixed $50
            ['amount' => 1001, 'expectedBandCut' => 100.1],   // 10% of $1,001 = $100.10
            ['amount' => 1500, 'expectedBandCut' => 150],     // 10% of $1,500 = $150
            ['amount' => 2500, 'expectedBandCut' => 300],     // Fixed $300
            ['amount' => 5000, 'expectedBandCut' => 300],     // Fixed $300
            ['amount' => 5001, 'expectedBandCut' => 1000.2],  // 20% of $5,001 = $1,000.20
            ['amount' => 10000, 'expectedBandCut' => 2000]    // 20% of $10,000 = $2,000
        ];

        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'Mixed Tiered Band Cut',
                    'cutType' => 'tiered',
                    'tierConfig' => [
                        ['min' => 0, 'max' => 1000, 'type' => 'fixed', 'value' => 50],
                        ['min' => 1001, 'max' => 2000, 'type' => 'percentage', 'value' => 10],
                        ['min' => 2001, 'max' => 5000, 'type' => 'fixed', 'value' => 300],
                        ['min' => 5001, 'max' => PHP_FLOAT_MAX, 'type' => 'percentage', 'value' => 20]
                    ]
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1']
        ];

        foreach ($testCases as $testCase) {
            $response = $this->actingAs($this->user)
                ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                    'nodes' => $nodes,
                    'edges' => $edges,
                    'test_amount' => $testCase['amount']
                ]);

            $response->assertOk();
            $result = $response->json();

            $this->assertEquals(
                $testCase['expectedBandCut'],
                $result['band_cut'],
                "For amount \${$testCase['amount']}, expected band cut \${$testCase['expectedBandCut']} but got \${$result['band_cut']}"
            );
        }
    }

    /**
     * Test conditional node branching
     */
    public function test_conditional_node_calculation()
    {
        $testAmount = 5000;
        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'conditional-1',
                'type' => 'conditional',
                'data' => [
                    'label' => 'Price Check',
                    'conditionType' => 'booking_price',
                    'operator' => '>',
                    'value' => 3000
                ]
            ],
            [
                'id' => 'bandcut-true',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'High Price Cut',
                    'cutType' => 'percentage',
                    'value' => 15
                ]
            ],
            [
                'id' => 'bandcut-false',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'Low Price Cut',
                    'cutType' => 'percentage',
                    'value' => 10
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'conditional-1'],
            ['id' => 'e2', 'source' => 'conditional-1', 'sourceHandle' => 'true', 'target' => 'bandcut-true'],
            ['id' => 'e3', 'source' => 'conditional-1', 'sourceHandle' => 'false', 'target' => 'bandcut-false']
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                'nodes' => $nodes,
                'edges' => $edges,
                'test_amount' => $testAmount
            ]);

        $response->assertOk();
        $result = $response->json();

        // $5000 > $3000, so takes TRUE branch: 15% of $5000 = $750
        $this->assertEquals(750, $result['band_cut']);
    }

    /**
     * Test equal split payout group
     */
    public function test_equal_split_payout_group()
    {
        $testAmount = 5000;
        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'data' => [
                    'label' => 'Band Cut',
                    'cutType' => 'percentage',
                    'value' => 10
                ]
            ],
            [
                'id' => 'payout-1',
                'type' => 'payoutGroup',
                'data' => [
                    'label' => 'Band Members',
                    'sourceType' => 'allMembers',
                    'distributionMode' => 'equal_split',
                    'allocationType' => 'remainder'
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1'],
            ['id' => 'e2', 'source' => 'bandcut-1', 'target' => 'payout-1']
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                'nodes' => $nodes,
                'edges' => $edges,
                'test_amount' => $testAmount
            ]);

        $response->assertOk();
        $result = $response->json();

        // Band cut: $500 (10% of $5000)
        $this->assertEquals(500, $result['band_cut']);

        // Remaining $4500 split among 3 members (owner + 2 members) = $1500 each
        $this->assertCount(3, $result['member_payouts']);
        foreach ($result['payouts'] as $payout) {
            $this->assertEquals(1500, $payout['amount']);
        }
    }

    /**
     * Test sequential allocation with multiple payout groups
     */
    public function test_sequential_allocation()
    {
        // Create payment group for testing
        $paymentGroup = $this->band->paymentGroups()->create([
            'name' => 'Production Team',
            'band_id' => $this->band->id
        ]);

        $producer1 = User::factory()->create(['name' => 'Producer 1']);
        $producer2 = User::factory()->create(['name' => 'Producer 2']);
        $paymentGroup->users()->attach([$producer1->id, $producer2->id]);

        $testAmount = 5000;
        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'bandcut-1',
                'type' => 'bandCut',
                'data' => [
                    'cutType' => 'percentage',
                    'value' => 10
                ]
            ],
            [
                'id' => 'payout-1',
                'type' => 'payoutGroup',
                'data' => [
                    'label' => 'Production',
                    'sourceType' => 'paymentGroup',
                    'groupId' => $paymentGroup->id,
                    'distributionMode' => 'equal_split',
                    'allocationType' => 'fixed',
                    'allocationValue' => 1000,
                    'displayOrder' => 1
                ]
            ],
            [
                'id' => 'payout-2',
                'type' => 'payoutGroup',
                'data' => [
                    'label' => 'Members',
                    'sourceType' => 'allMembers',
                    'distributionMode' => 'equal_split',
                    'allocationType' => 'percentage',
                    'allocationValue' => 60,
                    'displayOrder' => 2
                ]
            ],
            [
                'id' => 'payout-3',
                'type' => 'payoutGroup',
                'data' => [
                    'label' => 'Management',
                    'sourceType' => 'specific',
                    'memberIds' => [$this->user->id],
                    'distributionMode' => 'equal_split',
                    'allocationType' => 'remainder',
                    'displayOrder' => 3
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'bandcut-1'],
            ['id' => 'e2', 'source' => 'bandcut-1', 'target' => 'payout-1'],
            ['id' => 'e3', 'source' => 'payout-1', 'target' => 'payout-2'],
            ['id' => 'e4', 'source' => 'payout-2', 'target' => 'payout-3']
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                'nodes' => $nodes,
                'edges' => $edges,
                'test_amount' => $testAmount
            ]);

        $response->assertOk();
        $result = $response->json();

        // Verify calculations:
        // Income: $5000
        // Band cut (10%): $500
        // Remaining: $4500
        // Group 1 (fixed $1000): $500 each for 2 producers
        // Remaining: $3500
        // Group 2 (60% of $3500 = $2100): $700 each for 3 members
        // Remaining: $1400
        // Group 3 (remainder): $1400 to 1 person

        $this->assertEquals(500, $result['band_cut']);

        // Check production team got $1000 total ($500 each)
        $productionPayouts = array_filter($result['member_payouts'], fn($p) =>
            in_array($p['user_id'], [$producer1->id, $producer2->id])
        );
        $this->assertEquals(1000, array_sum(array_column($productionPayouts, 'amount')));

        // Check total distributed equals input minus band cut
        $totalPayouts = array_sum(array_column($result['member_payouts'], 'amount'));
        $this->assertEquals($testAmount - $result['band_cut'], $totalPayouts);
    }

    /**
     * Test rounding behavior - ensure no money is lost
     */
    public function test_rounding_preserves_total()
    {
        $testAmount = 1000;
        $nodes = [
            [
                'id' => 'income-1',
                'type' => 'income',
                'data' => ['label' => 'Income']
            ],
            [
                'id' => 'payout-1',
                'type' => 'payoutGroup',
                'data' => [
                    'label' => 'Split 3 Ways',
                    'sourceType' => 'allMembers',
                    'distributionMode' => 'equal_split',
                    'allocationType' => 'remainder'
                ]
            ]
        ];

        $edges = [
            ['id' => 'e1', 'source' => 'income-1', 'target' => 'payout-1']
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/finances/payout-flow/{$this->band->id}/preview", [
                'nodes' => $nodes,
                'edges' => $edges,
                'test_amount' => $testAmount
            ]);

        $response->assertOk();
        $result = $response->json();

        // $1000 / 3 = $333.33... per person
        $this->assertCount(3, $result['member_payouts']);

        // Total should equal input (no money lost to rounding)
        $totalPayouts = array_sum(array_column($result['member_payouts'], 'amount'));
        $this->assertEquals($testAmount, $totalPayouts);
    }
}
