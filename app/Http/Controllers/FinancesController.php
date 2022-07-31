<?php

namespace App\Http\Controllers;

use App\Services\FinanceServices;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Auth;

class FinancesController extends Controller
{
    public function index()
    {
        $bands = Auth::user()->bandOwner;

        $completedProposals = (new FinanceServices())->getBandFinances($bands);
        $payments = (new FinanceServices())->getBandPayments($bands);
        return Inertia::render('Finances/index',compact('completedProposals','payments'));
    }
}
