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
use App\Services\ContactPortalService;
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
            ->with(['contract', 'contacts'])
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
        // Get default roster for the band
        $defaultRoster = $band->defaultRoster;

        $event = [
            'event_type_id' => $booking->event_type_id,
            'key' => Str::uuid(),
            'title' => $booking->name,
            'date' => $booking->date,
            'time' => $booking->start_time,
            'roster_id' => $defaultRoster?->id,
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
            'payments.payer',
            'payments.user',
            'contract',
            'events' => function ($query) {
                $query->orderBy('date', 'desc');
            },
            'events.eventable',
        ]);
        
        // Append calculated financial attributes for frontend consumption
        $booking->append(['amountPaid', 'amountLeft']);
        
        // Get recent activity history
        $activityService = new \App\Services\BookingActivityService();
        $recentActivities = $activityService->getBookingTimeline($booking)->take(10);
        
        // Load active payout configuration and calculate payouts
        $payoutConfig = null;
        $payoutResult = null;

        if ($booking->price > 0) {
            $payoutConfig = \App\Models\BandPayoutConfig::where('band_id', $band->id)
                ->where('is_active', true)
                ->with(['band.paymentGroups.users'])
                ->first();

            if ($payoutConfig) {
                // Calculate payouts with attendance data from all events
                $payoutResult = $payoutConfig->calculatePayouts($booking->price, null, $booking);
            }
        }
        
        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
            'band' => $band,
            'contacts' => $booking->contacts,
            'payments' => $booking->payments,
            'events' => $booking->events,
            'contract' => $booking->contract,
            'recentActivities' => $recentActivities,
            'payoutConfig' => $payoutConfig,
            'payoutResult' => $payoutResult,
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
        $booking->load(['contacts', 'payments.invoice', 'payments.payer', 'payments.user', 'contract']);
        $booking->append(['amountPaid', 'amountLeft']);

        return Inertia::render('Bookings/Finances', [
            'booking' => $booking,
            'band' => $band,
            'payments' => $booking->payments,
            'paymentTypes' => \App\Enums\PaymentType::options()
        ]);
    }

    public function storePayment(StoreBookingPaymentRequest $request, Bands $band, Bookings $booking)
    {
        $paymentData = $request->validated();
        
        // Set the payer to the authenticated user
        $paymentData['payer_type'] = 'App\\Models\\User';
        $paymentData['payer_id'] = Auth::id();
        
        $payment = $booking->payments()->create($paymentData);
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
        $booking->load([
            'events.attachments',
            'events.eventable',
            'events.eventMembers.rosterMember',
            'events.eventMembers.user'
        ]);

        $events = $booking->events->map(function ($event) {
            $this->appendLastUpdatedBy($event);
            $this->appendFormattedAttachmentSizes($event);
            $event->roster_members = $this->formatRosterMembers($event);

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
    public function payout(Bands $band, Bookings $booking)
    {
        $booking->load([
            'band',
            'eventType',
            'payout.adjustments',
            'events.eventMembers.rosterMember',
            'events.eventMembers.user',
        ]);

        $payout = $this->getOrCreatePayout($booking, $band);
        $adjustedTotal = $payout->adjusted_amount_float;

        // Load all available configurations for the band
        $availableConfigs = \App\Models\BandPayoutConfig::where('band_id', $band->id)
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        // Load payout configuration and calculate payouts
        $payoutConfig = null;
        $payoutResult = null;

        if ($adjustedTotal > 0) {
            // Use stored config if exists, otherwise use active config
            if ($payout->payout_config_id) {
                $payoutConfig = \App\Models\BandPayoutConfig::where('id', $payout->payout_config_id)
                    ->where('band_id', $band->id)
                    ->with(['band.paymentGroups.users'])
                    ->first();
            }

            // Fallback to active config if no stored config or stored config not found
            if (!$payoutConfig) {
                $payoutConfig = \App\Models\BandPayoutConfig::where('band_id', $band->id)
                    ->where('is_active', true)
                    ->with(['band.paymentGroups.users'])
                    ->first();
            }

            if ($payoutConfig) {
                // Calculate payouts with attendance data from all events
                $payoutResult = $payoutConfig->calculatePayouts($adjustedTotal, null, $booking);

                // Store calculation result and config ID in payout record
                $payout->payout_config_id = $payoutConfig->id;
                $payout->calculation_result = $payoutResult;
                $payout->save();
            }
        }
        
        // Format events with attendance data
        $events = $booking->events->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->date,
                'time' => $event->time,
                'members' => $event->eventMembers->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'display_name' => $member->display_name,
                        'role' => $member->role ?? $member->rosterMember?->role,
                        'attendance_status' => $member->attendance_status,
                        'user_id' => $member->user_id,
                        'roster_member_id' => $member->roster_member_id,
                    ];
                }),
            ];
        });

        return Inertia::render('Bookings/Payout', [
            'booking' => $booking,
            'band' => $band,
            'payoutConfig' => $payoutConfig,
            'payoutResult' => $payoutResult,
            'payout' => $payout,
            'adjustments' => $payout->adjustments ?? [],
            'adjustedTotal' => $adjustedTotal,
            'events' => $events,
            'availableConfigs' => $availableConfigs,
        ]);
    }

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

        // Use ContactPortalService to grant access (handles password generation, flag setting, and notification)
        $portalService = new ContactPortalService();

        try {
            $portalService->grantPortalAccess($contact, $booking);

            return redirect()->back()->with([
                'successMessage' => 'Portal access enabled for ' . $contact->name . '. An email with login instructions has been sent.',
            ]);
        } catch (\Exception $e) {
            // Log the error and return error message
            \Log::error('Failed to grant portal access', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with([
                'errorMessage' => 'Failed to enable portal access for ' . $contact->name . '. Please try again.',
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

    /**
     * Store a new payout adjustment
     */
    public function storePayoutAdjustment(Request $request, Bands $band, Bookings $booking)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $payout = $this->getOrCreatePayout($booking, $band);
        $adjustment = $payout->adjustments()->create([
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // Recalculate adjusted amount
        $payout->recalculateAdjustedAmount();

        activity()
            ->performedOn($booking)
            ->causedBy(Auth::user())
            ->withProperties([
                'adjustment_id' => $adjustment->id,
                'amount' => $validated['amount'],
                'description' => $validated['description'],
            ])
            ->log('payout_adjustment_added');

        return redirect()->back()->with('successMessage', 'Payout adjustment added successfully');
    }

    /**
     * Delete a payout adjustment
     */
    public function destroyPayoutAdjustment(Bands $band, Bookings $booking, \App\Models\PayoutAdjustment $adjustment)
    {
        // Get the booking's payout
        $payout = $booking->payout;
        if (!$payout) {
            abort(404, 'Payout not found');
        }

        // Verify the adjustment belongs to this payout
        if ($adjustment->payout_id !== $payout->id) {
            abort(403, 'Adjustment does not belong to this booking payout');
        }

        $amount = $adjustment->amount;
        $description = $adjustment->description;

        $adjustment->delete();

        // Recalculate adjusted amount
        $payout->recalculateAdjustedAmount();

        activity()
            ->performedOn($booking)
            ->causedBy(Auth::user())
            ->withProperties([
                'amount' => $amount,
                'description' => $description,
            ])
            ->log('payout_adjustment_removed');

        return redirect()->back()->with('successMessage', 'Payout adjustment removed successfully');
    }

    /**
     * Update the payout configuration for a booking
     */
    public function updatePayoutConfiguration(Request $request, Bands $band, Bookings $booking)
    {
        $validated = $request->validate([
            'payout_config_id' => 'required|exists:band_payout_configs,id',
        ]);

        $payout = $this->getOrCreatePayout($booking, $band);
        $payoutConfig = \App\Models\BandPayoutConfig::where('id', $validated['payout_config_id'])
            ->where('band_id', $band->id)
            ->with(['band.paymentGroups.users'])
            ->firstOrFail();

        // Load booking relationships needed for payout calculation
        $booking->load([
            'events.eventMembers.rosterMember',
            'events.eventMembers.user',
        ]);

        // Update the payout configuration
        $payout->payout_config_id = $payoutConfig->id;

        // Recalculate payouts with the new configuration
        $adjustedTotal = $payout->adjusted_amount_float;
        if ($adjustedTotal > 0) {
            $payoutResult = $payoutConfig->calculatePayouts($adjustedTotal, null, $booking);
            $payout->calculation_result = $payoutResult;
        }

        $payout->save();

        activity()
            ->performedOn($booking)
            ->causedBy(Auth::user())
            ->withProperties([
                'config_id' => $payoutConfig->id,
                'config_name' => $payoutConfig->name,
            ])
            ->log('payout_configuration_updated');

        return redirect()->back()->with('successMessage', 'Payout configuration updated successfully');
    }

    /**
     * Append last updated user info from activity log to the event.
     */
    private function appendLastUpdatedBy(Events $event): void
    {
        $lastActivity = $event->activities()
            ->where('description', 'updated')
            ->latest()
            ->first();

        if ($lastActivity && $lastActivity->causer) {
            $event->last_updated_by = [
                'id' => $lastActivity->causer->id,
                'name' => $lastActivity->causer->name,
            ];
        }
    }

    /**
     * Append formatted file sizes to event attachments.
     */
    private function appendFormattedAttachmentSizes(Events $event): void
    {
        if ($event->attachments) {
            $event->attachments->each(function ($attachment) {
                $attachment->formatted_size = $attachment->formattedSize;
            });
        }
    }

    /**
     * Format roster members with resolved roles.
     */
    private function formatRosterMembers(Events $event): \Illuminate\Support\Collection
    {
        return $event->eventMembers->map(function ($eventMember) use ($event) {
            $role = $this->resolveEventMemberRole($eventMember, $event);

            return [
                'name' => $eventMember->display_name,
                'role' => $role,
            ];
        })->sortBy('name')->values();
    }

    /**
     * Resolve the role for an event member with fallbacks.
     */
    private function resolveEventMemberRole($eventMember, Events $event): ?string
    {
        if ($eventMember->role) {
            return $eventMember->role;
        }

        if ($eventMember->rosterMember) {
            return $eventMember->rosterMember->role;
        }

        // Fallback: find role from current roster by user_id (for data integrity issues)
        if ($eventMember->user_id && $event->eventable) {
            $band = $event->eventable->band ?? null;
            if ($band) {
                $currentRosterMember = $band->rosters()
                    ->where('is_active', true)
                    ->with('members')
                    ->get()
                    ->flatMap(fn($roster) => $roster->members)
                    ->firstWhere('user_id', $eventMember->user_id);

                return $currentRosterMember?->role;
            }
        }

        return null;
    }

    /**
     * Get or create a payout record for a booking.
     */
    private function getOrCreatePayout(Bookings $booking, Bands $band): \App\Models\Payout
    {
        if ($booking->payout) {
            return $booking->payout;
        }

        $baseAmount = is_string($booking->price)
            ? floatval($booking->price)
            : $booking->price;

        return $booking->payout()->create([
            'band_id' => $band->id,
            'base_amount' => $baseAmount,
            'adjusted_amount' => $baseAmount,
        ]);
    }
}
