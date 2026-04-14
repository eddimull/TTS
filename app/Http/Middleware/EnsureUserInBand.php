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

        $bandId = $bandHeader;

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
