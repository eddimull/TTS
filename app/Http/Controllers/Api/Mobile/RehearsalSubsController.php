<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\StoreRehearsalSubRequest;
use App\Models\Rehearsal;
use App\Services\Mobile\RehearsalService;
use App\Services\RehearsalSubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RehearsalSubsController extends Controller
{
    public function __construct(
        private readonly RehearsalSubService $subService,
        private readonly RehearsalService $rehearsalService,
    ) {}

    /**
     * POST /api/mobile/rehearsals/{rehearsal}/subs
     */
    public function store(StoreRehearsalSubRequest $request, int $rehearsal): JsonResponse
    {
        [$rehearsalModel, $band] = $this->resolveWritable($request, $rehearsal);

        $this->subService->invite($rehearsalModel, $request->user(), $request->validated());

        return response()->json(
            ['subs' => $this->rehearsalService->formatSubs($rehearsalModel)],
            201,
        );
    }

    /**
     * Resolve the rehearsal + band and enforce canWrite('rehearsals').
     *
     * @return array{0: Rehearsal, 1: \App\Models\Bands}
     */
    private function resolveWritable(Request $request, int $rehearsalId): array
    {
        $rehearsalModel = Rehearsal::with(['rehearsalSchedule.band', 'events'])
            ->findOrFail($rehearsalId);

        $band = $rehearsalModel->rehearsalSchedule?->band ?? $rehearsalModel->band;

        if (!$band) {
            abort(404, 'Band not found for this rehearsal.');
        }

        if (!$request->user()->canWrite('rehearsals', $band->id)) {
            abort(403, 'You do not have permission to manage subs for this rehearsal.');
        }

        return [$rehearsalModel, $band];
    }
}
