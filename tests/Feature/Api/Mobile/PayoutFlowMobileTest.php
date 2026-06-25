<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandPayoutConfig;
use App\Models\Bands;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutFlowMobileTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Bands $band;
    protected string $ownerToken;
    protected string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Owner so allMembers payout groups resolve to a payable recipient.
        BandOwners::create(['band_id' => $this->band->id, 'user_id' => $this->owner->id]);

        // $this->member is a band member but NOT an owner — so the owner-only
        // tests actually exercise the `owner` middleware (a 403 from the band
        // membership check would otherwise be a false pass), and read-gated
        // routes can assert a non-owner member is allowed.
        BandMembers::create(['band_id' => $this->band->id, 'user_id' => $this->member->id]);

        $this->ownerToken = $this->owner->createToken('test-device')->plainTextToken;
        $this->memberToken = $this->member->createToken('test-device')->plainTextToken;
    }

    private function headers(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function makeConfig(bool $active = true): BandPayoutConfig
    {
        return BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Cfg',
            'is_active' => $active,
            'band_cut_type' => 'none',
            'band_cut_value' => 0,
            'member_payout_type' => 'equal_split',
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 0,
            'flow_diagram' => [
                'nodes' => [
                    ['id' => 'income-1', 'type' => 'income', 'data' => ['amount' => 1000]],
                ],
                'edges' => [],
            ],
        ]);
    }

    public function test_lists_band_configs_without_flow_payload(): void
    {
        $this->makeConfig();

        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs");

        $res->assertOk()->assertJsonStructure([
            'configs' => [['id', 'name', 'is_active', 'updated_at']],
        ]);
        // List is intentionally light — no flow_diagram.
        $this->assertArrayNotHasKey('flow_diagram', $res->json('configs.0'));
    }

    public function test_shows_one_config_with_flow_diagram(): void
    {
        $config = $this->makeConfig();

        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs/{$config->id}");

        $res->assertOk()
            ->assertJsonPath('id', $config->id)
            ->assertJsonPath('flow_diagram.nodes.0.type', 'income');
    }

    public function test_preview_calculates_via_shared_service(): void
    {
        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->postJson("/api/mobile/bands/{$this->band->id}/payout-flow/preview", [
                'test_amount' => 1000,
                'nodes' => [
                    ['id' => 'income-1', 'type' => 'income', 'data' => ['amount' => 1000]],
                    ['id' => 'p1', 'type' => 'payoutGroup', 'data' => [
                        'sourceType' => 'allMembers',
                        'allMembersConfig' => ['includeOwners' => true, 'includeMembers' => false, 'includeProduction' => false],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]],
                ],
                'edges' => [['source' => 'income-1', 'target' => 'p1']],
            ]);

        $res->assertOk()
            ->assertJsonPath('total_amount', 1000)
            ->assertJsonPath('total_member_payout', 1000);
    }

    public function test_owner_can_update_flow_and_activation_deactivates_others(): void
    {
        $other = $this->makeConfig(active: true);
        $target = $this->makeConfig(active: false);

        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->patchJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs/{$target->id}", [
                'is_active' => true,
                'flow_diagram' => [
                    'nodes' => [['id' => 'income-1', 'type' => 'income', 'data' => ['amount' => 5000]]],
                    'edges' => [],
                ],
            ]);

        $res->assertOk()->assertJsonPath('flow_diagram.nodes.0.data.amount', 5000);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertFalse($other->fresh()->is_active, 'activating one config deactivates the others');
    }

    public function test_non_owner_cannot_update(): void
    {
        $config = $this->makeConfig();

        $this->withHeaders($this->headers($this->memberToken))
            ->patchJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs/{$config->id}", [
                'name' => 'Hacked',
            ])
            ->assertForbidden();
    }

    public function test_preview_resolves_roster_members_and_node_values(): void
    {
        // A roster with 3 members on this band.
        $roster = Roster::factory()->create(['band_id' => $this->band->id, 'is_active' => true, 'is_default' => true]);
        RosterMember::factory()->count(3)->create(['roster_id' => $roster->id, 'is_active' => true]);

        // income(900) -> payoutGroup(roster, remainder, equal_split)
        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->postJson("/api/mobile/bands/{$this->band->id}/payout-flow/preview", [
                'test_amount' => 900,
                'nodes' => [
                    ['id' => 'income-1', 'type' => 'income', 'data' => ['amount' => 900]],
                    ['id' => 'p1', 'type' => 'payoutGroup', 'data' => [
                        'sourceType' => 'roster',
                        'rosterConfig' => ['memberTypeFilter' => 'all'],
                        'incomingAllocationType' => 'remainder',
                        'distributionMode' => 'equal_split',
                    ]],
                ],
                'edges' => [['source' => 'income-1', 'target' => 'p1']],
            ]);

        $res->assertOk();
        // The roster's 3 members resolved (previously 0 with no roster context).
        $res->assertJsonPath('node_values.p1.memberCount', 3);
        $res->assertJsonPath('node_values.p1.allocated', 900);
        $res->assertJsonPath('node_values.p1.perMember', 300);
    }

    public function test_config_templates_are_all_structurally_valid(): void
    {
        $service = app(\App\Services\PayoutFlowService::class);
        $templates = $service->configTemplates();

        $this->assertSame(
            ['blank', 'equal_split', 'band_cut_equal', 'roster_sub_pay'],
            array_keys($templates),
        );

        foreach ($templates as $key => $tpl) {
            $this->assertArrayHasKey('name', $tpl, "template $key name");
            $this->assertArrayHasKey('description', $tpl, "template $key description");
            $flow = $tpl['flowDiagram'];
            $this->assertArrayHasKey('nodes', $flow);
            $this->assertArrayHasKey('edges', $flow);

            $incomes = array_filter($flow['nodes'], fn ($n) => $n['type'] === 'income');
            $this->assertCount(1, $incomes, "template $key must have one income node");

            $errors = $service->collectFlowValidationErrors($flow['nodes'], $flow['edges']);
            $this->assertSame([], $errors, "template $key flow invalid: " . json_encode($errors));
        }
    }

    public function test_templates_endpoint_lists_pickable_templates(): void
    {
        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/payout-flow/templates");

        $res->assertOk();
        $res->assertJsonCount(4, 'templates');
        $res->assertJsonStructure(['templates' => [['key', 'name', 'description']]]);
        $keys = array_column($res->json('templates'), 'key');
        $this->assertEqualsCanonicalizing(
            ['blank', 'equal_split', 'band_cut_equal', 'roster_sub_pay'],
            $keys,
        );
    }

    public function test_band_member_can_list_templates(): void
    {
        // Templates are read-gated, not owner-only — a band member (non-owner)
        // can list them.
        $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/payout-flow/templates")
            ->assertOk()
            ->assertJsonCount(4, 'templates');
    }

    public function test_create_config_from_blank_template_returns_inactive_config_with_income(): void
    {
        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->postJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs", [
                'name' => 'My New Config',
                'template' => 'blank',
            ]);

        $res->assertCreated();
        $res->assertJsonPath('config.name', 'My New Config');
        $res->assertJsonPath('config.is_active', false);
        $nodes = $res->json('config.flow_diagram.nodes');
        $this->assertCount(1, $nodes);
        $this->assertSame('income', $nodes[0]['type']);

        $this->assertDatabaseHas('band_payout_configs', [
            'band_id' => $this->band->id,
            'name' => 'My New Config',
            'is_active' => false,
        ]);
    }

    public function test_create_config_from_band_cut_template_has_three_nodes(): void
    {
        $res = $this->withHeaders($this->headers($this->ownerToken))
            ->postJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs", [
                'name' => 'Cut + split',
                'template' => 'band_cut_equal',
            ]);

        $res->assertCreated();
        $types = array_column($res->json('config.flow_diagram.nodes'), 'type');
        $this->assertEqualsCanonicalizing(['income', 'bandCut', 'payoutGroup'], $types);
    }

    public function test_create_config_does_not_deactivate_existing_active_config(): void
    {
        $active = BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Existing active',
            'is_active' => true,
            'flow_diagram' => ['nodes' => [['id' => 'income-1', 'type' => 'income', 'data' => ['amount' => 100]]], 'edges' => []],
        ]);

        $this->withHeaders($this->headers($this->ownerToken))
            ->postJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs", [
                'name' => 'New one', 'template' => 'blank',
            ])->assertCreated();

        $this->assertDatabaseHas('band_payout_configs', ['id' => $active->id, 'is_active' => true]);
    }

    public function test_create_config_rejects_unknown_template(): void
    {
        $this->withHeaders($this->headers($this->ownerToken))
            ->postJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs", [
                'name' => 'Bad', 'template' => 'nope',
            ])->assertStatus(422);
    }

    public function test_non_owner_cannot_create_config(): void
    {
        $this->withHeaders($this->headers($this->memberToken))
            ->postJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs", [
                'name' => 'X', 'template' => 'blank',
            ])->assertForbidden();
    }

    public function test_owner_can_delete_config(): void
    {
        $config = BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'To delete',
            'is_active' => false,
            'flow_diagram' => ['nodes' => [['id' => 'income-1', 'type' => 'income', 'data' => ['amount' => 0]]], 'edges' => []],
        ]);

        $this->withHeaders($this->headers($this->ownerToken))
            ->deleteJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs/{$config->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('band_payout_configs', ['id' => $config->id]);
    }

    public function test_delete_unknown_config_returns_404(): void
    {
        $this->withHeaders($this->headers($this->ownerToken))
            ->deleteJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs/999999")
            ->assertNotFound();
    }

    public function test_non_owner_cannot_delete_config(): void
    {
        $config = BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Keep',
            'is_active' => false,
            'flow_diagram' => ['nodes' => [['id' => 'income-1', 'type' => 'income', 'data' => ['amount' => 0]]], 'edges' => []],
        ]);

        $this->withHeaders($this->headers($this->memberToken))
            ->deleteJson("/api/mobile/bands/{$this->band->id}/payout-flow/configs/{$config->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('band_payout_configs', ['id' => $config->id]);
    }
}
