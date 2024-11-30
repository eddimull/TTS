<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Invoices;
use App\Models\StripeAccounts;
use App\Models\StripeCustomers;
use App\Models\StripeProducts;
use Illuminate\Support\Facades\Auth;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\StripeClient;


class InvoiceServices
{

    private function getStripeCustomer(int $contact_id, string $account_id): StripeCustomers
    {
        $customer = StripeCustomers::where('contact_id', $contact_id)->first();
        if (!$customer) {
            $contact = Contacts::find($contact_id);
            $stripeCustomer = Customer::create([
                'email' => $contact->email,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'metadata' => [
                    'contact_id' => $contact_id
                ]
            ]);
            $customer = StripeCustomers::create([
                'contact_id' => $contact_id,
                'stripe_customer_id' => $stripeCustomer->id,
                'stripe_account_id' => $account_id,
            ]);
        }

        return $customer;
    }

    /**
     * @throws ApiErrorException
     */
    public function createInvoice(Bookings $booking, float $amount, int $contactId, bool $convenienceFee): void
    {
        Stripe::setApiKey(config('services.stripe.key'));
        $stripe = new StripeClient(config('services.stripe.key'));

        $stripeAccount = StripeAccounts::where('stripe_account_id', $booking->band->stripe_accounts->stripe_account_id)->first();

        $product = $this->getStripeProduct($stripe, $booking->name, $booking->band_id);

        $customer = $this->getStripeCustomer($contactId, $stripeAccount->stripe_account_id);

        $staticApplicationPercent = 0.029;
        $staticApplicationFee = 500;
        $staticStripeCharge = 30;
        $staticStripePercent = 1.029;
        $amount = $amount * 100;
        $paymentAmount = $amount; // this is the amount that will be paid to the band. AKA the customer will not see this amount. 

        if ($convenienceFee) {
            $amount = ($amount * $staticStripePercent) + $staticStripeCharge + $staticApplicationFee;
        }
        $application_fee = round((($amount * $staticApplicationPercent) + $staticStripeCharge) + $staticApplicationFee, 0);

        $amount = round($amount, 0);

        $invoice = Invoice::create([
            'application_fee_amount' => $application_fee,
            'collection_method' => 'send_invoice',
            'days_until_due' => 30,
            'transfer_data' => [
                'destination' => $stripeAccount->stripe_account_id,
            ],
            'customer' => $customer->stripe_customer_id,
        ]
        );

        InvoiceItem::create([
            'customer' => $customer->stripe_customer_id,
            'invoice' => $invoice->id,
            'price_data' => [
                'currency' => 'usd',
                'product' => $product->id,
                'unit_amount' => $amount,
            ],
        ]);

        $localInvoice = Invoices::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'status' => 'open',
            'stripe_id' => $invoice->id,
            'convenience_fee' => $convenienceFee,
        ]);

        $stripe->invoices->sendInvoice($invoice->id, []);

        // create a payment record for the booking in pending state
        // this will be updated when the payment is received
        $booking->payments()->create([
            'amount' => $paymentAmount / 100, // this gets cast to Price class which handles multiplying by 100 already
            'status' => 'pending',
            'invoices_id' => $localInvoice->id,
            'name' => $booking->name . ', invoice ' . $invoice->id,
            'band_id' => $booking->band_id,
            'user_id' => Auth::id(),
        ]);
    }

    public function getStripeProduct(StripeClient $stripe, string $name, int $bandID): Product
    {
        $results = $stripe->products->search([
            'query' => "name:'$name'",
            'limit' => 1,
        ]);
        if (count($results->data) > 0) {
            return $results->data[0];
        }

        // otherwise, create a new product and store it locally as well
        $product = Product::create([
            'name' => $name,
        ]);
        StripeProducts::create([
            'band_id' => $bandID,
            'stripe_product_id' => $product->id,
            'product_name' => $name,
        ]);

        return $product;
    }
}
