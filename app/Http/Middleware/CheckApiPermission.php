<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The permission to check (e.g., 'api:read-events')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $apiToken = $request->get('api_token');

        if (!$apiToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API token not found in request'
            ], 401);
        }

        // Check if the API token has the required permission
        if (!$apiToken->hasPermissionTo($permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'This API token does not have permission to perform this action',
                'required_permission' => $permission
            ], 403);
        }

        return $next($request);
    }
}
