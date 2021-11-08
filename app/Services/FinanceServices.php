<?php

namespace App\Services;

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
}