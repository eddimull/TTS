<?php

namespace App\Services;

use App\Models\Bookings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ContractAmendmentService
{
    /**
     * Recall a booking contract that is out for signature so it can be
     * edited and resent. Voids the PandaDoc document first (external call,
     * not rollback-able), then resets contract + booking state so the
     * contract editor unlocks. Resending later creates a fresh document.
     */
    public function amend(Bookings $booking): void
    {
        $contract = $booking->contract;

        if ($booking->contract_option !== 'default')
        {
            throw new InvalidArgumentException('Only Bandmate-generated contracts can be amended.');
        }

        if (!$contract || $contract->status !== 'sent')
        {
            throw new InvalidArgumentException('Only a contract that is out for signature can be amended.');
        }

        if ($booking->status !== 'pending')
        {
            throw new InvalidArgumentException('Only a pending booking can have its contract amended.');
        }

        $contract->voidPandaDocDocument();

        DB::transaction(function () use ($booking, $contract)
        {
            $contract->update(['status' => 'pending', 'envelope_id' => null]);
            $booking->update(['status' => 'draft']);
        });
    }
}
