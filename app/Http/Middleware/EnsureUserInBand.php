<?php

namespace App\Http\Middleware;

use App\Models\Bands;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserInBand
{
    public function handle(Request $request, Closure $next): Response
    {
        $bandId = $request->header('X-Band-ID');

        if (!$bandId) {
            return response()->json([
                'error' => 'Missing band context.',
                'message' => 'X-Band-ID header is required.',
            ], 422);
        }

        $band = Bands::find($bandId);

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

        $request->merge(['mobile_band' => $band]);

        return $next($request);
    }
}
