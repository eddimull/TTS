<?php

namespace App\Services;

use App\Models\BandPayoutConfig;
use App\Models\Roster;
use Illuminate\Support\Collection;

/**
 * Shared payout-flow logic used by both the web PayoutFlowController/
 * FinancesController and the mobile Api\Mobile\PayoutFlowController.
 *
 * Extracted from those controllers so the flow→config mapping, flow validation,
 * and active-config bookkeeping live in one place instead of being duplicated
 * across the web and mobile surfaces.
 */
class PayoutFlowService
{
    /**
     * Convert flow diagram nodes/edges to traditional config fields.
     * Maintains backward compatibility with form-based configuration.
     *
     * @param  array  $nodes  Flow diagram nodes
     * @param  array  $edges  Flow diagram edges
     * @return array Traditional config fields
     */
    public function flowToConfig(array $nodes, array $edges): array
    {
        $config = [
            'band_cut_type' => 'none',
            'band_cut_value' => 0,
            'band_cut_tier_config' => null,
            'use_payment_groups' => false,
            'payment_group_config' => [],
            'member_payout_type' => 'equal_split',
            'tier_config' => null,
            'include_owners' => true,
            'include_members' => true,
            'regular_member_count' => 0,
            'production_member_count' => 0,
            'production_member_types' => [],
            'member_specific_config' => [],
            'minimum_payout' => 0,
        ];

        // Extract Band Cut configuration
        $bandCutNode = collect($nodes)->firstWhere('type', 'bandCut');
        if ($bandCutNode) {
            $config['band_cut_type'] = $bandCutNode['data']['cutType'] ?? 'none';
            $config['band_cut_value'] = $bandCutNode['data']['value'] ?? 0;
            if ($config['band_cut_type'] === 'tiered') {
                $config['band_cut_tier_config'] = $bandCutNode['data']['tierConfig'] ?? null;
            }
        }

        // Extract Split node to determine mode
        $splitNode = collect($nodes)->firstWhere('type', 'split');
        if ($splitNode) {
            $config['use_payment_groups'] = ($splitNode['data']['mode'] ?? 'groups') === 'groups';
        }

        // Extract Payment Group configuration
        if ($config['use_payment_groups']) {
            $groupNodes = collect($nodes)
                ->where('type', 'paymentGroup')
                ->sortBy(fn ($node) => $node['data']['displayOrder'] ?? 0);

            $config['payment_group_config'] = $groupNodes->map(function ($node) {
                return [
                    'group_id' => $node['data']['groupId'] ?? null,
                    'allocation_type' => $node['data']['allocationType'] ?? 'percentage',
                    'allocation_value' => $node['data']['allocationValue'] ?? 0,
                ];
            })->values()->toArray();
        }

        // Extract Individual Members configuration
        $individualNode = collect($nodes)->firstWhere('type', 'individualMembers');
        if ($individualNode) {
            $config['include_owners'] = $individualNode['data']['includeOwners'] ?? true;
            $config['include_members'] = $individualNode['data']['includeMembers'] ?? true;
            $config['production_member_count'] = $individualNode['data']['productionCount'] ?? 0;
            $config['member_payout_type'] = $individualNode['data']['memberPayoutType'] ?? 'equal_split';
            $config['tier_config'] = $individualNode['data']['tierConfig'] ?? null;
            $config['minimum_payout'] = $individualNode['data']['minimumPayout'] ?? 0;
        }

        return $config;
    }

