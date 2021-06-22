<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use Illuminate\Http\Request;
use App\Models\EventTypes;
use App\Models\BandEvents;
use App\Models\Proposals;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;


class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;
                   
        $eventTypes = EventTypes::all();
        $proposals = Proposals::where('band_id','=',$bands[0]->id)->where('phase_id','=',6)->with('invoices')->get();
        // dd($proposals);
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
        $stripe = new \Stripe\StripeClient(env('STRIPE_KEY'));
          
        if($proposal->stripe_customers->count() == 0)
        {
            foreach($proposal->proposal_contacts as $contact)
            {
                $stripe->customers->create([
                    'description' => 'Customer for ' . $proposal->name,
                    'email'=>$contact->email,
                    'name'=>$contact->name
                  ]);
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function show(Invoices $invoices)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function edit(Invoices $invoices)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Invoices $invoices)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Invoices  $invoices
     * @return \Illuminate\Http\Response
     */
    public function destroy(Invoices $invoices)
    {
        //
    }
}
