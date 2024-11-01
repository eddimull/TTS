<?php

namespace App\Services;

use App\Models\Invoices;
use App\Models\ProposalContacts;
use App\Models\stripe_customers;
use App\Models\stripe_products;
use App\Models\stripe_invoice_prices;


class InvoiceServices{

    private function createStripeCustomers($proposal,$stripe,$contact_id)
    {
       
        $contact = ProposalContacts::find($contact_id);

        if(!stripe_customers::where('proposal_id',$proposal->id)->where('proposal_contact_id',$contact_id)->exists())
        {

            $createdCustomer = $stripe->customers->create([
                'description' => 'Customer for ' . $proposal->name,
                'email'=>$contact->email,
                'name'=>$contact->name
            ]);
            
            stripe_customers::create([
                'stripe_account_id'=>$createdCustomer->id,
                'proposal_id'=>$proposal->id,
                'status'=>'connected',
                'proposal_contact_id'=>$contact->id
            ]);
            
            $proposal->refresh();
        }
            
        

        
    }

    public function createInvoice($proposal, $request)
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_KEY'));
          
        $this->createStripeCustomers($proposal,$stripe,$request->contact_id);
        
        $staticApplicationPercent = 0.029;
        $staticApplicationFee = 500;
        $staticStripeCharge = 30;
        $staticStripePercent = 1.029;
        $amount = $request->amount * 100;

        if($request->buyer_pays_convenience)
        {
            $amount = ($amount * $staticStripePercent) + $staticStripeCharge + $staticApplicationFee;
        }
        $application_fee = round((($amount * $staticApplicationPercent) + $staticStripeCharge) + $staticApplicationFee,0);

        $amount = round($amount,0);


        
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
        $customer = stripe_customers::where('proposal_id',$proposal->id)->where('proposal_contact_id',$request->contact_id)->first();
        
        $invoice = \Stripe\Invoice::create([
            'on_behalf_of' => $proposal->band->stripe_accounts->stripe_account_id,
            'application_fee_amount' => $application_fee,
            'collection_method'=>'send_invoice',
            'days_until_due' => 30,
            'transfer_data' => [
                'destination' => $proposal->band->stripe_accounts->stripe_account_id,
            ],
            'customer' => $customer->stripe_account_id
        ]);

        $invoice_item = \Stripe\InvoiceItem::create([
            'customer' =>  $customer->stripe_account_id,
            'price' => $price->id,
            'invoice' => $invoice->id
        ]);

        Invoices::create([
            'proposal_id'=>$proposal->id,
            'amount'=>$amount/100,
            'status'=>'open',
            'stripe_id'=>$invoice->id,
            'convenience_fee'=>$request->buyer_pays_convenience
        ]);
        
        $stripe->invoices->sendInvoice($invoice->id,[]);
    } 
}