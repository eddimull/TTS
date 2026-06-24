<?php

namespace App\Services;

use App\Models\BandPayoutConfig;

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
}