    /**
     * Collect structural validation errors for a flow diagram.
     * Returns an empty array when the flow is valid.
     */
    public function collectFlowValidationErrors(array $nodes, array $edges): array
    {
        $errors = [];
        $nodeCollection = collect($nodes);

        // Must have exactly one income node
        $incomeCount = $nodeCollection->where('type', 'income')->count();
        if ($incomeCount === 0) {
            $errors[] = 'Flow must have an Income node';
        } elseif ($incomeCount > 1) {
            $errors[] = 'Flow can only have one Income node';
        }

        // Check for disconnected nodes (skip if only one node)
        if (count($nodes) > 1) {
            $connectedNodeIds = collect($edges)
                ->flatMap(fn ($edge) => [$edge['source'], $edge['target']])
                ->unique()
                ->toArray();

            foreach ($nodes as $node) {
                $isDisconnected = ! in_array($node['id'], $connectedNodeIds);
                if ($isDisconnected && $node['type'] !== 'income') {
                    $errors[] = "Node is disconnected: {$node['type']}";
                }
            }
        }

        return $errors;
    }

    /**
     * Build a transient (unsaved) BandPayoutConfig from a flow diagram, ready
     * for calculatePayouts(). Used by preview on both web and mobile.
     */
    public function buildPreviewConfig(int $bandId, array $nodes, array $edges): BandPayoutConfig
    {
        $config = new BandPayoutConfig($this->flowToConfig($nodes, $edges));
        $config->band_id = $bandId;
        // Preserve the flow diagram so it uses flow-based calculation.
        $config->flow_diagram = ['nodes' => $nodes, 'edges' => $edges];

        return $config;
    }

    /**
     * Build a preview attendance set from a band's roster, in the shape the
     * flow calculator expects. Used so roster-source payout groups resolve real
     * members during a no-booking preview (each member counted as attending one
     * of one event, i.e. full weight).
     *
     * Pass an explicit $rosterId, or null to use the band's default/active roster.
     */
    public function attendanceFromRoster(int $bandId, ?int $rosterId = null): Collection
    {
        $roster = $rosterId
            ? Roster::where('band_id', $bandId)->where('id', $rosterId)->first()
            : Roster::where('band_id', $bandId)->where('is_active', true)
                ->orderByDesc('is_default')->orderByDesc('id')->first();

        if (! $roster) {
            return collect();
        }

        return $roster->members()->where('is_active', true)
            ->with(['bandRole', 'user'])->get()
            ->map(fn ($m) => [
                'roster_member_id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->display_name,
                'role' => $m->bandRole?->name,
                'band_role_id' => $m->band_role_id,
                // Match calculateAttendanceWeights: a user-backed entry is a
                // 'member', a non-user roster entry is a 'substitute'. This is
                // what memberTypeFilter (members_only / substitutes_only) keys on.
                'type' => $m->isUser() ? 'member' : 'substitute',
                'events_attended' => 1,
                'total_events' => 1,
                'weight' => 1.0,
                'value_attended' => 0,
                'custom_payout' => null,
            ])
            ->values();
    }

    /**
     * Deactivate all other active payout configs for a band.
     */
    public function deactivateOtherConfigs(int $bandId, ?int $excludeConfigId = null): void
    {
        $query = BandPayoutConfig::where('band_id', $bandId)
            ->where('is_active', true);

        if ($excludeConfigId) {
            $query->where('id', '!=', $excludeConfigId);
        }

        $query->update(['is_active' => false]);
    }

