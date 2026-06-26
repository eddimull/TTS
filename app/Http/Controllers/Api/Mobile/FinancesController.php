<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\FinanceYearRequest;
use App\Services\FinanceServices;
use App\Services\Mobile\BookingFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * GET /api/mobile/bands/{band}/finances/revenue
     *
     * Returns total recorded revenue grouped by year (newest first), scoped to
     * the band. Amounts are in cents. Payments without a date (e.g. pending
     * invoices) are excluded.
     */
    public function revenue(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');

        $revenue = $band->paymentsByYear()
            ->whereNotNull('date')
            ->get()
            ->map(fn ($row) => [
                'year'  => (int) $row->year,
                'total' => (int) $row->total,
            ])
            ->values();

        return response()->json(['revenue' => $revenue]);
    }

    /**
     * GET /api/mobile/bands/{band}/finances/trends
     *
     * Per-month paid/unpaid/forecast/net/count for a year (cents), scoped to the
     * band. Optional ?snapshot_date=Y-m-d limits the primary series to bookings
     * created on/before that date; ?compare_with_current=1 (only with a snapshot)
     * additionally returns the current (unfiltered) series as current_months.
     */
    public function trends(Request $request): JsonResponse
    {
        $band = $request->input('mobile_band');
        $year = $request->integer('year') ?: (int) date('Y');
        $snapshotDate = $request->input('snapshot_date');
        $compare = $request->boolean('compare_with_current');

        $months = $this->bucketByMonth($band, $year, $snapshotDate);

        $payload = [
            'year' => $year,
            'snapshot_date' => $snapshotDate,
            'available_years' => $this->availableYears($band),
            'months' => $months,
        ];

        if ($compare && $snapshotDate) {
            $payload['current_months'] = $this->bucketByMonth($band, $year, null);
        }

        return response()->json($payload);
    }

    private function bucketByMonth($band, int $year, ?string $snapshotDate): array
    {
        $bands = $this->financeServices->getPaidUnpaid([$band], $snapshotDate);
        $b = $bands->first();
        $bookings = collect($b->paidBookings)->concat(collect($b->unpaidBookings));

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $rows[$m] = ['month' => $m, 'paid' => 0.0, 'unpaid' => 0.0, 'forecast' => 0.0, 'net' => 0.0, 'count' => 0];
        }

        foreach ($bookings as $booking) {
            if (($booking->status ?? null) === 'cancelled') continue;
            if (empty($booking->start_date)) continue;
            $date = \Carbon\Carbon::parse($booking->start_date);
            if ((int) $date->year !== $year) continue;
            $m = (int) $date->month;
            $price = (float) $booking->price;
            $paid = (float) $booking->amount_paid;
            $net = (float) ($booking->net_amount ?? 0);
            $rows[$m]['forecast'] += $price;
            $rows[$m]['paid'] += $paid;
            $rows[$m]['unpaid'] += max(0, $price - $paid);
            $rows[$m]['net'] += $net;
            $rows[$m]['count'] += 1;
        }

        return array_values(array_map(fn ($r) => [
            'month' => $r['month'],
            'paid' => (int) round($r['paid'] * 100),
            'unpaid' => (int) round($r['unpaid'] * 100),
            'forecast' => (int) round($r['forecast'] * 100),
            'net' => (int) round($r['net'] * 100),
            'count' => $r['count'],
        ], $rows));
    }

    private function availableYears($band): array
    {
        $bands = $this->financeServices->getPaidUnpaid([$band], null);
        $b = $bands->first();
        $bookings = collect($b->paidBookings)->concat(collect($b->unpaidBookings));
        return $bookings
            ->filter(fn ($bk) => ($bk->status ?? null) !== 'cancelled' && !empty($bk->start_date))
            ->map(fn ($bk) => (int) \Carbon\Carbon::parse($bk->start_date)->year)
            ->unique()->sortDesc()->values()->all();
    }
}
