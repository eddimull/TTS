<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\FinanceYearRequest;
use App\Services\FinanceServices;
use App\Services\Mobile\BookingFormatter;
use Illuminate\Http\JsonResponse;

class FinancesController extends Controller
{
    public function __construct(
        private readonly FinanceServices $financeServices,
        private readonly BookingFormatter $formatter,
    ) {}

    /**
     * GET /api/mobile/bands/{band}/finances
     *
     * Returns both unpaid and paid bookings for the band.
     * Accepts optional query param: ?year=2026
     */
    public function index(FinanceYearRequest $request): JsonResponse
    {
        $band = $request->input('mobile_band');
        $year = $request->integer('year') ?: null;

        $unpaid = $band->getUnpaidBookings();
        $unpaid->loadMissing('payments');
        $unpaid = $this->financeServices->filterByYear($unpaid, $year);

        $paid = $band->getPaidBookings();
        $paid->loadMissing('payments');
        $paid = $this->financeServices->filterByYear($paid, $year);

        return response()->json([
            'unpaid' => $unpaid->map(fn ($b) => $this->formatter->formatForFinance($b))->values(),
            'paid'   => $paid->map(fn ($b) => $this->formatter->formatForFinance($b))->values(),
        ]);
    }

    /**
     * GET /api/mobile/bands/{band}/finances/unpaid
     *
     * Returns unpaid bookings for the band.
     * Accepts optional query param: ?year=2026
     */
    public function unpaid(FinanceYearRequest $request): JsonResponse
    {
        $band = $request->input('mobile_band');
        $year = $request->integer('year') ?: null;

        $unpaid = $band->getUnpaidBookings();
        $unpaid->loadMissing('payments');
        $unpaid = $this->financeServices->filterByYear($unpaid, $year);

        return response()->json([
            'bookings' => $unpaid->map(fn ($b) => $this->formatter->formatForFinance($b))->values(),
        ]);
    }

    /**
     * GET /api/mobile/bands/{band}/finances/paid
     *
     * Returns paid bookings for the band.
     * Accepts optional query param: ?year=2026
     */
    public function paid(FinanceYearRequest $request): JsonResponse
    {
        $band = $request->input('mobile_band');
        $year = $request->integer('year') ?: null;

        $paid = $band->getPaidBookings();
        $paid->loadMissing('payments');
        $paid = $this->financeServices->filterByYear($paid, $year);

        return response()->json([
            'bookings' => $paid->map(fn ($b) => $this->formatter->formatForFinance($b))->values(),
        ]);
    }
}
