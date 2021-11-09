<?php

namespace App\Http\Controllers;

use App\Models\ProposalPayments;
use App\Models\Proposals;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia; 

class FinalizedProposalController extends Controller
{
    public function paymentIndex(Proposals $proposal)
    {

        $proposal->amountLeft = $proposal->amountLeft;
        $proposal->amountPaid = $proposal->amountPaid;    

        foreach($proposal->payments as $payment)
        {
            $payment->formattedPaymentDate = $payment->formattedPaymentDate;
        }
        
        return Inertia::render('Proposals/ProposalPayments',compact('proposal'));
    } 

    public function submitPayment(Proposals $proposal, Request $request)
    {
        ProposalPayments::create([
            'proposal_id'=>$proposal->id,
            'name'=>$request->name,
            'amount'=>$request->amount,
            'paymentDate'=>Carbon::parse($request->paymentDate)
        ]);
        if($proposal->amountLeft == '0.00')
        {
            $proposal->paid = true;
            $proposal->save();
        }
        return back()->with('successMessage','Payment received');
    }

    public function deletePayment(Proposals $proposal,ProposalPayments $payment)
    {
        $payment->delete();

        if($proposal->amountLeft !== '0.00')
        {
            $proposal->paid = false;
            $proposal->save();
        }
        return back()->with('successMessage','Payment Removed');
    }
}
