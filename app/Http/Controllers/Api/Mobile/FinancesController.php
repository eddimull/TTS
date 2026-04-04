<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use App\Services\FinanceServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancesController extends Controller
{
    public function __construct(
        private readonly FinanceServices $financeServices
    ) {}

    /**
     * GET /api/mobile/bands/{band}/finances
     *
     * Returns both unpaid and paid bookings for the band.
     * Accepts optional query param: ?year=2026
     */
    public function index(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');

        if (!$request->user()->canRead('bookings', $band->id)) {
            abort(403, 'You do not have permission to view finances for this band.');
        }

        $request->validate(['year' => 'nullable|integer|min:2000|max:2100']);
        $year = $request->integer('year') ?: null;

        $unpaid = $band->getUnpaidBookings();
        $unpaid->loadMissing('payments');
        $unpaid = $this->financeServices->filterByYear($unpaid, $year);

        $paid = $band->getPaidBookings();
        $paid->loadMissing('payments');
        $paid = $this->financeServices->filterByYear($paid, $year);

        return response()->json([
            'unpaid' => $unpaid->map(fn($booking) => $this->formatFinanceBooking($booking))->values(),
            'paid'   => $paid->map(fn($booking) => $this->formatFinanceBooking($booking))->values(),
        ]);
    }

    /**
     * GET /api/mobile/bands/{band}/finances/unpaid
     *
     * Returns unpaid bookings for the band.
     * Accepts optional query param: ?year=2026
     */
    public function unpaid(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');

        if (!$request->user()->canRead('bookings', $band->id)) {
            abort(403, 'You do not have permission to view finances for this band.');
        }

        $request->validate(['year' => 'nullable|integer|min:2000|max:2100']);
        $year = $request->integer('year') ?: null;

        $unpaid = $band->getUnpaidBookings();
        $unpaid->loadMissing('payments');
        $unpaid = $this->financeServices->filterByYear($unpaid, $year);

        return response()->json([
            'bookings' => $unpaid->map(fn($booking) => $this->formatFinanceBooking($booking))->values(),
        ]);
    }

    /**
     * GET /api/mobile/bands/{band}/finances/paid
     *
     * Returns paid bookings for the band.
     * Accepts optional query param: ?year=2026
     */
    public function paid(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');

        if (!$request->user()->canRead('bookings', $band->id)) {
            abort(403, 'You do not have permission to view finances for this band.');
        }

        $request->validate(['year' => 'nullable|integer|min:2000|max:2100']);
        $year = $request->integer('year') ?: null;

        $paid = $band->getPaidBookings();
        $paid->loadMissing('payments');
        $paid = $this->financeServices->filterByYear($paid, $year);

        return response()->json([
            'bookings' => $paid->map(fn($booking) => $this->formatFinanceBooking($booking))->values(),
        ]);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function formatFinanceBooking(Bookings $booking): array
    {
        return [
            'id'           => $booking->id,
            'name'         => $booking->name ?? '',
            'date'         => $booking->date?->format('Y-m-d') ?? '',
            'start_time'   => $booking->start_time ?? '',
            'end_time'     => $booking->end_time ?? '',
            'venue_name'   => $booking->venue_name ?? '',
            'venue_address' => $booking->venue_address ?? '',
            'status'       => $booking->status ?? '',
            'price'        => (string) $booking->price,
            'amount_paid'  => (string) $booking->amount_paid,
            'amount_due'   => (string) $booking->amount_due,
            'is_paid'      => (bool) $booking->is_paid,
        ];
    }
}
