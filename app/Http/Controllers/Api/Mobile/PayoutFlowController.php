<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\BandPayoutConfig;
use App\Services\PayoutFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mobile API for the payout flow editor.
 *
 * Reuses the shared PayoutFlowService (flow→config mapping, preview-config
 * building, active-config bookkeeping) so the calculation/validation logic
 * stays in one place across web and mobile. The band is resolved by the
 * `mobile.band` middleware and made available as `mobile_band` on the request;
 * write routes are additionally gated by the `owner` middleware.
 */
class PayoutFlowController extends Controller
{
    public function __construct(
        private readonly PayoutFlowService $payoutFlow,
    ) {}

    /**
     * GET /api/mobile/bands/{band}/payout-flow/configs
     * List a band's payout configurations.
     */
    public function listConfigs(Bands $band): JsonResponse
    {
        // The list response omits flow_diagram, so don't load that (potentially
        // large) column — select only what formatConfig() needs.
        return response()->json([
            'configs' => $band->payoutConfigs()
                ->get(['id', 'band_id', 'name', 'is_active', 'updated_at'])
                ->map(fn (BandPayoutConfig $c) => $this->formatConfig($c))
                ->values(),
        ]);
    }

    /**
     * The starter templates offered when creating a config (key/name/description
     * only — no flow payload; the flow is applied server-side on create).
     */
    public function templates(Bands $band): JsonResponse
    {
        $templates = collect($this->payoutFlow->configTemplates())
            ->map(fn (array $t, string $key) => [
                'key' => $key,
                'name' => $t['name'],
                'description' => $t['description'],
            ])
            ->values();

        return response()->json(['templates' => $templates]);
    }

    /**
     * GET /api/mobile/bands/{band}/payout-flow/configs/{configId}
     * Fetch one configuration, including its full flow_diagram.
     */
    public function showConfig(Bands $band, int $configId): JsonResponse
    {
        $config = $this->findConfig($band->id, $configId);

        return response()->json($this->formatConfig($config, withFlow: true));
    }

    /**
     * PATCH /api/mobile/bands/{band}/payout-flow/configs/{configId}
     * Update a configuration's flow_diagram (owner-only).
     */
    public function updateConfig(Request $request, Bands $band, int $configId): JsonResponse
    {
        $config = $this->findConfig($band->id, $configId);

        $validated = $request->validate([
            'flow_diagram' => 'nullable|array',
            'name' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        // Activating this config deactivates the others (shared service logic).
        if (($validated['is_active'] ?? false) && ! $config->is_active) {
            $this->payoutFlow->deactivateOtherConfigs($band->id, $config->id);
        }

        $config->update($validated);

        return response()->json($this->formatConfig($config->fresh(), withFlow: true));
    }

    /**
     * POST /api/mobile/bands/{band}/payout-flow/configs
     * Create a new configuration from a starter template (owner-only).
     */
    public function createConfig(Request $request, Bands $band): JsonResponse
    {
        // Validate against the service's actual template keys so the two can't
        // drift (a stale hard-coded list would let templateFlow() return null
        // and persist a config with a null flow_diagram).
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template' => [
                'required',
                'string',
                Rule::in(array_keys($this->payoutFlow->configTemplates())),
            ],
        ]);

        $flow = $this->payoutFlow->templateFlow($validated['template']);

        // New configs are created INACTIVE — the user activates explicitly.
        // (is_active column defaults to 1, so it must be set here.)
        $config = BandPayoutConfig::create([
            'band_id' => $band->id,
            'name' => $validated['name'],
            'is_active' => false,
            'flow_diagram' => $flow,
        ]);

        return response()->json(
            ['config' => $this->formatConfig($config, withFlow: true)],
            201,
        );
    }

    /**
     * DELETE /api/mobile/bands/{band}/payout-flow/configs/{configId}
     * Delete a config (owner-only). The mobile UI blocks deleting the active
     * config; there is intentionally no server-side active-guard (mirrors web).
     */
    public function destroyConfig(Bands $band, int $configId): JsonResponse
    {
        $this->findConfig($band->id, $configId)->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/mobile/bands/{band}/payout-flow/preview
     * Preview a payout calculation for a flow + test amount (no persistence).
     */
    public function preview(Request $request, Bands $band): JsonResponse
    {
        $validated = $request->validate([
            'nodes' => 'required|array',
            'edges' => 'required|array',
            'test_amount' => 'required|numeric|min:0',
            // Scope existence to this band so a bad/cross-band id 422s instead of
            // silently falling back to an empty roster.
            'roster_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('rosters', 'id')->where('band_id', $band->id),
            ],
        ]);

        try {
            $tempConfig = $this->payoutFlow->buildPreviewConfig(
                $band->id,
                $validated['nodes'],
                $validated['edges'],
            );

            // Resolve roster members so roster-source payout groups compute real
            // counts in the preview (no booking context otherwise).
            $attendance = $this->payoutFlow->attendanceFromRoster(
                $band->id,
                $validated['roster_id'] ?? null,
            );

            return response()->json($tempConfig->calculatePayouts(
                $validated['test_amount'],
                null,
                null,
                $attendance->isNotEmpty() ? $attendance : null,
            ));
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to preview calculation',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function findConfig(int $bandId, int $configId): BandPayoutConfig
    {
        return BandPayoutConfig::where('band_id', $bandId)
            ->where('id', $configId)
            ->firstOrFail();
    }

    private function formatConfig(BandPayoutConfig $c, bool $withFlow = false): array
    {
        $out = [
            'id' => $c->id,
            'name' => $c->name,
            'is_active' => $c->is_active,
            'updated_at' => $c->updated_at,
        ];
        // Always include the flow on detail/update; keep the list response light.
        if ($withFlow) {
            $out['flow_diagram'] = $c->flow_diagram;
        }

        return $out;
    }
}
