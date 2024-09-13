<?php

namespace App\Services;

use Error;
use Illuminate\Support\Carbon;
use App\Models\ProposalPayments;
use Illuminate\Support\Facades\DB;

class FinanceServices
{
    function getBandFinances($bands)
    {

        foreach ($bands as $band)
        {
            $band->completedProposals;
            foreach ($band->completedProposals as $proposal)
            {
                $proposal->amountPaid = $proposal->amountPaid;
                $proposal->amountLeft = $proposal->amountLeft;
            }
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

    function getPaidUnpaid($bands)
    {
        foreach ($bands as $band)
        {
            $band->paidBookings = $band->getPaidBookings();
            $band->unpaidBookings = $band->getUnpaidBookings();
        }

        return $bands;
    }

    function getBandRevenueByYear($bands)
    {
        return $bands->load(['payments' => function ($query)
        {
            $query->select('band_id', DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total'))
                ->groupBy('band_id', DB::raw('YEAR(date)'))
                ->orderBy('year', 'desc');
        }]);
    }

    function getBandPayments($bands)
    {
        foreach ($bands as $band)
        {
            $band->payments = $band->payments()
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($payment)
                {
                    return [
                        'id' => $payment->id,
                        'name' => $payment->name,
                        'payable_type' => $payment->payable_type,
                        'payable_name' => $payment->payable->name,
                        'payable_date' => $payment->payable->date->format('Y-m-d'),
                        'payable_id' => $payment->payable_id,
                        'formattedPaymentDate' => $payment->date->format('Y-m-d'), // Adjust format as needed
                        'formattedPaymentAmount' => number_format($payment->amount / 100, 2)
                    ];
                });
        }
        return $bands;
    }

    function makePayment($proposal, $paymentName, $amount, $date)
    {
        try
        {

            $payment = ProposalPayments::create([
                'proposal_id' => $proposal->id,
                'name' => $paymentName,
                'amount' => $amount,
                'paymentDate' => Carbon::parse($date)
            ]);
            if ($proposal->amountLeft == '0.00')
            {
                $proposal->paid = true;
                $proposal->save();
            }
            return $payment;
        }
        catch (\Exception $e)
        {
            return back()->withError($e->getMessage())->withInput();
        }
    }

    function removePayment($proposal, $payment)
    {
        try
        {

            $payment->delete();

            if ($proposal->amountLeft !== '0.00')
            {
                $proposal->paid = false;
                $proposal->save();
            }
            return true;
        }
        catch (\Exception $e)
        {
            return back()->withError($e->getMessage())->withInput();
        }
    }
}
