<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\EventTypes;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\BookingContacts;
use App\Services\InvoiceServices;
use App\Events\PaymentWasReceived;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Requests\StoreBookingsRequest;
use App\Http\Requests\UpdateBookingsRequest;
use App\Http\Requests\UpdateBookingEventRequest;
use App\Http\Requests\StoreBookingPaymentRequest;
use App\Http\Requests\UploadBookingContractRequest;
use App\Http\Requests\BookingContact as BookingContactRequest;
use App\Services\BookingActivityService;
use App\Notifications\ContactPortalAccessGranted;

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

        $bookings = $band ? $band->bookings() : Bookings::whereIn('band_id', $userBands->pluck('id'));


        $bookings = $bookings->where('date', '>=', Carbon::now()->subMonths(6))
            ->with('contract') // Eager load the contract relationship
            ->with('contacts') // Eager load the contacts relationship
            ->get();


        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'bands' => $userBands,
        ]);
    }

    public function create(Bands $band)
    {
        $eventTypes = EventTypes::all();
        $bookingDetails = $band->bookings()
            ->with('eventType')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('date')
            ->map(function ($booking) {
                return [
                    'name' => $booking->name,
                    'event_type' => $booking->eventType->name ?? 'Unknown',
                    'start_time' => $booking->start_time,
                    'status' => $booking->status,
                ];
            });
        
        return Inertia::render('Bookings/Create', [
            'band' => $band,
            'eventTypes' => $eventTypes,
            'bookedDates' => $band->bookings()->where('status', '!=', 'cancelled')->pluck('date')->toArray(),
            'bookingDetails' => $bookingDetails,
        ]);
    }

    public function store(StoreBookingsRequest $request, Bands $band)
    {
        
        $booking = $band->bookings()->create($request->validated());

        if ($booking->contract_option === 'none')
        {
            $booking->status = 'confirmed';
            $booking->save();
        }

        $booking->contract()->create([
            'author_id' => Auth::id(),
            'custom_terms' => Storage::disk('local')->json('contract/InitialTerms.json'),
        ]);
        // TODO need to refactor this outside of the controller
        $event = [
            'event_type_id' => $booking->event_type_id,
            'key' => Str::uuid(),
            'title' => $booking->name,
            'date' => $booking->date,
            'time' => $booking->start_time,
            'additional_data' => [
                'times' => [
                    ['title' => 'Load In', 'time' => $booking->start_date_time->copy()->subHours(4)->format('Y-m-d H:i')],
                    ['title' => 'Soundcheck', 'time' => $booking->start_date_time->copy()->subHours(3)->format('Y-m-d H:i')],
                    ['title' => 'Quiet', 'time' => $booking->start_date_time->copy()->subHours(1)->format('Y-m-d H:i')],
                    ['title' => 'End Time', 'time' => $booking->end_date_time->format('Y-m-d H:i')],
                ],
                'backline_provided' => false,
                'production_needed' => true,
                'color' => 'TBD',
                'lodging' => [
                    ['title' => 'Provided', 'type' => 'checkbox', 'data' => false],
                    ['title' => 'location', 'type' => 'text', 'data' => 'TBD'],
                    ['title' => 'check_in', 'type' => 'text', 'data' => 'TBD'],
                    ['title' => 'check_out', 'type' => 'text', 'data' => 'TBD'],
                ],
                'public' => true,
                'outside' => false,
            ]
        ];

        if ($booking->event_type_id === 1)
        {
            $event['additional_data']['wedding']['onsite'] = true;
            $event['additional_data']['wedding']['dances'] = [
                ['title' => 'first_dance', 'data' => 'TBD',],
                ['title' => 'father_daughter', 'data' => 'TBD',],
                ['title' => 'mother_son', 'data' => 'TBD',],
                ['title' => 'money_dance', 'data' => 'TBD',],
                ['title' => 'bouquet_garter', 'data' => 'TBD']
            ];
            $event['additional_data']['times'][] = ['title' => 'Ceremony', 'time' => $booking->start_date_time->copy()->format('Y-m-d H:i')];
            $event['additional_data']['onsite'] = true;
            $event['additional_data']['public'] = false;
        }

        $booking->events()->create($event);

        return redirect()->route('Booking Details', [$band, $booking]);
    }

    public function show(Bands $band, Bookings $booking)
    {
        // Load all necessary relationships for a comprehensive view
        $booking->load([
            'band',
            'eventType',
            'contacts' => function ($query) {
                $query->orderBy('is_primary', 'desc');
            },
            'payments' => function ($query) {
                $query->orderBy('date', 'desc');
            },
            'payments.invoice',
            'contract',
            'events' => function ($query) {
                $query->orderBy('date', 'desc');
            },
        ]);
        
        // Calculate financial totals
        $booking->amountPaid = $booking->amountPaid;
        $booking->amountLeft = $booking->amountLeft;
        
        // Get recent activity history
        $activityService = new \App\Services\BookingActivityService();
        $recentActivities = $activityService->getBookingTimeline($booking)->take(10);
        
        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
            'band' => $band,
            'contacts' => $booking->contacts,
            'payments' => $booking->payments,
            'events' => $booking->events,
            'contract' => $booking->contract,
            'recentActivities' => $recentActivities,
        ]);
    }

    public function update(UpdateBookingsRequest $request, Bands $band, Bookings $booking)
    {
        $booking->update($request->validated());
        return redirect()->back()->with('successMessage', "$booking->name has been updated.");
    }

    public function contacts(Bands $band, Bookings $booking)
    {
        $booking->load('contacts');

        // If bookingHistory is protected/hidden, make it visible after loading
        $booking->contacts->each(function ($contact)
        {
            $contact->append('booking_history');
        });

        return Inertia::render('Bookings/Contacts', ['booking' => $booking, 'band' => $band]);
    }

    public function storeContact(BookingContactRequest $request, Bands $band, Bookings $booking)
    {
        $contact = Contacts::firstOrCreate(
            ['email' => $request->email],
            $request->only(['name', 'phone']) + ['band_id' => $band->id]
        );

        $booking->contacts()->syncWithoutDetaching([
            $contact->id => $request->only(['role', 'is_primary', 'notes', 'additional_info'])
        ]);
        return redirect()->back()->with('successMessage', "{$request->name} has been added.");
    }

    public function updateContact(BookingContactRequest $request, Bands $band, Bookings $booking, BookingContacts $contact)
    {
        $contact->update($request->validated());
        $contact->contact->update(collect($request->validated())->only(['name', 'email', 'phone'])->filter()->toArray());
        return redirect()->back()->with('successMessage', "{$request->name} has been updated.");
    }

    public function destroyContact(Bands $band, Bookings $booking, BookingContacts $contact)
    {
        $contact->delete($contact);
        
        return redirect()->back()->with('successMessage', "{$contact->contact->name} has been removed from booking.");
    }



    public function finances(Bands $band, Bookings $booking)
    {
        $booking->load(['contacts', 'payments.invoice', 'contract']);
        $booking->amountPaid = $booking->amountPaid;
        $booking->amountLeft = $booking->amountLeft;

        return Inertia::render('Bookings/Finances', [
            'booking' => $booking,
            'band' => $band,
            'payments' => $booking->payments
        ]);
    }

    public function storePayment(StoreBookingPaymentRequest $request, Bands $band, Bookings $booking)
    {
        $payment = $booking->payments()->create($request->validated());
        event(new PaymentWasReceived($payment));
        return redirect()->back()->with('successMessage', 'Payment has been added.');
    }

    public function destroyPayment(Bands $band, Bookings $booking, $payment)
    {
        $booking->payments()->find($payment)->delete();
        return redirect()->back()->with('successMessage', 'Payment has been removed.');
    }

    public function receipt(Bands $band, Bookings $booking)
    {
        // $booking->payments = $booking->payments;

        $paymentPDF = $booking->getPaymentPdf();
        if ($paymentPDF === null)
        {
            // Handle the error, e.g., return an error response
            return response('Failed to generate PDF', 500);
        }

        // return response()->download('/tmp/test.pdf', 'test.pdf');

        return Response::streamDownload(
            function () use ($paymentPDF)
            {
                echo $paymentPDF;
            },
            Str::slug($booking->name . '_Receipt', '_') . '.pdf',
            [
                'Content-type' => 'application/pdf'
            ]
        );
    }

    public function paymentPDF(Bookings $booking)
    {
        return view('pdf.bookingPayment', ['booking' => $booking]);
    }

    public function downloadContract(Bands $band, Bookings $booking)
    {
        $contractPDF = $booking->getContractPdf();
        if ($contractPDF === null)
        {
            // Handle the error, e.g., return an error response
            return response('Failed to generate PDF', 500);
        }

        return Response::streamDownload(
            function () use ($contractPDF)
            {
                echo $contractPDF;
            },
            Str::slug($booking->name . '_Contract', '_') . '.pdf',
            [
                'Content-type' => 'application/pdf'
            ]
        );
    }

    public function destroy(Bands $band, Bookings $booking)
    {
        $booking->contacts()->detach();
        $booking->events()->delete();
        $booking->delete();
        
        return redirect()->route('Bookings Home')->with('successMessage', "{$booking->name} has been deleted.");
    }

    public function cancelBooking(Bands $band, Bookings $booking)
    {
        $booking->status = 'cancelled';
        $booking->save();
        return redirect()->route('Bookings Home')->with('successMessage', "{$booking->name} has been cancelled.");
    }

    public function contract(Bands $band, Bookings $booking)
    {
        $booking->contacts = $booking->contacts()->get();
        if (count($booking->contacts) === 0 && $booking->contract_option !== 'none')
        {
            return redirect()->route('Booking Contacts', [$band, $booking])->with('warningMessage', 'Please add a contact before generating a contract.');
        }
        $booking->contract = $booking->contract;
        $booking->duration = $booking->duration;

        return Inertia::render('Bookings/Contract', [
            'booking' => $booking,
            'band' => $band,
        ]);
    }

    public function uploadContract(UploadBookingContractRequest $request, Bands $band, Bookings $booking)
    {
        $booking->storeContractPdf($request->file('pdf')->get());
        $booking->status = 'confirmed';
        $booking->save();
        return redirect()->back()->with('successMessage', 'Contract has been uploaded.');
    }

    public function events(Bands $band, Bookings $booking)
    {
        // Get events with last updated user info from activity log
        $events = $booking->events->map(function ($event) {
            // Get the last update activity
            $lastActivity = $event->activities()
                ->where('description', 'updated')
                ->latest()
                ->first();
            
            // Add last updated user info if available
            if ($lastActivity && $lastActivity->causer) {
                $event->last_updated_by = [
                    'id' => $lastActivity->causer->id,
                    'name' => $lastActivity->causer->name,
                ];
            }
            
            return $event;
        });
        
        return Inertia::render('Bookings/Events', [
            'booking' => $booking,
            'events' => $events
        ]);
    }

    public function updateOrCreateEvent(UpdateBookingEventRequest $request, Bands $band, Bookings $booking, Events $event = null)
    {
        $validatedData = $request->validated();

        // If $event is null, it means we're creating a new event
        if (!$event)
        {
            $event = $booking->events()->make([
                'event_type_id' => $booking->event_type_id,
                'key' => Str::uuid(),
            ]);
        }

        // Update or create the event
        $event->fill($validatedData);
        $event->save();

        $message = $event->wasRecentlyCreated ? 'Event Created' : 'Event Updated';

        return redirect()->back()->with('successMessage', $message);
    }

    public function deleteEvent(Bands $band, Bookings $booking, Events $event)
    {
        $event->delete();
        return redirect()->back()->with('successMessage', 'Event Deleted');
    }

    public function storeInvoice(Bands $band, Bookings $booking, Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric',
            'contactId' => 'required|exists:contacts,id',
            'convenienceFee' => 'required|bool',
        ]);
        app(InvoiceServices::class)->createInvoice($booking, $data['amount'], $data['contactId'], $data['convenienceFee']);
    }

    /**
     * Display the activity history for a booking
     *
     * @param  Bands  $band
     * @param  Bookings  $booking
     * @return \Inertia\Response
     */
    public function history(Bands $band, Bookings $booking)
    {
        $activityService = new BookingActivityService();
        $activities = $activityService->getBookingTimeline($booking);
        
        // Load booking with necessary relationships
        $booking->load('band', 'eventType', 'contacts');
        
        return Inertia::render('Bookings/History', [
            'booking' => [
                'id' => $booking->id,
                'name' => $booking->name,
                'date' => $booking->date->format('Y-m-d'),
                'start_time' => $booking->start_time->format('H:i'),
                'end_time' => $booking->end_time->format('H:i'),
                'venue_name' => $booking->venue_name,
                'status' => $booking->status,
                'price' => $booking->price,
                'band_name' => $booking->band->name,
                'event_type' => $booking->eventType->name ?? 'Unknown',
                'contacts_count' => $booking->contacts->count(),
                'amount_paid' => $booking->amount_paid,
            ],
            'band' => $band,
            'activities' => $activities,
        ]);
    }

    /**
     * Enable portal access for a contact
     *
     * @param  Bands  $band
     * @param  Bookings  $booking
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function enableContactPortalAccess(Bands $band, Bookings $booking, Request $request)
    {
        $data = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
        ]);

        $contact = Contacts::findOrFail($data['contact_id']);
        
        // Verify contact belongs to this booking
        if (!$booking->contacts->contains($contact->id)) {
            abort(403, 'Contact not associated with this booking.');
        }

        // Check if contact already has portal access
        if ($contact->can_login) {
            return redirect()->back()->with([
                'warningMessage' => $contact->name . ' already has portal access enabled.',
            ]);
        }

        // Generate a temporary password
        $temporaryPassword = \Str::random(16);
        
        // Update contact with login access
        $contact->update([
            'password' => \Hash::make($temporaryPassword),
            'can_login' => true,
        ]);

        // Send email notification with credentials
        try {
            $contact->notify(new ContactPortalAccessGranted(
                $temporaryPassword,
                $booking->name,
                $band->name
            ));
            
            return redirect()->back()->with([
                'successMessage' => 'Portal access enabled for ' . $contact->name . '. An email with login instructions has been sent.',
            ]);
        } catch (\Exception $e) {
            // Log the error but still consider it a success
            \Log::error('Failed to send portal access email', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with([
                'warningMessage' => 'Portal access enabled for ' . $contact->name . ', but the email could not be sent. Please contact them directly.',
            ]);
        }
    }

    /**
     * Disable portal access for a contact
     *
     * @param  Bands  $band
     * @param  Bookings  $booking
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function disableContactPortalAccess(Bands $band, Bookings $booking, Request $request)
    {
        $data = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
        ]);

        $contact = Contacts::findOrFail($data['contact_id']);
        
        // Verify contact belongs to this booking
        if (!$booking->contacts->contains($contact->id)) {
            abort(403, 'Contact not associated with this booking.');
        }

        // Disable login access
        $contact->update([
            'can_login' => false,
        ]);

        return redirect()->back()->with('successMessage', 'Portal access disabled for ' . $contact->name);
    }

    /**
     * Get activity history for a booking (JSON API endpoint)
     *
     * @param  Bands  $band
     * @param  Bookings  $booking
     * @return \Illuminate\Http\JsonResponse
     */
    public function historyJson(Bands $band, Bookings $booking)
    {
        $activityService = new BookingActivityService();
        $activities = $activityService->getBookingTimeline($booking);
        
        return response()->json([
            'activities' => $activities,
        ]);
    }
}
