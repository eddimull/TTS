<?php

namespace App\Http\Controllers;

use App\Models\ProposalPayments;
use App\Models\Proposals;
use App\Services\FinanceServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia; 

class FinalizedProposalController extends Controller
{
    public function paymentIndex(Proposals $proposal)
    {

        $proposal->attachPayments();
        
        return Inertia::render('Proposals/ProposalPayments',compact('proposal'));
    } 

    public function submitPayment(Proposals $proposal, Request $request)
    {
        $request->validate([
            'name'=>'required',
            'amount'=>'required|Numeric',
            'paymentDate'=>'required|Date',
        ]);
        $payment = (new FinanceServices())->makePayment($proposal,$request->name,$request->amount,$request->paymentDate);
        
        $payment->sendReceipt();
        

        return back()->with('successMessage','Payment received');
    }

    public function deletePayment(Proposals $proposal,ProposalPayments $payment)
    {
        (new FinanceServices())->removePayment($proposal,$payment);
        
        return back()->with('successMessage','Payment Removed');
    }

    public function paymentPDF(ProposalPayments $payment)
    {
        return view('pdf.payment',['payment'=>$payment]);
    }
}
