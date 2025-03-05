<?php

namespace App\Http\Middleware;

use App\Models\Bands;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SelectTeam
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If there is a route model binding for 'band'
        $user = $request->user();
        $band = $request->route('band');
        if ($band) {
            setPermissionsTeamId($band);
        } else {
            // get the first owned band for the current user
            if ($user) {
                $band = $user->bandOwner->first() ?? null;
                if ($band) {
                    setPermissionsTeamId($band);
                } else {
                    // get the first member band for the current user
                    $band = $user->bandMember->first() ?? null;
                    if ($band) {
                        setPermissionsTeamId($band);
                    } else {
                        setPermissionsTeamId(null);
                    }
                }
            } else {
                setPermissionsTeamId(null);
            }
        }
        return $next($request);
    }
}
