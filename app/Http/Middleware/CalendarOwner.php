<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Bands;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CalendarOwner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $calendar = $request->route('calendar_id');
        
        $band = $calendar->band;
        
        if (!$band instanceof Bands || !$user->isOwner($band->id)) {
            return abort(403, 'Unauthorized access');
        }
        return $next($request);
    }
}
