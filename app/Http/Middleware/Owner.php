<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Bands;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;

class Owner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $band = $request->route('band');
        if (is_string($band)) {
            $band = Bands::find($band);
        }
        if (!$band instanceof Bands || !$user->isOwner($band->id)) {
            return abort(403, 'Unauthorized access');
        }

        return $next($request);
    }
}
