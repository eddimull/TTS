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

        return Inertia::render('Finances/Index', $financialData);
    }

    public function paidUnpaid()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $paidUnpaid = $financeServices->getPaidUnpaid($bands);

        return Inertia::render('Finances/PaidUnpaid', ['paidUnpaid' => $paidUnpaid]);
    }

    public function revenue()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $financialData = $financeServices->getBandRevenueByYear($bands);

        return Inertia::render('Finances/Revenue', ['revenue' => $financialData]);
    }

    public function unpaidServices()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $unpaid = $financeServices->getUnpaid($bands);

        return Inertia::render('Finances/Unpaid', ['unpaid' => $unpaid]);
    }

    public function paidContracts()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $financialData = $this->getFinancialData($bands, $financeServices);

        return Inertia::render('Finances/PaidContracts', $financialData);
    }

    public function payments()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $payments = $financeServices->getBandPayments($bands);

        return Inertia::render('Finances/Payments', ['payments' => $payments]);
    }

    private function getFinancialData($bands, FinanceServices $financeServices): array
    {
        return [
            'completedBookings' => $financeServices->getBandFinances($bands),
            'payments' => $financeServices->getBandPayments($bands)
        ];
    }
}
