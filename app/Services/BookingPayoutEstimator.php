<?php

namespace App\Services;

use App\Models\BandPayoutConfig;
use App\Models\Bookings;

/**
 * Read-only payout estimation with the SAME selection semantics everywhere:
 * the booking's stored config (fallback: the band's active one) over the
 * adjusted total (fallback: summed event values).
 *
 * Never writes — the Payout page's render-time cache save is what fed the
 * realtime reload loop; every other surface must estimate without saving.
 */
class BookingPayoutEstimator
{
    /**
     * @return array{config: ?BandPayoutConfig, result: ?array}
     */
    public function estimate(Bookings $booking, int $bandId): array
    {
        $payout = $booking->payout;
        $config = null;

        if ($payout?->payout_config_id) {
            $config = BandPayoutConfig::where('id', $payout->payout_config_id)
                ->where('band_id', $bandId)
                ->with(['band.paymentGroups.users'])
                ->first();
        }

        if (! $config) {
            $config = BandPayoutConfig::where('band_id', $bandId)
                ->where('is_active', true)
                ->with(['band.paymentGroups.users'])
                ->first();
        }

        if (! $config) {
            return ['config' => null, 'result' => null];
        }

        $amount = ($payout && $payout->adjusted_amount_float > 0)
            ? $payout->adjusted_amount_float
            : $booking->total_event_value;

        return ['config' => $config, 'result' => $config->calculatePayouts($amount, null, $booking)];
    }
}
