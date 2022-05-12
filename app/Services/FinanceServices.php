<?php

namespace App\Services;
use App\Models\ProposalPayments;
use Error;
use Illuminate\Support\Carbon;

class FinanceServices
{
    function getBandFinances($bands)
    {
        
        foreach($bands as $band)
        {
            $band->completedProposals;
            foreach($band->completedProposals as $proposal)
            {
                $proposal->amountPaid = $proposal->amountPaid;
                $proposal->amountLeft = $proposal->amountLeft;
            }
        }
        
        return $bands;
    }

    function makePayment($proposal,$paymentName,$amount,$date)
    {
        try{

            ProposalPayments::create([
                'proposal_id'=>$proposal->id,
                'name'=>$paymentName,
                'amount'=>$amount,
                'paymentDate'=>Carbon::parse($date)
            ]);
            if($proposal->amountLeft == '0.00')
            {
                $proposal->paid = true;
                $proposal->save();
            }
            return true;
        } catch(\Exception $e)
        {
            return back()->withError($e->getMessage())->withInput();
        }

    }

    function removePayment($proposal,$payment)
    {
        try{

            $payment->delete();

            if($proposal->amountLeft !== '0.00')
            {
                $proposal->paid = false;
                $proposal->save();
            }
            return true;
        } catch(\Exception $e)
        {
            return back()->withError($e->getMessage())->withInput();
        }

    }
    
}