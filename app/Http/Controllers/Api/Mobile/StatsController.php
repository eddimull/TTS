<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Return the authenticated user's personal stats (earnings, travel, and
     * performance locations) across all their bands — the same data the web
     * /stats page shows, computed by the shared UserStatsService so the two
     * can't diverge.
     *
     * Performance locations are additionally enriched with cached lat/lng from
     * venue_cache so the mobile map can render markers without a per-address
     * client-side geocoding round-trip.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // UserStatsService is constructed with the user, but several of the
        // queries it reaches into (and helpers it shares) read Auth::user();
        // bind the guard like the other mobile controllers do.
        Auth::setUser($user);

        $stats = (new UserStatsService($user))->getUserStats();

        $stats['locations'] = $this->attachCoordinates($stats['locations'] ?? []);

        return response()->json(['stats' => $stats]);
    }

    /**
     * Attach cached lat/lng to each location by matching its address against
     * venue_cache (the same table the web geocoding endpoint writes). The web
     * geocodes the "venue_name, address" full_address string, so that's what
     * venue_cache.address holds — match on full_address first, then fall back
     * to the bare venue_address. Uncached locations keep null coordinates and
     * simply won't get a map marker — they still appear in the locations list.
     *
     * @param  array<int, array<string, mixed>>  $locations
     * @return array<int, array<string, mixed>>
     */
    private function attachCoordinates(array $locations): array
    {
        if (empty($locations)) {
            return $locations;
        }

        $addresses = collect($locations)
            ->flatMap(fn (array $l) => [$l['full_address'] ?? null, $l['venue_address'] ?? null])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($addresses)) {
            return $locations;
        }

        $coords = DB::table('venue_cache')
            ->whereIn('address', $addresses)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['address', 'latitude', 'longitude'])
            ->keyBy('address');

        return collect($locations)->map(function (array $location) use ($coords) {
            $match = $coords->get($location['full_address'] ?? '')
                ?? $coords->get($location['venue_address'] ?? '');

            $location['lat'] = $match ? (float) $match->latitude : null;
            $location['lng'] = $match ? (float) $match->longitude : null;

            return $location;
        })->all();
    }
}
