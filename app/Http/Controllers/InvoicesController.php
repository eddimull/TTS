<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use Illuminate\Http\Request;
use App\Models\EventTypes;
use App\Models\Proposals;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\InvoiceServices;
use App\Services\FinanceServices;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bands = Auth::user()->bandOwner;

        $bandsWithProposals = (new FinanceServices())->getBandFinances($bands);
        $proposals = [];
        foreach($bandsWithProposals as $band)
        {
            foreach($band->proposals as $proposal)
            {
                $proposal->attachPayments();
                $proposal->contacts = $proposal->proposal_contacts;
                $proposal->invoices = $proposal->invoices;
                $proposals[] = $proposal;
            }
        }
        $eventTypes = EventTypes::all();

        
        return Inertia::render('Invoices/Index',[
            'proposals'=>$proposals,
            'eventTypes'=>$eventTypes
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Proposals $proposal, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'contact_id' => 'required|exists:App\Models\ProposalContacts,id',
            'buyer_pays_convenience' => 'required|boolean'
        ]);
        
        (new InvoiceServices())->createInvoice($proposal,$request);

        return back()->with('successMessage','Invoice sent in for ' . $proposal->name);
    }

}
