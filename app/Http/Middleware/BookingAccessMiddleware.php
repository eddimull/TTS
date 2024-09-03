<?php

namespace App\Http\Middleware;

use App\Models\Bands;
use Closure;
use Illuminate\Http\Request;
use App\Models\Bookings;

class BookingAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user)
        {
            return redirect()->route('login');
        }
        $band = $request->route('band');
        $bookingInput = $request->route('booking');


        $booking = $this->resolveBooking($bookingInput);

        if ($band->owners->contains('user_id', $user->id) || $band->members->contains('user_id', $user->id))
        {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }

    private function resolveBooking($input)
    {
        if ($input instanceof Bookings)
        {
            return $input;
        }

        if (is_numeric($input))
        {
            return Bookings::find($input);
        }

        return null;
    }
}
