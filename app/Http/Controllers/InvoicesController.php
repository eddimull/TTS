<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use Illuminate\Http\Request;
use App\Models\EventTypes;
use App\Models\BandEvents;
use App\Models\Proposals;
use App\Models\stripe_customers;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\stripe_invoice_prices;
use App\Models\stripe_products;

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
                
                $createdCustomer = $stripe->customers->create([
                    'description' => 'Customer for ' . $proposal->name,
                    'email'=>$contact->email,
                    'name'=>$contact->name
                  ]);

                stripe_customers::create([
                    'stripe_account_id'=>$createdCustomer->id,
                    'proposal_id'=>$proposal->id,
                    'status'=>'connected'
                ]);

                
            }
        }

        $amount = $request->amount * 100;

        if($request->buyer_pays_convenience)
        {
            $amount = ($amount * 1.029) + 30;
        }
        \Stripe\Stripe::setApiKey(env('STRIPE_KEY'));
        $product = \Stripe\Product::create([
            'name'=>$proposal->name
        ]);

        stripe_products::create([
            'proposal_id'=>$proposal->id,
            'product_name'=>$proposal->name,
            'stripe_product_id'=>$product->id
        ]);

        $price = \Stripe\Price::create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => 'usd',
        ]);
        
        stripe_invoice_prices::create([
            'proposal_id'=>$proposal->id,
            'stripe_price_id'=>$price->id
        ]);

        $invoice_item = \Stripe\InvoiceItem::create([
            'customer' =>  $proposal->stripe_customers[0]->stripe_account_id,
            'price' => $price->id,
          ]);
        
        $invoice = \Stripe\Invoice::create([
            'on_behalf_of' => $proposal->band->stripe_accounts->stripe_account_id,
            'application_fee_amount' => 10,
            'transfer_data' => [
                'destination' => $proposal->band->stripe_accounts->stripe_account_id,
            ],
            'customer' => $proposal->stripe_customers[0]->stripe_account_id
        ]);

        return back()->with('successMessage','Invoice sent in for ' . $proposal->name);
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
