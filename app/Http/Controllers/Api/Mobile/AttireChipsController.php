<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\StoreAttireChipRequest;
use App\Models\AttireChip;
use App\Models\Bands;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile attire chips: short, reusable dress-code labels saved per band.
 *
 * Authorization is handled at the route layer via the `mobile.band` middleware
 * with the `read:events` / `write:events` token abilities — chips are an
 * event-editing affordance, so they ride on the same permission. See
 * routes/api.php under the `/api/mobile` group.
 */
class AttireChipsController extends Controller
{
    /**
     * Default chips seeded the first time a band saves any chip. Order here
     * is the order they are inserted (and therefore their `position`).
     */
    private const DEFAULT_CHIPS = [
        'All black',
        'All white',
        'Black tie',
        'Cocktail',
        'Smart casual',
        'Casual',
    ];

    /**
     * GET /api/mobile/bands/{band}/attire-chips
     */
    public function index(Request $request, Bands $band): JsonResponse
    {
        $chips = AttireChip::where('band_id', $band->id)
            ->orderBy('position')
            ->orderBy('label')
            ->get(['id', 'label', 'position']);

        return response()->json(['data' => $chips->values()]);
    }

    /**
     * POST /api/mobile/bands/{band}/attire-chips
     *
     * Idempotent: if a chip with the same label (case-insensitive) already
     * exists for this band, returns it with 200 instead of erroring on the
     * unique constraint.
     *
     * Seeding behavior: if this is the very first chip ever saved for the
     * band, the six DEFAULT_CHIPS are inserted first (in the same
     * transaction). If the incoming label matches one of the defaults
     * (case-insensitive), the separate insert is skipped — the seed
     * already covers it.
     */
    public function store(StoreAttireChipRequest $request, Bands $band): JsonResponse
    {
        $label = $request->validated()['label'];

        $chip = DB::transaction(function () use ($band, $label) {
            // First-time seeding check.
            $existingCount = AttireChip::where('band_id', $band->id)->count();
            $isFirstSave   = $existingCount === 0;

            $matchesDefault = null;
            if ($isFirstSave) {
                $position = 0;
                foreach (self::DEFAULT_CHIPS as $defaultLabel) {
                    $created = (new AttireChip())->forceFill([
                        'band_id'  => $band->id,
                        'label'    => $defaultLabel,
                        'position' => $position++,
                    ]);
                    $created->save();

                    if (strcasecmp($defaultLabel, $label) === 0) {
                        $matchesDefault = $created;
                    }
                }

                if ($matchesDefault) {
                    return $matchesDefault;
                }
            }

            // Idempotent: return the existing row if the label already exists
            // (case-insensitive). Covers both the post-seed lookup and the
            // ordinary "user adds a duplicate" path.
            $existing = AttireChip::where('band_id', $band->id)
                ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
                ->first();

            if ($existing) {
                return $existing;
            }

            $nextPosition = (int) AttireChip::where('band_id', $band->id)->max('position') + 1;

            $chip = (new AttireChip())->forceFill([
                'band_id'  => $band->id,
                'label'    => $label,
                'position' => $nextPosition,
            ]);
            $chip->save();

            return $chip;
        });

        return response()->json([
            'data' => [
                'id'       => $chip->id,
                'label'    => $chip->label,
                'position' => $chip->position,
            ],
        ]);
    }

    /**
     * DELETE /api/mobile/bands/{band}/attire-chips/{chip}
     */
    public function destroy(Request $request, Bands $band, AttireChip $chip): JsonResponse
    {
        abort_if($chip->band_id !== $band->id, 404);

        $chip->delete();

        return response()->json(['message' => 'Attire chip deleted.']);
    }
}
