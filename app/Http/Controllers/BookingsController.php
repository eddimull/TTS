<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingsRequest;
use App\Http\Requests\UpdateBookingsRequest;
use App\Models\Bookings;
use Inertia\Inertia;
use App\Models\Bands;
use Illuminate\Support\Facades\Auth;
use App\Models\EventTypes;
use Carbon\Carbon;

class BookingsController extends Controller
{
    public function index(Bands $band = null)
    {
        if (app()->environment('local'))
        {
            ini_set('memory_limit', '2048M');
        }
        $user = Auth::user();
        $userBands = $user->bands();
        if ($band && !$userBands->contains($band))
        {
            abort(403, 'Unauthorized action.');
        }

        $bookings = $band ? $band->bookings : Bookings::whereIn('band_id', $userBands->pluck('id'))
            ->where('date', '>=', Carbon::now()->subMonths(6))
            ->get();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'bands' => $userBands,
        ]);
    }

    public function create(Bands $band)
    {
        $eventTypes = EventTypes::all();
        return Inertia::render('Bookings/Create', [
            'band' => $band,
            'eventTypes' => $eventTypes
        ]);
    }

    public function store(StoreBookingsRequest $request, Bands $band)
    {
        $booking = $band->bookings()->create($request->validated());
        return redirect()->route('bands.booking.show', [$band, $booking]);
    }

    public function show(Bands $band, Bookings $booking)
    {
        $booking->load('band');
        return Inertia::render('Bookings/Show', ['booking' => $booking, 'band' => $band]);
    }

    public function update(UpdateBookingsRequest $request, Bands $band, Bookings $booking)
    {
        $booking->update($request->validated());
        return redirect()->back()->with('successMessage', "$booking->name has been updated.");
    }

    public function destroy(Bands $band, Bookings $booking)
    {
        $booking->delete();
        return redirect()->route('bookings.index', $band);
    }
}
