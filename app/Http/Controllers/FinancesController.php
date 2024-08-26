<?php

namespace App\Http\Controllers;

use App\Services\FinanceServices;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class FinancesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $financialData = $this->getFinancialData($bands, $financeServices);

        return Inertia::render('Finances/index', $financialData);
    }

    private function getFinancialData($bands, FinanceServices $financeServices): array
    {
        return [
            'completedProposals' => $financeServices->getBandFinances($bands),
            'payments' => $financeServices->getBandPayments($bands)
        ];
    }
}
