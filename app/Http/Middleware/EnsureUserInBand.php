<?php

namespace App\Http\Middleware;

use App\Models\Bands;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserInBand
{
    public function handle(Request $request, Closure $next, string $ability = null): Response
    {
        $bandHeader = $request->header('X-Band-ID');

        if (!$bandHeader) {
            return response()->json([
                'error' => 'Missing band context.',
                'message' => 'X-Band-ID header is required.',
            ], 422);
        }

        // Prefer the {band} route parameter when present — it's a more specific
        // signal than the header, which only carries the user's currently
        // selected band. The header is the default; the URL is the override.
        $routeBandId = $request->route('band');
        $bandId = $routeBandId ?: $bandHeader;

        $band = $bandId instanceof Bands ? $bandId : Bands::find($bandId);

        if (!$band) {
            return response()->json([
                'error' => 'Band not found.',
            ], 404);
        }

        $user = $request->user();

        if (!$user->allBands()->contains('id', $band->id)) {
            return response()->json([
                'error' => 'Forbidden.',
                'message' => 'You are not a member of this band.',
            ], 403);
        }

        if ($ability && !$request->user()->tokenCan($ability)) {
            return response()->json([
                'error' => 'Forbidden.',
                'message' => 'Insufficient token permissions.',
            ], 403);
        }

        // Token abilities are band-agnostic: a user who has, say, read:bookings on
        // ANY of their bands carries a bare "read:bookings" ability, which the
        // tokenCan() check above cannot tie back to THIS band. Re-check the
        // ability against the resolved band so a sub (who is in allBands() but
        // lacks the per-band permission) can't reach another band's resources.
        // canRead()/canWrite() are band-scoped and already encode the owner
        // shortcut and the sub-can-read-events exception.
        if ($ability && str_contains($ability, ':')) {
            [$action, $resource] = explode(':', $ability, 2);

            $allowed = match ($action) {
                'read'  => $user->canRead($resource, $band->id),
                'write' => $user->canWrite($resource, $band->id),
                default => true, // unrecognised action shape: leave to tokenCan()
            };

            if (!$allowed) {
                return response()->json([
                    'error' => 'Forbidden.',
                    'message' => 'You do not have permission for this resource in this band.',
                ], 403);
            }
        }

        $request->merge(['mobile_band' => $band]);

        // Substitute the resolved model back into the route parameter so that
        // Laravel's scopeBindings() can scope child bindings (e.g. {booking})
        // through the {band} relationship automatically.
        if ($request->route('band')) {
            $request->route()->setParameter('band', $band);
        }

        return $next($request);
    }
}
