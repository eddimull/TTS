<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Services\ContactPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ContactPortalController extends Controller
{
    protected $paymentService;

    public function __construct(ContactPaymentService $paymentService)
    {
        $this->middleware('auth:contact');
        $this->paymentService = $paymentService;
    }

    /**
     * Show the contact dashboard with all their bookings
     */
    public function dashboard()
    {
        $contact = Auth::guard('contact')->user();
        
        // Get all bookings for this contact with payment information
        $bookings = $contact->bookings()
            ->with(['band', 'eventType', 'payments' => function($query) {
                $query->where('status', 'paid');
            }])
            ->where('date', '>=', now()->subMonths(6))
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($booking) {
                // Get the last time the status was changed from activity log
                $statusChangedAt = $booking->activities()
                    ->where('log_name', 'bookings')
                    ->whereJsonContains('properties->attributes->status', $booking->status)
                    ->latest()
                    ->first()?->created_at;

                // If no status change found, use updated_at as fallback
                if (!$statusChangedAt) {
                    $statusChangedAt = $booking->updated_at;
                }

                return [
                    'id' => $booking->id,
                    'name' => $booking->name,
                    'date' => $booking->date->format('M j, Y'),
                    'start_time' => $booking->start_time->format('g:i A'),
                    'end_time' => $booking->end_time->format('g:i A'),
                    'venue_name' => $booking->venue_name,
                    'venue_address' => $booking->venue_address,
                    'status' => $booking->status,
                    'status_changed_at' => $statusChangedAt?->format('M j, Y'),
                    'price' => $booking->price,
                    'amount_paid' => $booking->amount_paid,
                    'amount_due' => $booking->amount_due,
                    'band_name' => $booking->band->name,
                    'event_type' => $booking->eventType?->name,
                    'is_paid' => $booking->is_paid,
                    'has_balance' => $booking->amount_due > 0,
                ];
            });

        return Inertia::render('Contact/Dashboard', [
            'portal' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ],
            'bookings' => $bookings,
        ]);
    }

    /**
     * Show payment page for a specific booking
     */
    public function showPayment(Bookings $booking)
    {
        $contact = Auth::guard('contact')->user();
        
        // Verify this contact is associated with this booking
        if (!$booking->contacts->contains($contact->id)) {
            abort(403, 'Unauthorized access to this booking.');
        }

        return Inertia::render('Contact/Payment', [
            'booking' => [
                'id' => $booking->id,
                'name' => $booking->name,
                'date' => $booking->date->format('Y-m-d'),
                'venue_name' => $booking->venue_name,
                'band_name' => $booking->band->name,
                'price' => $booking->price,
                'amount_paid' => $booking->amount_paid,
                'amount_due' => $booking->amount_due,
            ],
            'portal' => [
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ]);
    }

    /**
     * Create Stripe Checkout Session for payment
     */
    public function createCheckoutSession(Request $request, Bookings $booking)
    {
        $contact = Auth::guard('contact')->user();
        
        // Verify this contact is associated with this booking
        if (!$booking->contacts->contains($contact->id)) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $booking->amount_due,
        ]);
       

        try {
            $checkoutUrl = $this->paymentService->createCheckoutSession(
                $booking,
                $contact,
                $request->amount,
                true // Always apply convenience fee
            );

            return response()->json([
                'checkout_url' => $checkoutUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create payment session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Payment success callback
     */
    public function paymentSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');
        
        return Inertia::render('Contact/PaymentSuccess', [
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Payment cancellation callback
     */
    public function paymentCancelled()
    {
        return Inertia::render('Contact/PaymentCancelled');
    }

    /**
     * Show payment history for the contact
     */
    public function paymentHistory()
    {
        $contact = Auth::guard('contact')->user();
        
        // Get all payments related to this contact's bookings
        $payments = $contact->bookings()
            ->with(['payments' => function($query) {
                $query->where('status', 'paid')->orderBy('date', 'desc');
            }])
            ->get()
            ->flatMap(function ($booking) {
                return $booking->payments->map(function ($payment) use ($booking) {
                    return [
                        'id' => $payment->id,
                        'booking_name' => $booking->name,
                        'booking_date' => $booking->date->format('Y-m-d'),
                        'amount' => $payment->amount,
                        'date' => $payment->date?->format('Y-m-d'),
                        'status' => $payment->status,
                        'band_name' => $booking->band->name,
                    ];
                });
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('Contact/PaymentHistory', [
            'payments' => $payments,
            'contact' => [
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ]);
    }
}
