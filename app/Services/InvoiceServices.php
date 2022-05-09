<?php

namespace App\Services;

use App\Models\Invoices;
use App\Models\stripe_customers;
use App\Models\stripe_products;
use App\Models\stripe_invoice_prices;


class InvoiceServices{
    public function createInvoice($proposal, $request)
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
        
        $invoice_item = \Stripe\InvoiceItem::create([
            'customer' =>  $proposal->stripe_customers[0]->stripe_account_id,
            'price' => $price->id,
        ]);
        
        $invoice = \Stripe\Invoice::create([
            'on_behalf_of' => $proposal->band->stripe_accounts->stripe_account_id,
            'application_fee_amount' => 500,
            'collection_method'=>'send_invoice',
            'days_until_due' => 30,
            'transfer_data' => [
                'destination' => $proposal->band->stripe_accounts->stripe_account_id,
            ],
            'customer' => $proposal->stripe_customers[0]->stripe_account_id
        ]);

        Invoices::create([
            'proposal_id'=>$proposal->id,
            'amount'=>$amount/100,
            'status'=>'open',
            'stripe_id'=>$invoice->id
        ]);
        
        $stripe->invoices->sendInvoice($invoice->id,[]);
    } 
}