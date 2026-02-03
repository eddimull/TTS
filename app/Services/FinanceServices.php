<?php

namespace App\Services;

use Error;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceServices
{
    function getBandFinances($bands)
    {

        foreach ($bands as $band)
        {
            $band->completedBookings;
        }

        return $bands;
    }

    function getUnpaid($bands)
    {
        foreach ($bands as $band)
        {
            $band->unpaidBookings = $band->getUnpaidBookings();
        }

        return $bands;
    }

    function getPaid($bands)
    {
        foreach ($bands as $band)
        {
            $band->paidBookings = $band->getPaidBookings();
        }

        return $bands;
    }

    function getPaidUnpaid($bands, $snapshotDate = null)
    {
        foreach ($bands as $band)
        {
            $band->paidBookings = $band->getPaidBookings($snapshotDate)->map(function ($booking) {
                // Add net amount (price - band cut)
                $this->addNetAmount($booking);
                return $booking;
            });

            $band->unpaidBookings = $band->getUnpaidBookings($snapshotDate)->map(function ($booking) {
                // Add net amount (price - band cut)
                $this->addNetAmount($booking);
                return $booking;
            });
        }

        return $bands;
    }

    /**
     * Calculate and add net amount to booking (band's revenue = band cut)
     */
    private function addNetAmount($booking)
    {
        $booking->load('payout', 'band.activePayoutConfig');

        $price = is_string($booking->price) ? floatval($booking->price) : $booking->price;
        $bandCut = 0;

        // Get band cut from payout calculation if available
        if ($booking->payout && $booking->payout->calculation_result) {
            $bandCut = $booking->payout->calculation_result['band_cut'] ?? 0;
        } elseif ($booking->band && $booking->band->activePayoutConfig) {
            // Calculate band cut using active config
            $config = $booking->band->activePayoutConfig;

            if ($config->band_cut_type === 'percentage') {
                $bandCut = ($price * $config->band_cut_value) / 100;
            } elseif ($config->band_cut_type === 'fixed') {
                $bandCut = $config->band_cut_value;
            }
        }

        // Net amount is what the band keeps (the band cut)
        $booking->net_amount = $bandCut;
        $booking->band_cut = $bandCut;
    }

    function getBandRevenueByYear($bands)
    {
        return $bands->load(['payments' => function ($query)
        {
            $query->select('band_id', DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total'))
                ->where('date', '!=', null) // Invoices that are pending do not have a payment date
                ->groupBy('band_id', DB::raw('YEAR(date)'))
                ->orderBy('year', 'desc');
        }]);
    }

    function getBandPayments($bands)
    {
        foreach ($bands as $band)
        {
            $band->payments = $band->payments()->with('payable')
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($payment)
                {
                    return [
                        'id' => $payment->id,
                        'name' => $payment->name,
                        'payable_type' => $payment->payable_type,
                        'payable_name' => $payment->payable->name,
                        'payable_date' => $payment->payable?->date ? $payment->payable->date->format('Y-m-d') : null,
                        'payable_id' => $payment->payable_id,
                        'formattedPaymentDate' => $payment->date ? $payment->date->format('Y-m-d') : null,
                        'formattedPaymentAmount' => number_format($payment->amount, 2)
                    ];
                });
        }
        return $bands;
    }

}
