<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventTypes;
use App\Models\BookingContacts;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\BookingContact as BookingContactRequest;
use App\Http\Requests\StoreBookingsRequest;
use App\Http\Requests\UpdateBookingsRequest;

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
        return redirect()->route('Booking Details', [$band, $booking]);
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

    public function contacts(Bands $band, Bookings $booking)
    {
        $booking->load('contacts');
        return Inertia::render('Bookings/Contacts', ['booking' => $booking, 'band' => $band]);
    }

    public function storeContact(BookingContactRequest $request, Bands $band, Bookings $booking)
    {
        $booking->contacts()->create($request->validated());
        return redirect()->back()->with('successMessage', "{$request->name} has been added.");
    }

    public function updateContact(BookingContactRequest $request, Bands $band, Bookings $booking, BookingContacts $contact)
    {
        $contact->update($request->validated());
        return redirect()->back()->with('successMessage', "{$request->name} has been updated.");
    }

    public function destroyContact(Bands $band, Bookings $booking, BookingContacts $contact)
    {
        $contact->delete($contact);
        return redirect()->back()->with('successMessage', "{$contact->name} has been removed.");
    }



    public function finances(Bands $band, Bookings $booking)
    {
        $booking->amountPaid = $booking->amountPaid;
        $booking->amountLeft = $booking->amountLeft;

        return Inertia::render('Bookings/Finances', [
            'booking' => $booking,
            'band' => $band,
            'payments' => $booking->payments
        ]);
    }

    public function destroy(Bands $band, Bookings $booking)
    {
        $booking->delete();
        return redirect()->route('Bookings Home');
    }
}
