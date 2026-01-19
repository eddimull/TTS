<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\BandPayoutConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PayoutFlowController extends Controller
{
    /**
     * Show the payout flow editor for a band.
     */
    public function edit(Bands $band): InertiaResponse
    {
        $this->authorize('update', $band);

        $band->load([
            'owners',
            'members',
            'paymentGroups.users',
            'activePayoutConfig',
            'payoutConfigs' => fn($query) => $query->orderBy('is_active', 'desc')->orderBy('updated_at', 'desc'),
            'rosters.members.user'
        ]);

        $availableRoles = $this->extractAvailableRoles($band);
        $previewRosterMembers = $this->buildPreviewRosterMembers($band);

        return Inertia::render('Finances/PayoutFlowEditor', [
            'band' => $band,
            'availableRoles' => $availableRoles,
            'previewRosterMembers' => $previewRosterMembers
        ]);
    }

    /**
     * Preview payout calculation from flow without saving.
     */
    public function preview(Request $request, Bands $band): JsonResponse
    {
        $this->authorize('view', $band);

        $validated = $request->validate([
            'nodes' => 'required|array',
            'edges' => 'required|array',
            'test_amount' => 'required|numeric|min:0'
        ]);

        try {
            $tempConfigData = $this->flowToConfig($validated['nodes'], $validated['edges']);
            $tempConfig = new BandPayoutConfig($tempConfigData);
            $tempConfig->band_id = $band->id;

            return response()->json($tempConfig->calculatePayouts($validated['test_amount']));
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to preview calculation',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validate flow structure.
     */
    public function validateFlow(Request $request, Bands $band): JsonResponse
    {
        $this->authorize('view', $band);

        $validated = $request->validate([
            'nodes' => 'required|array',
            'edges' => 'required|array'
        ]);

        $errors = $this->collectFlowValidationErrors($validated['nodes'], $validated['edges']);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors
        ]);
    }

    /**
     * Convert flow diagram nodes/edges to traditional config fields
     * This maintains backward compatibility with form-based configuration
     *
     * @param array $nodes Flow diagram nodes
     * @param array $edges Flow diagram edges
     * @return array Traditional config fields
     */
    private function flowToConfig(array $nodes, array $edges): array
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
                ->sortBy(fn($node) => $node['data']['displayOrder'] ?? 0);

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
     * Extract available roles from band's configured roles.
     */
    private function extractAvailableRoles(Bands $band): array
    {
        return $band->bandRoles()
            ->active()
            ->ordered()
            ->get()
            ->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_order' => $role->display_order,
            ])
            ->toArray();
    }

    /**
     * Build preview roster members from the band's default roster.
     */
    private function buildPreviewRosterMembers(Bands $band): array
    {
        $defaultRoster = $band->rosters->firstWhere('is_default', true)
            ?? $band->rosters->firstWhere('is_active', true)
            ?? $band->rosters->first();

        if (!$defaultRoster || !$defaultRoster->members) {
            return [];
        }

        return $defaultRoster->members
            ->where('is_active', true)
            ->map(fn($member) => [
                'roster_member_id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name ?? $member->name,
                'role' => $member->role,
                'band_role_id' => $member->band_role_id,
                'type' => $member->user_id ? 'member' : 'substitute',
                'eventsAttended' => 1,
                'totalEvents' => 1,
                'customPayout' => null
            ])
            ->values()
            ->toArray();
    }

    /**
     * Collect validation errors for flow structure.
     */
    private function collectFlowValidationErrors(array $nodes, array $edges): array
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
                ->flatMap(fn($edge) => [$edge['source'], $edge['target']])
                ->unique()
                ->toArray();

            foreach ($nodes as $node) {
                $isDisconnected = !in_array($node['id'], $connectedNodeIds);
                if ($isDisconnected && $node['type'] !== 'income') {
                    $errors[] = "Node is disconnected: {$node['type']}";
                }
            }
        }

        return $errors;
    }
}
