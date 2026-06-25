<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\BandPayoutConfig;
use App\Services\PayoutFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PayoutFlowController extends Controller
{
    public function __construct(
        private readonly PayoutFlowService $payoutFlow,
    ) {}

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
            $tempConfig = $this->payoutFlow->buildPreviewConfig(
                $band->id,
                $validated['nodes'],
                $validated['edges']
            );

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

        $errors = $this->payoutFlow->collectFlowValidationErrors($validated['nodes'], $validated['edges']);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors
        ]);
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
}
