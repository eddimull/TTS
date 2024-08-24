<?php

namespace App\Http\Controllers;

use App\Models\ProposalPayments;
use App\Models\Proposals;
use App\Services\FinanceServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class FinalizedProposalController extends Controller
{
    public function paymentIndex(Proposals $proposal)
    {
        $proposal->load('payments');  // Assuming attachPayments() is just loading the payments relationship

        return Inertia::render('Proposals/ProposalPayments', [
            'proposal' => $proposal
        ]);
    }

    public function submitPayment(Proposals $proposal, Request $request)
    {
        $request->validate([
            'name' => 'required',
            'amount' => 'required|Numeric',
            'paymentDate' => 'required|Date',
        ]);
        $payment = (new FinanceServices())->makePayment($proposal, $request->name, $request->amount, $request->paymentDate);

        $payment->sendReceipt();


        return back()->with('successMessage', 'Payment received');
    }

    public function deletePayment(Proposals $proposal, ProposalPayments $payment)
    {
        (new FinanceServices())->removePayment($proposal, $payment);

        return back()->with('successMessage', 'Payment Removed');
    }

    //This is very much like the payment PDF, but downloading the receipt from the site for authorized users
    public function getReceipt(Proposals $proposal)
    {
        $paymentPDF = $proposal->lastPayment->getPdf();

        return Response::streamDownload(
            function () use ($paymentPDF) {
                echo $paymentPDF->pdf();
            },
            Str::slug($proposal->name . ' Receipt', '_') . '.pdf',
            [
                'Content-type' => 'application/pdf'
            ]
        );
    }

    public function paymentPDF(ProposalPayments $payment)
    {
        return view('pdf.payment', ['payment' => $payment]);
    }
}
