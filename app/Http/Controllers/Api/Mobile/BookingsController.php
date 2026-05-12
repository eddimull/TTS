<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\BookingIndexRequest;
use App\Http\Requests\Mobile\SendBookingContractRequest;
use App\Http\Requests\Mobile\StoreBookingContactRequest;
use App\Http\Requests\Mobile\StoreBookingPaymentRequest;
use App\Http\Requests\Mobile\StoreBookingRequest;
use App\Http\Requests\Mobile\UpdateBookingContactRequest;
use App\Http\Requests\Mobile\UpdateBookingContractTermsRequest;
use App\Http\Requests\Mobile\UpdateBookingRequest;
use App\Http\Requests\Mobile\UploadBookingContractRequest;
use App\Models\Bands;
use App\Models\BookingContacts;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\Payments;
use App\Services\BookingActivityService;
use App\Services\Mobile\BookingFormatter;
use App\Services\Mobile\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingsController extends Controller
{
    public function __construct(
        private readonly BookingFormatter $formatter,
        private readonly BookingService $bookingService,
    ) {}

    /**
     * GET /api/mobile/bands/{band}/bookings
     */
    public function index(BookingIndexRequest $request, Bands $band): JsonResponse
    {
        $query = $band->bookings()->with(['contacts', 'band', 'events']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('upcoming')) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '>=', now()->toDateString()));
        }

        if ($request->filled('year')) {
            $query->whereHas('events', fn ($q) => $q->whereYear('date', $request->integer('year')));
        }

        $bookings = $query->get()
            ->sortByDesc(fn ($b) => $b->start_date?->format('Y-m-d') ?? '')
            ->values();

        return response()->json([
            'bookings' => $bookings->map(fn ($b) => $this->formatter->format($b))->values(),
        ]);
    }

    /**
     * GET /api/mobile/me/bookings
     *
     * Returns bookings across every band the authenticated user belongs to.
     * Used by the multi-band Bookings tab on mobile.
     */
    public function indexForUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d',
        ]);
        if (
            !empty($validated['from']) &&
            !empty($validated['to']) &&
            $validated['from'] > $validated['to']
        ) {
            throw ValidationException::withMessages([
                'from' => ['from must be on or before to'],
            ]);
        }

        $user = $request->user();
        // Use bands() not allBands(): subs are authorized at the event level
        // (see User::getEventsAttribute) and bookings carry money/contract info
        // they shouldn't see. Subs get an empty Bookings tab; their assigned
        // events still surface via the Dashboard/events endpoints.
        $bandIds = $user->bands()->pluck('id');

        $query = Bookings::query()
            ->with(['band', 'contacts', 'events'])
            ->whereIn('band_id', $bandIds);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('upcoming')) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '>=', now()->toDateString()));
        }

        if ($request->filled('year')) {
            $query->whereHas('events', fn ($q) => $q->whereYear('date', $request->integer('year')));
        }

        if (!empty($validated['from'])) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '>=', $validated['from']));
        }

        if (!empty($validated['to'])) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '<=', $validated['to']));
        }

        $bookings = $query->get()
            ->sortByDesc(fn ($b) => $b->start_date?->format('Y-m-d') ?? '')
            ->values();

        return response()->json([
            'bookings' => $bookings->map(fn ($b) => $this->formatter->format($b))->values(),
        ]);
    }

    /**
     * GET /api/mobile/bands/{band}/bookings/{booking}
     */
    public function show(Request $request, Bands $band, Bookings $booking): JsonResponse
    {
        $booking->load(['contacts', 'events', 'contract', 'payments', 'band']);

        return response()->json(['booking' => $this->formatter->format($booking)]);
    }

    public function store(StoreBookingRequest $request, Bands $band): JsonResponse
    {
        $validated = $request->validated();

        $startDt = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $endDt   = $startDt->copy()->addHours((float) $validated['duration']);
        $endTime = $endDt->format('H:i');

        $status = ($validated['contract_option'] ?? 'default') === 'none' ? 'confirmed' : 'draft';

        // date/start_time/end_time/venue_name/venue_address now live on events,
        // not bookings. Store only booking-level fields here.
        $attrs = [
            'band_id'         => $band->id,
            'author_id'       => Auth::id(),
            'name'            => $validated['name'],
            'event_type_id'   => $validated['event_type_id'],
            'price'           => $validated['price'] ?? null,
            'contract_option' => $validated['contract_option'] ?? 'default',
            'notes'           => $validated['notes'] ?? null,
            'status'          => $status,
        ];
        $booking = Bookings::create($attrs);

        $booking->contract()->create([
            'author_id'    => Auth::id(),
            'status'       => 'pending',
            'custom_terms' => $this->bookingService->getInitialTerms(),
        ]);

        $additionalData = $this->bookingService->buildAdditionalData($validated, $startDt, $endDt);
        $this->bookingService->createInitialEvent($booking, $validated, $additionalData);
        $this->bookingService->redistributeEventValues($booking->fresh());

        return response()->json([
            'booking' => $this->formatter->format(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments', 'band'])
            ),
        ], 201);
    }

    public function update(UpdateBookingRequest $request, Bands $band, Bookings $booking): JsonResponse
    {
        $validated = $request->validated();
        $oldPrice  = (float) $booking->price;

        $booking->update($validated);

        if (isset($validated['price']) && (float) $validated['price'] !== $oldPrice) {
            $this->bookingService->redistributeEventValues($booking->fresh());
        }

        return response()->json([
            'booking' => $this->formatter->format(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments', 'band'])
            ),
        ]);
    }

    public function destroy(Request $request, Bands $band, Bookings $booking): JsonResponse
    {
        $booking->contacts()->detach();
        $booking->delete();

        return response()->json(['message' => 'Booking deleted']);
    }

    public function cancel(Request $request, Bands $band, Bookings $booking): JsonResponse
    {
        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'booking' => $this->formatter->format(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments', 'band'])
            ),
        ]);
    }

    public function storeEvent(
        \App\Http\Requests\Mobile\StoreBookingEventRequest $request,
        Bands $band,
        Bookings $booking,
    ): JsonResponse {
        $event = $booking->events()->create(array_merge(
            $request->validated(),
            ['key' => Str::uuid()],
        ));

        // Redistribute event values when a new event is added.
        $this->bookingService->redistributeEventValues($booking->fresh());

        return response()->json([
            'event' => $this->formatter->formatEvent($event->fresh()),
        ], 201);
    }

    public function updateEvent(
        \App\Http\Requests\Mobile\UpdateBookingEventRequest $request,
        Bands $band,
        Bookings $booking,
        Events $event,
    ): JsonResponse {
        if ($event->eventable_type !== Bookings::class || $event->eventable_id !== $booking->id) {
            return response()->json(['error' => 'Event does not belong to this booking.'], 404);
        }

        $event->update($request->validated());

        return response()->json([
            'event' => $this->formatter->formatEvent($event->fresh()),
        ]);
    }

    public function destroyEvent(
        Bands $band,
        Bookings $booking,
        Events $event,
    ): JsonResponse {
        if ($event->eventable_type !== Bookings::class || $event->eventable_id !== $booking->id) {
            return response()->json(['error' => 'Event does not belong to this booking.'], 404);
        }

        if ($booking->events()->count() <= 1) {
            return response()->json([
                'error' => 'Cannot delete the last event of a booking. A booking must have at least one event.',
            ], 422);
        }

        $event->delete();
        $this->bookingService->redistributeEventValues($booking->fresh());

        return response()->json(['message' => 'Event deleted.']);
    }

    public function contactLibrary(Request $request, Bands $band): JsonResponse
    {
        $q = $request->query('q', '');

        $contacts = Contacts::where('band_id', $band->id)
            ->when($q, fn ($query) => $query->where(function ($q2) use ($q) {
                $q2->where('name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json(['contacts' => $contacts]);
    }

    public function storeContact(StoreBookingContactRequest $request, Bands $band, Bookings $booking): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['contact_id'])) {
            $contact = Contacts::where('band_id', $band->id)->findOrFail($validated['contact_id']);
        } else {
            $contact = Contacts::firstOrCreate(
                ['band_id' => $band->id, 'email' => $validated['email']],
                ['name' => $validated['name'], 'phone' => $validated['phone'] ?? null]
            );
        }

        $booking->contacts()->syncWithoutDetaching([
            $contact->id => [
                'role'       => $validated['role'] ?? null,
                'is_primary' => $validated['is_primary'] ?? false,
            ],
        ]);

        return response()->json([
            'contacts' => $this->formatter->formatContacts($booking->fresh()->contacts),
        ]);
    }

    public function updateContact(UpdateBookingContactRequest $request, Bands $band, Bookings $booking, BookingContacts $bookingContact): JsonResponse
    {
        $bookingContact->update($request->validated());

        return response()->json([
            'contacts' => $this->formatter->formatContacts($booking->fresh()->contacts),
        ]);
    }

    public function destroyContact(Request $request, Bands $band, Bookings $booking, BookingContacts $bookingContact): JsonResponse
    {
        $bookingContact->delete();

        return response()->json(['message' => 'Contact removed']);
    }

    public function storePayment(StoreBookingPaymentRequest $request, Bands $band, Bookings $booking): JsonResponse
    {
        $validated = $request->validated();

        $payment = $booking->payments()->create([
            'name'         => $validated['name'],
            'amount'       => $validated['amount'],
            'date'         => $validated['date'],
            'payment_type' => $validated['payment_type'],
            'status'       => $validated['status'] ?? 'paid',
            'band_id'      => $band->id,
            'user_id'      => Auth::id(),
            'payer_type'   => \App\Models\User::class,
            'payer_id'     => Auth::id(),
        ]);

        $booking->refresh();

        return response()->json([
            'payment'     => $this->formatter->formatPayment($payment),
            'amount_paid' => (string) $booking->amount_paid,
            'amount_due'  => (string) $booking->amount_due,
            'is_paid'     => (bool) $booking->is_paid,
        ]);
    }

    public function destroyPayment(Request $request, Bands $band, Bookings $booking, Payments $payment): JsonResponse
    {
        $payment->delete();
        $booking->refresh();

        return response()->json([
            'message'     => 'Payment deleted',
            'amount_paid' => (string) $booking->amount_paid,
            'amount_due'  => (string) $booking->amount_due,
            'is_paid'     => (bool) $booking->is_paid,
        ]);
    }

    public function showContract(Request $request, Bands $band, Bookings $booking): JsonResponse
    {
        $booking->loadMissing('contract');
        $c = $booking->contract;

        return response()->json([
            'contract_option' => $booking->contract_option,
            'contract'        => $c ? [
                'id'           => $c->id,
                'status'       => $c->status,
                'asset_url'    => $c->asset_url,
                'envelope_id'  => $c->envelope_id,
                'custom_terms' => $c->custom_terms,
                'updated_at'   => $c->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function viewContract(Bands $band, Bookings $booking)
    {
        $contract = $booking->contract;
        if (!$contract || !$contract->asset_url) {
            abort(404, 'Contract not found');
        }

        $filePath = $contract->getFilePath();
        if (!Storage::disk('s3')->exists($filePath)) {
            abort(404, 'Contract file not found');
        }

        $stream = Storage::disk('s3')->readStream($filePath);

        return response()->stream(
            function () use ($stream) { fpassthru($stream); },
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]
        );
    }

    public function downloadContract(Bands $band, Bookings $booking)
    {
        $contract = $booking->contract;
        if (!$contract || !$contract->asset_url) {
            abort(404, 'Contract not found');
        }

        $filePath = $contract->getFilePath();
        if (!Storage::disk('s3')->exists($filePath)) {
            abort(404, 'Contract file not found');
        }

        $stream = Storage::disk('s3')->readStream($filePath);

        return response()->stream(
            function () use ($stream) { fpassthru($stream); },
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . basename($filePath) . '"',
            ]
        );
    }

    public function viewContractUrl(Bands $band, Bookings $booking): JsonResponse
    {
        if (!$booking->contract || !$booking->contract->asset_url) {
            abort(404, 'Contract not found');
        }

        $expiresAt = now()->addMinutes(15);
        $url = URL::temporarySignedRoute(
            'mobile.bookings.contract.view.signed',
            $expiresAt,
            ['band' => $band->id, 'booking' => $booking->id],
        );

        return response()->json([
            'url'        => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function viewContractSigned(Bands $band, Bookings $booking)
    {
        if (!request()->hasValidSignature()) {
            abort(403);
        }

        return $this->viewContract($band, $booking);
    }

    public function uploadContract(UploadBookingContractRequest $request, Bands $band, Bookings $booking): JsonResponse
    {
        $path     = Storage::put("contracts/bookings/{$booking->id}", $request->file('pdf'));
        $contract = $booking->contract;

        if (!$contract) {
            $contract = $booking->contract()->create([
                'author_id' => Auth::id(),
                'status'    => 'completed',
            ]);
        }

        $contract->update(['asset_url' => $path, 'status' => 'completed']);
        $booking->update(['contract_option' => 'external', 'status' => 'confirmed']);

        return response()->json([
            'booking' => $this->formatter->format(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments', 'band'])
            ),
        ]);
    }

    public function sendContract(SendBookingContractRequest $request, Bands $band, Bookings $booking): JsonResponse
    {
        $booking->loadMissing(['contacts', 'contract']);
        $validated = $request->validated();
        $contact   = $booking->contacts()->find($validated['signer_id']);

        if (!$contact) {
            return response()->json(['message' => 'Signer contact not found on this booking.'], 422);
        }

        $ccContact = !empty($validated['cc_id']) ? $booking->contacts()->find($validated['cc_id']) : null;

        try {
            $contractPdf = $booking->getContractPdf($contact);
            $booking->storeContractPdf($contractPdf);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Contract generation failed: ' . $e->getMessage()], 500);
        }

        $booking->refresh();
        $contract = $booking->contract;

        if (!$contract) {
            return response()->json(['message' => 'No contract record found after generation.'], 500);
        }

        try {
            $contract->sendToPandaDoc($contact, $ccContact);
            $booking->update(['status' => 'pending']);

            return response()->json([
                'booking' => $this->formatter->format(
                    $booking->fresh()->load(['contacts', 'events', 'contract', 'payments', 'band'])
                ),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send contract: ' . $e->getMessage()], 500);
        }
    }

    public function saveContractTerms(
        UpdateBookingContractTermsRequest $request,
        Bands $band,
        Bookings $booking
    ): JsonResponse {
        $validated = $request->validated();

        $contract = $booking->contract()->firstOrCreate(
            [],
            ['author_id' => $request->user()->id]
        );

        $contract->update(['custom_terms' => $validated['custom_terms']]);

        return response()->json([
            'booking' => $this->formatter->format(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments', 'band'])
            ),
        ]);
    }

    public function showHistory(Request $request, Bands $band, Bookings $booking): JsonResponse
    {
        try {
            $history = (new BookingActivityService())->getBookingTimeline($booking);
            return response()->json(['history' => $history]);
        } catch (\Exception) {
            return response()->json(['history' => []]);
        }
    }

    public function contractHistory(Request $request, Contracts $contract): JsonResponse
    {
        // Resolve the owning band via the polymorphic contractable (Bookings).
        $contractable = $contract->contractable;
        if (!($contractable instanceof Bookings)) {
            abort(404);
        }

        $band = $contractable->band;
        if (!$band) {
            abort(404);
        }

        // Authorize: user must belong to the band (owner or member). Subs are
        // intentionally excluded — contracts carry signer PII and money info
        // they shouldn't see (matches indexForUser's bands() vs allBands() rule).
        $user = $request->user();
        if (!$user->bands()->contains('id', $band->id)) {
            abort(403);
        }

        return response()->json(['history' => $contract->auditTrail()]);
    }
}
