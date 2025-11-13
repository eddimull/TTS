<?php

namespace App\Http\Controllers;

use App\Models\EventTypes;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\FinanceServices;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bands = Auth::user()->bandOwner;

        $bandsWithBookings = (new FinanceServices())->getBandFinances($bands);
        $bookings = [];
        foreach ($bandsWithBookings as $band)
        {
            foreach ($band->completedBookings as $booking)
            {
                $booking->attachPayments();
                $booking->contacts = $booking->booking_contacts;
                $booking->invoices = $booking->invoices;
                $bookings[] = $booking;
            }
        }

        $eventTypes = EventTypes::all();


        return Inertia::render('Invoices/Index', [
            'bookings' => $bookings,
            'eventTypes' => $eventTypes
        ]);
    }
}
