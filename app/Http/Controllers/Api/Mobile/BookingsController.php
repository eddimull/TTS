<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\BookingContacts;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Payments;
use App\Services\BookingActivityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookingsController extends Controller
{
    /**
     * GET /api/mobile/bands/{band}/bookings
     *
     * List bookings for a band. The `mobile.band` middleware resolves the band
     * from the X-Band-ID header and stores it on the request as `mobile_band`.
     */
    public function index(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');

        if (!$request->user()->canRead('bookings', $band->id)) {
            abort(403, 'You do not have permission to read bookings for this band.');
        }

        $request->validate([
            'status'   => 'nullable|string',
            'upcoming' => 'nullable|boolean',
            'year'     => 'nullable|integer|min:2000|max:2100',
        ]);

        $query = Bookings::where('band_id', $band->id)
            ->with('contacts');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('upcoming')) {
            $query->whereDate('date', '>=', now()->toDateString());
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->integer('year'));
        }

        $bookings = $query->orderBy('date', 'desc')->get();

        $mapped = $bookings->map(fn($booking) => $this->formatBooking($booking));

        return response()->json(['bookings' => $mapped->values()]);
    }

    /**
     * GET /api/mobile/bands/{band}/bookings/{booking}
     *
     * Return full detail for a single booking scoped to a band.
     */
    public function show(Request $request, $band, int $booking): JsonResponse
    {
        $bandModel = $request->input('mobile_band');

        if (!$request->user()->canRead('bookings', $bandModel->id)) {
            abort(403, 'You do not have permission to read bookings for this band.');
        }

        $bookingModel = Bookings::where('band_id', $bandModel->id)
            ->with(['contacts', 'events', 'contract', 'payments'])
            ->findOrFail($booking);

        return response()->json(['booking' => $this->formatBooking($bookingModel)]);
    }

    public function store(Request $request): JsonResponse
    {
        $band = $request->mobile_band;

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'event_type_id'   => 'required|integer|exists:event_types,id',
            'date'            => 'required|date',
            'start_time'      => 'required|date_format:H:i',
            'duration'        => 'required|numeric|min:0.5|max:24',
            'price'           => 'required|numeric|min:0',
            'venue_name'      => 'nullable|string|max:255',
            'venue_address'   => 'nullable|string',
            'contract_option' => 'nullable|in:default,none,external',
            'notes'           => 'nullable|string',
        ]);

        $startDt = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $endDt   = $startDt->copy()->addHours((float) $validated['duration']);
        $endTime = $endDt->format('H:i');

        $status = ($validated['contract_option'] ?? 'default') === 'none' ? 'confirmed' : 'draft';

        $booking = Bookings::create([
            'band_id'         => $band->id,
            'author_id'       => Auth::id(),
            'name'            => $validated['name'],
            'event_type_id'   => $validated['event_type_id'],
            'date'            => $validated['date'],
            'start_time'      => $validated['start_time'],
            'end_time'        => $endTime,
            'price'           => $validated['price'],
            'venue_name'      => $validated['venue_name'] ?? null,
            'venue_address'   => $validated['venue_address'] ?? null,
            'contract_option' => $validated['contract_option'] ?? 'default',
            'notes'           => $validated['notes'] ?? null,
            'status'          => $status,
        ]);

        // Create contract record
        $booking->contract()->create([
            'author_id'    => Auth::id(),
            'status'       => 'pending',
            'custom_terms' => $this->getInitialTerms(),
        ]);

        // Create initial event
        $additionalData = [
            'times' => [
                ['title' => 'Load In',    'time' => $startDt->copy()->subHours(4)->format('Y-m-d H:i')],
                ['title' => 'Soundcheck', 'time' => $startDt->copy()->subHours(3)->format('Y-m-d H:i')],
                ['title' => 'Quiet',      'time' => $startDt->copy()->subHours(1)->format('Y-m-d H:i')],
                ['title' => 'End Time',   'time' => $endDt->format('Y-m-d H:i')],
            ],
            'backline_provided' => false,
            'production_needed' => true,
            'color'             => 'TBD',
            'public'            => true,
            'outside'           => false,
            'lodging'           => [
                ['title' => 'Provided',  'type' => 'checkbox', 'data' => false],
                ['title' => 'location',  'type' => 'text',     'data' => 'TBD'],
                ['title' => 'check_in',  'type' => 'text',     'data' => 'TBD'],
                ['title' => 'check_out', 'type' => 'text',     'data' => 'TBD'],
            ],
        ];

        if ((int) $validated['event_type_id'] === 1) {
            $additionalData['wedding'] = [
                'onsite' => true,
                'dances' => [
                    ['title' => 'first_dance',     'data' => 'TBD'],
                    ['title' => 'father_daughter', 'data' => 'TBD'],
                    ['title' => 'mother_son',      'data' => 'TBD'],
                    ['title' => 'money_dance',     'data' => 'TBD'],
                    ['title' => 'bouquet_garter',  'data' => 'TBD'],
                ],
            ];
            $additionalData['times'][] = ['title' => 'Ceremony', 'time' => $startDt->format('Y-m-d H:i')];
            $additionalData['onsite'] = true;
            $additionalData['public'] = false;
        }

        $defaultRoster = $band->defaultRoster;

        $booking->events()->create([
            'title'           => $booking->name,
            'date'            => $validated['date'],
            'time'            => $validated['start_time'],
            'event_type_id'   => $validated['event_type_id'],
            'value'           => $validated['price'],
            'additional_data' => $additionalData,
            'key'             => Str::uuid()->toString(),
            'roster_id'       => $defaultRoster?->id,
        ]);

        $this->redistributeEventValues($booking->fresh());

        return response()->json([
            'booking' => $this->formatBooking(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments'])
            )
        ], 201);
    }

    public function update(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'event_type_id' => 'sometimes|integer|exists:event_types,id',
            'date'          => 'sometimes|date',
            'start_time'    => 'sometimes|nullable|date_format:H:i',
            'end_time'      => 'sometimes|nullable|date_format:H:i',
            'price'         => 'sometimes|numeric|min:0',
            'venue_name'    => 'sometimes|nullable|string|max:255',
            'venue_address' => 'sometimes|nullable|string',
            'notes'         => 'sometimes|nullable|string',
            'status'        => 'sometimes|in:draft,pending,confirmed,cancelled',
        ]);

        $oldPrice = (float) $booking->price;
        $booking->update($validated);

        if (isset($validated['price']) && (float) $validated['price'] !== $oldPrice) {
            $this->redistributeEventValues($booking->fresh());
        }

        return response()->json([
            'booking' => $this->formatBooking(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments'])
            )
        ]);
    }

    public function destroy(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);
        $booking->contacts()->detach();
        $booking->events()->delete();
        $booking->delete();
        return response()->json(['message' => 'Booking deleted']);
    }

    public function cancel(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);
        $booking->update(['status' => 'cancelled']);
        return response()->json([
            'booking' => $this->formatBooking($booking->fresh()->load(['contacts', 'events', 'contract', 'payments']))
        ]);
    }

    public function contactLibrary(Request $request, $band): JsonResponse
    {
        $band = $request->mobile_band;
        $q    = $request->query('q', '');

        $contacts = Contacts::where('band_id', $band->id)
            ->when($q, fn($query) => $query->where(function ($q2) use ($q) {
                $q2->where('name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json(['contacts' => $contacts]);
    }

    public function storeContact(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);

        $validated = $request->validate([
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'name'       => 'required_without:contact_id|string|max:255',
            'email'      => 'required_without:contact_id|email',
            'phone'      => 'nullable|string',
            'role'       => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ]);

        if (!empty($validated['contact_id'])) {
            $contact = Contacts::where('band_id', $band->id)
                ->findOrFail($validated['contact_id']);
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
            'contacts' => $this->formatContacts($booking->fresh()->contacts)
        ]);
    }

    public function updateContact(Request $request, $band, $booking, $bc): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);

        $pivotRow = BookingContacts::where('booking_id', $booking->id)
            ->findOrFail($bc);

        $validated = $request->validate([
            'role'       => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ]);

        $pivotRow->update($validated);

        return response()->json([
            'contacts' => $this->formatContacts($booking->fresh()->contacts)
        ]);
    }

    public function destroyContact(Request $request, $band, $booking, $bc): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);

        BookingContacts::where('booking_id', $booking->id)
            ->findOrFail($bc)
            ->delete();

        return response()->json(['message' => 'Contact removed']);
    }

    public function storePayment(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0.01',
            'date'         => 'required|date',
            'payment_type' => 'required|in:cash,check,portal,venmo,zelle,invoice,wire,credit_card,other',
            'status'       => 'nullable|in:paid,pending',
        ]);

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
            'payment'     => $this->formatPayment($payment),
            'amount_paid' => (string) $booking->amount_paid,
            'amount_due'  => (string) $booking->amount_due,
            'is_paid'     => (bool) $booking->is_paid,
        ]);
    }

    public function destroyPayment(Request $request, $band, $booking, $payment): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);
        $payment = $booking->payments()->findOrFail($payment);
        $payment->delete();
        $booking->refresh();

        return response()->json([
            'message'     => 'Payment deleted',
            'amount_paid' => (string) $booking->amount_paid,
            'amount_due'  => (string) $booking->amount_due,
            'is_paid'     => (bool) $booking->is_paid,
        ]);
    }

    public function showContract(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->with('contract')->findOrFail($booking);
        $c       = $booking->contract;

        return response()->json([
            'contract_option' => $booking->contract_option,
            'contract'        => $c ? [
                'id'          => $c->id,
                'status'      => $c->status,
                'asset_url'   => $c->asset_url,
                'envelope_id' => $c->envelope_id,
                'updated_at'  => $c->updated_at?->format('Y-m-d'),
            ] : null,
        ]);
    }

    public function uploadContract(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);

        $request->validate(['pdf' => 'required|file|mimes:pdf|max:20480']);

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
            'booking' => $this->formatBooking(
                $booking->fresh()->load(['contacts', 'events', 'contract', 'payments'])
            )
        ]);
    }

    public function sendContract(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)
            ->with(['contacts', 'contract'])
            ->findOrFail($booking);

        $validated = $request->validate([
            'signer_id' => 'required|integer',
            'cc_id'     => 'nullable|integer',
        ]);

        $contact = $booking->contacts()->find($validated['signer_id']);

        if (!$contact) {
            return response()->json(['message' => 'Signer contact not found on this booking.'], 422);
        }

        $ccContact = null;
        if (!empty($validated['cc_id'])) {
            $ccContact = $booking->contacts()->find($validated['cc_id']);
        }

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
                'booking' => $this->formatBooking(
                    $booking->fresh()->load(['contacts', 'events', 'contract', 'payments'])
                )
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send contract: ' . $e->getMessage()], 500);
        }
    }

    public function showHistory(Request $request, $band, $booking): JsonResponse
    {
        $band    = $request->mobile_band;
        $booking = Bookings::where('band_id', $band->id)->findOrFail($booking);

        $service = new BookingActivityService();

        try {
            $history = $service->getBookingTimeline($booking);
            return response()->json(['history' => $history]);
        } catch (\Exception) {
            return response()->json(['history' => []]);
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function formatBooking(Bookings $booking): array
    {
        $base = [
            'id'              => $booking->id,
            'name'            => $booking->name,
            'date'            => $booking->date?->format('Y-m-d'),
            'start_time'      => $booking->start_time,
            'end_time'        => $booking->end_time,
            'venue_name'      => $booking->venue_name,
            'venue_address'   => $booking->venue_address,
            'status'          => $booking->status,
            'price'           => (string) $booking->price,
            'event_type_id'   => $booking->event_type_id,
            'notes'           => $booking->notes,
            'amount_paid'     => (string) $booking->amount_paid,
            'amount_due'      => (string) $booking->amount_due,
            'is_paid'         => (bool) $booking->is_paid,
            'contract_option' => $booking->contract_option,
            'contacts'        => $this->formatContacts($booking->contacts),
            'events'          => $booking->relationLoaded('events')
                ? $booking->events->map(fn($e) => [
                    'id'    => $e->id,
                    'key'   => $e->key,
                    'title' => $e->title,
                    'date'  => $e->date?->format('Y-m-d'),
                    'time'  => $e->time,
                ])->values()->all()
                : [],
            'contract' => null,
            'payments'  => [],
        ];

        if ($booking->relationLoaded('contract') && $booking->contract) {
            $c = $booking->contract;
            $base['contract'] = [
                'id'          => $c->id,
                'status'      => $c->status,
                'asset_url'   => $c->asset_url,
                'envelope_id' => $c->envelope_id,
            ];
        }

        if ($booking->relationLoaded('payments')) {
            $base['payments'] = $booking->payments
                ->map(fn($p) => $this->formatPayment($p))
                ->values()->all();
        }

        return $base;
    }

    private function formatContacts($contacts): array
    {
        return $contacts->map(fn($c) => [
            'id'         => $c->id,
            'bc_id'      => $c->pivot->id ?? null,
            'contact_id' => $c->id,
            'name'       => $c->name,
            'email'      => $c->email,
            'phone'      => $c->phone,
            'role'       => $c->pivot->role ?? null,
            'is_primary' => (bool) ($c->pivot->is_primary ?? false),
        ])->values()->all();
    }

    private function formatPayment(Payments $payment): array
    {
        return [
            'id'           => $payment->id,
            'name'         => $payment->name,
            'amount'       => (string) $payment->amount,
            'date'         => $payment->date?->format('Y-m-d'),
            'payment_type' => $payment->payment_type instanceof \App\Enums\PaymentType
                              ? $payment->payment_type->value
                              : $payment->payment_type,
            'status'       => $payment->status,
        ];
    }

    private function getInitialTerms(): array
    {
        $path = storage_path('app/contract/InitialTerms.json');
        if (file_exists($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function redistributeEventValues(Bookings $booking): void
    {
        $events = $booking->events()->get();
        if ($events->isEmpty()) return;
        $share = $booking->price / $events->count();
        foreach ($events as $event) {
            $event->update(['value' => $share]);
        }
    }
}
