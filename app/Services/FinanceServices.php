<?php

namespace App\Services;

class FinanceServices
{
    function getBandFinances($bands)
    {
        
        foreach($bands as $band)
        {
            $band->completedProposals;
        }
        
        return $bands;
    }
}