    /**
     * Starter templates offered when creating a config. Each is a complete,
     * editable flow_diagram. Keyed by template id; order is the picker order.
     *
     * @return array<string, array{name: string, description: string, flowDiagram: array}>
     */
    public function configTemplates(): array
    {
        $income = fn (array $extra = []) => array_merge([
            'id' => 'income-1',
            'type' => 'income',
            'data' => ['amount' => 0, 'label' => 'Income', 'deactivated' => false],
        ], $extra);

        $allMembersGroup = fn (string $id, array $data = []) => [
            'id' => $id,
            'type' => 'payoutGroup',
            'data' => array_merge([
                'label' => 'Members',
                'sourceType' => 'allMembers',
                'allMembersConfig' => [
                    'includeOwners' => true,
                    'includeMembers' => true,
                    'includeProduction' => false,
                    'productionCount' => 0,
                ],
                'distributionMode' => 'equal_split',
                'incomingAllocationType' => 'remainder',
                'incomingAllocationValue' => 0,
                'deactivated' => false,
            ], $data),
        ];

        $bandCut = [
            'id' => 'cut-1',
            'type' => 'bandCut',
            'data' => ['cutType' => 'percentage', 'value' => 10, 'tierConfig' => null, 'deactivated' => false],
        ];

        // Every edge gets a unique id derived from its endpoints + handle. Vue
        // Flow on web silently drops edges that have no id, so omitting it makes
        // template connections render on mobile but NOT on web.
        $edge = fn (string $source, string $sourceHandle, string $target, string $targetHandle) => [
            'id' => "edge-$source-$sourceHandle-$target-$targetHandle",
            'source' => $source,
            'target' => $target,
            'sourceHandle' => $sourceHandle,
            'targetHandle' => $targetHandle,
            'type' => 'custom',
        ];

        return [
            'blank' => [
                'name' => 'Blank',
                'description' => 'Start from scratch with a single income node.',
                'flowDiagram' => [
                    'nodes' => [$income()],
                    'edges' => [],
                ],
            ],
            'equal_split' => [
                'name' => 'Equal split',
                'description' => 'Everyone splits the income evenly.',
                'flowDiagram' => [
                    'nodes' => [$income(), $allMembersGroup('group-1')],
                    'edges' => [
                        $edge('income-1', 'income-out', 'group-1', 'payoutgroup-in'),
                    ],
                ],
            ],
            'band_cut_equal' => [
                'name' => 'Band cut + equal split',
                'description' => 'The band takes a cut, then the rest is split evenly.',
                'flowDiagram' => [
                    'nodes' => [$income(), $bandCut, $allMembersGroup('group-1')],
                    'edges' => [
                        $edge('income-1', 'income-out', 'cut-1', 'bandcut-in'),
                        $edge('cut-1', 'bandcut-out', 'group-1', 'payoutgroup-in'),
                    ],
                ],
            ],
            'roster_sub_pay' => [
                'name' => 'Roster + sub pay',
                'description' => 'Roster members split the remainder; subs get a fixed amount.',
                'flowDiagram' => [
                    'nodes' => [
                        $income(),
                        $bandCut,
                        [
                            'id' => 'group-roster',
                            'type' => 'payoutGroup',
                            'data' => [
                                'label' => 'Roster',
                                'sourceType' => 'roster',
                                'rosterConfig' => [
                                    'useAttendanceWeighting' => true,
                                    'filterByRoleId' => [],
                                    'memberTypeFilter' => 'all',
                                    'minEventsToQualify' => 1,
                                ],
                                'distributionMode' => 'equal_split',
                                'incomingAllocationType' => 'remainder',
                                'incomingAllocationValue' => 0,
                                'deactivated' => false,
                            ],
                        ],
                        [
                            'id' => 'group-subs',
                            'type' => 'payoutGroup',
                            'data' => [
                                'label' => 'Sub pay',
                                'sourceType' => 'roster',
                                'rosterConfig' => [
                                    'useAttendanceWeighting' => false,
                                    'filterByRoleId' => [],
                                    'memberTypeFilter' => 'substitutes_only',
                                    'minEventsToQualify' => 1,
                                ],
                                'distributionMode' => 'fixed',
                                'fixedAmountPerMember' => 0,
                                'incomingAllocationType' => 'fixed',
                                'incomingAllocationValue' => 0,
                                'deactivated' => false,
                            ],
                        ],
                    ],
                    'edges' => [
                        $edge('income-1', 'income-out', 'cut-1', 'bandcut-in'),
                        $edge('cut-1', 'bandcut-out', 'group-subs', 'payoutgroup-in'),
                        $edge('cut-1', 'bandcut-out', 'group-roster', 'payoutgroup-in'),
                    ],
                ],
            ],
        ];
    }

    /**
     * The flow_diagram for a template key, or null if the key is unknown.
     */
    public function templateFlow(string $key): ?array
    {
        return $this->configTemplates()[$key]['flowDiagram'] ?? null;
    }
}
