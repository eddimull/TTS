<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use Illuminate\Http\Request;
use App\Models\EventTypes;
use App\Models\Proposals;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\InvoiceServices;
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Proposals $proposal, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'contact_id' => 'required|exists:App\Models\ProposalContacts,id',
            'buyer_pays_convenience' => 'required|boolean'
        ]);

        (new InvoiceServices())->createInvoice($proposal, $request);

        return back()->with('successMessage', 'Invoice sent in for ' . $proposal->name);
    }
}
