<?php

namespace App\Services;

use App\Contracts\StripeClientInterface;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Invoices;
use App\Models\StripeAccounts;
use App\Models\StripeCustomers;
use App\Models\StripeProducts;
use Illuminate\Support\Facades\Auth;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeObject;


class InvoiceServices
{
    private StripeClientInterface $stripeClient;

    public function __construct(StripeClientInterface $stripeClient)
    {
        $this->stripeClient = $stripeClient;
    }

    private function getStripeCustomer(int $contact_id, string $account_id): StripeCustomers
    {
        $customer = StripeCustomers::where('contact_id', $contact_id)->first();
        if (!$customer) {
            $contact = Contacts::find($contact_id);
            $stripeCustomer = $this->stripeClient->createCustomer([
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
        $connectedAccount = StripeAccounts::where('stripe_account_id', $booking->band->stripe_accounts->stripe_account_id)->first();

        $product = $this->getStripeProduct($booking->name, $booking->band_id);

        $customer = $this->getStripeCustomer($contactId, $connectedAccount->stripe_account_id);

        $staticApplicationPercent = 0.04;
        $staticApplicationFee = 500;
        $staticStripeCharge = 30;
        $staticStripePercent = 1.04;
        $amount = $amount * 100;
        $paymentAmount = $amount; // this is the amount that will be paid to the band. AKA the customer will not see this amount.

        if ($convenienceFee) {
            $amount = ($amount * $staticStripePercent) + $staticStripeCharge + $staticApplicationFee;
        }
        $application_fee = round((($amount * $staticApplicationPercent) + $staticStripeCharge) + $staticApplicationFee, 0);

        $amount = round($amount, 0);

        $invoice = $this->stripeClient->createInvoice([
            'application_fee_amount' => $application_fee,
            'collection_method' => 'send_invoice',
            'days_until_due' => 30,
            'transfer_data' => [
                'destination' => $connectedAccount->stripe_account_id,
            ],
            'customer' => $customer->stripe_customer_id,
            'on_behalf_of' => $connectedAccount->stripe_account_id,
        ]);

        $this->stripeClient->createInvoiceItem([
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

        $this->stripeClient->sendInvoice($invoice->id, []);

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

    public function getStripeProduct(string $name, int $bandID): StripeObject
    {
        $results = $this->stripeClient->searchProducts([
            'query' => "name:'" . addslashes($name) . "'",
            'limit' => 1,
        ]);
        if (count($results->data) > 0) {
            return $results->data[0];
        }

        // otherwise, create a new product and store it locally as well
        $product = $this->stripeClient->createProduct([
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
