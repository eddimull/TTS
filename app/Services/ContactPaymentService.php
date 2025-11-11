<?php

namespace App\Services;

use App\Contracts\StripeClientInterface;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\StripeAccounts;
use App\Models\StripeCustomers;
use App\Models\StripeProducts;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

class ContactPaymentService
{
    private StripeClientInterface $stripeClient;

    public function __construct(StripeClientInterface $stripeClient)
    {
        $this->stripeClient = $stripeClient;
    }
    /**
     * Get or create Stripe customer for contact
     */
    private function getOrCreateStripeCustomer(Contacts $contact, string $accountId): StripeCustomers
    {
        // Try to find existing customer for this contact and account
        $customer = StripeCustomers::where('contact_id', $contact->id)
            ->where('stripe_account_id', $accountId)
            ->first();

        if (!$customer) {
            // Create new Stripe customer on the Connect account
            try {
                $stripeCustomer = $this->stripeClient->createCustomer([
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'metadata' => [
                        'contact_id' => $contact->id,
                        'band_id' => $contact->band_id,
                    ],
                ], [
                    'stripe_account' => $accountId,
                ]);

                // Store customer in database
                $customer = StripeCustomers::create([
                    'contact_id' => $contact->id,
                    'stripe_customer_id' => $stripeCustomer->id,
                    'stripe_account_id' => $accountId,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create Stripe customer on Connect account', [
                    'contact_id' => $contact->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        } else {
            // Verify the customer still exists on Stripe
            try {
                $this->stripeClient->retrieveCustomer($customer->stripe_customer_id, [
                    'stripe_account' => $accountId,
                ]);
            } catch (InvalidRequestException $e) {
                // Customer doesn't exist on Stripe anymore, recreate it
                Log::warning('Stripe customer not found, recreating', [
                    'contact_id' => $contact->id,
                    'customer_id' => $customer->stripe_customer_id,
                    'account_id' => $accountId,
                ]);

                $stripeCustomer = $this->stripeClient->createCustomer([
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'metadata' => [
                        'contact_id' => $contact->id,
                        'band_id' => $contact->band_id,
                    ],
                ], [
                    'stripe_account' => $accountId,
                ]);

                // Update the database record with new customer ID
                $customer->update([
                    'stripe_customer_id' => $stripeCustomer->id,
                ]);
            }
        }

        return $customer;
    }

    /**
     * Get or create Stripe product for the booking
     */
    private function getOrCreateStripeProduct(string $name, int $bandId): string
    {
        // Check if product already exists
        $existingProduct = StripeProducts::where('band_id', $bandId)
            ->where('product_name', $name)
            ->first();

        if ($existingProduct) {
            return $existingProduct->stripe_product_id;
        }

        // Create new product
        $product = $this->stripeClient->createProduct([
            'name' => $name,
        ]);

        StripeProducts::create([
            'band_id' => $bandId,
            'stripe_product_id' => $product->id,
            'product_name' => $name,
        ]);

        return $product->id;
    }

    /**
     * Calculate payment amounts with optional convenience fee
     */
    private function calculateAmounts(float $amount, bool $convenienceFee): array
    {
        $staticApplicationPercent = 0.04;
        $staticApplicationFee = 500; // $5.00 in cents
        $staticStripeCharge = 30; // $0.30 in cents
        $staticStripePercent = 1.04; // 4% stripe fee

        $amountInCents = round($amount * 100);
        $paymentAmount = $amountInCents; // Amount band receives

        if ($convenienceFee) {
            // Customer pays the fees
            $amountInCents = round(($amountInCents * $staticStripePercent) + $staticStripeCharge + $staticApplicationFee);
        }

        $applicationFee = round(($amountInCents * $staticApplicationPercent) + $staticStripeCharge + $staticApplicationFee);

        return [
            'total_amount' => $amountInCents,
            'payment_amount' => $paymentAmount,
            'application_fee' => $applicationFee,
        ];
    }

    /**
     * Create Stripe Checkout Session for contact payment
     * 
     * @param Bookings $booking The booking to pay for
     * @param Contacts $contact The contact making the payment
     * @param float $amount The amount to pay
     * @param bool $convenienceFee Whether to add convenience fee (always true for contact payments)
     * @return string The checkout session URL
     * @throws ApiErrorException
     */
    public function createCheckoutSession(
        Bookings $booking,
        Contacts $contact,
        float $amount,
        bool $convenienceFee = true
    ): string {
        // Get band's Stripe account
        $stripeAccount = StripeAccounts::where('band_id', $booking->band_id)
            ->where('status', 'active')
            ->firstOrFail();

        // Check if this is a test/development account OR if account lacks transfer capabilities
        $isTestAccount = str_starts_with($stripeAccount->stripe_account_id, 'acct_test_');

        // Check if the Connect account has the required capabilities
        $hasTransferCapability = false;
        if (!$isTestAccount) {
            try {
                $account = $this->stripeClient->retrieveAccount($stripeAccount->stripe_account_id);
                $hasTransferCapability = isset($account->capabilities->transfers) &&
                                        $account->capabilities->transfers === 'active';

                if (!$hasTransferCapability) {
                    Log::warning('Connect account lacks active transfer capability', [
                        'account_id' => $stripeAccount->stripe_account_id,
                        'capabilities' => $account->capabilities,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to retrieve Connect account', [
                    'account_id' => $stripeAccount->stripe_account_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Always create customer on platform account (for destination charges)
        $customer = $this->getOrCreateStripeCustomerDirect($contact);

        // Get or create product on platform account
        $productId = $this->getOrCreateStripeProduct(
            $booking->name . ' - Payment',
            $booking->band_id
        );

        // Calculate amounts
        $amounts = $this->calculateAmounts($amount, $convenienceFee);

        // Build checkout session parameters
        $sessionParams = [
            'customer' => $customer->stripe_customer_id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $amounts['total_amount'],
                    'product' => $productId,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('portal.payment.success', ['session_id' => '{CHECKOUT_SESSION_ID}']),
            'cancel_url' => route('portal.payment.cancelled'),
            'metadata' => [
                'booking_id' => $booking->id,
                'contact_id' => $contact->id,
                'band_id' => $booking->band_id,
                'payment_amount' => $amounts['payment_amount'],
                'convenience_fee' => $convenienceFee ? 'true' : 'false',
                'stripe_account_id' => $stripeAccount->stripe_account_id,
            ],
        ];

        // Only add payment_intent_data with transfers for fully onboarded Connect accounts
        if (!$isTestAccount && $hasTransferCapability) {
            $sessionParams['payment_intent_data'] = [
                'application_fee_amount' => $amounts['application_fee'],
                'transfer_data' => [
                    'destination' => $stripeAccount->stripe_account_id,
                ],
                'metadata' => [
                    'booking_id' => $booking->id,
                    'contact_id' => $contact->id,
                    'band_id' => $booking->band_id,
                    'payment_amount' => $amounts['payment_amount'],
                    'stripe_account_id' => $stripeAccount->stripe_account_id,
                ],
            ];
        } else {
            // For accounts without transfer capability, just add metadata
            $sessionParams['payment_intent_data'] = [
                'metadata' => [
                    'booking_id' => $booking->id,
                    'contact_id' => $contact->id,
                    'band_id' => $booking->band_id,
                    'payment_amount' => $amounts['payment_amount'],
                    'stripe_account_id' => $stripeAccount->stripe_account_id,
                    'requires_onboarding' => !$hasTransferCapability ? 'true' : 'false',
                ],
            ];
        }

        // Create checkout session on platform account (destination charge model)
        $session = $this->stripeClient->createCheckoutSession($sessionParams);

        Log::info('Checkout session created for contact payment', [
            'session_id' => $session->id,
            'booking_id' => $booking->id,
            'contact_id' => $contact->id,
            'amount' => $amount,
            'test_mode' => $isTestAccount,
        ]);

        return $session->url;
    }

    /**
     * Get or create Stripe customer directly (not via Connect account)
     * Used for development/testing when Connect accounts aren't set up
     */
    private function getOrCreateStripeCustomerDirect(Contacts $contact): StripeCustomers
    {
        // Try to find existing customer for this contact with direct account
        $customer = StripeCustomers::where('contact_id', $contact->id)
            ->whereNull('stripe_account_id')
            ->first();

        if (!$customer) {
            // Create new Stripe customer directly on platform account
            $stripeCustomer = $this->stripeClient->createCustomer([
                'email' => $contact->email,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'metadata' => [
                    'contact_id' => $contact->id,
                    'band_id' => $contact->band_id,
                    'test_mode' => 'true',
                ],
            ]);

            // Store customer in database
            $customer = StripeCustomers::create([
                'contact_id' => $contact->id,
                'stripe_customer_id' => $stripeCustomer->id,
                'stripe_account_id' => null, // Direct platform customer
            ]);
        }

        return $customer;
    }

    /**
     * Process successful payment from webhook
     * 
     * @param array $sessionData The checkout session data from webhook
     */
    public function processSuccessfulPayment(array $sessionData): void
    {
        $bookingId = $sessionData['metadata']['booking_id'] ?? null;
        $contactId = $sessionData['metadata']['contact_id'] ?? null;
        $paymentAmount = $sessionData['metadata']['payment_amount'] ?? null;

        if (!$bookingId || !$contactId || !$paymentAmount) {
            Log::error('Missing metadata in checkout session', ['session' => $sessionData]);
            return;
        }

        $booking = Bookings::find($bookingId);
        $contact = Contacts::find($contactId);

        if (!$booking || !$contact) {
            Log::error('Booking or contact not found', [
                'booking_id' => $bookingId,
                'contact_id' => $contactId,
            ]);
            return;
        }

        // Check if a payment with the exact same amount was already created in the last 5 minutes
        // This prevents duplicate payments from multiple webhook events
        $recentDuplicate = $booking->payments()
            ->where('amount', $paymentAmount / 100)
            ->where('name', $booking->name . ' - Contact Payment')
            ->where('created_at', '>', now()->subMinutes(5))
            ->first();

        if ($recentDuplicate) {
            Log::info('Payment already recorded recently, skipping duplicate', [
                'booking_id' => $bookingId,
                'amount' => $paymentAmount,
                'existing_payment_id' => $recentDuplicate->id,
            ]);
            return;
        }

        // Create payment record
        $payment = $booking->payments()->create([
            'amount' => $paymentAmount / 100, // Convert from cents, Price cast will handle it
            'status' => 'paid',
            'date' => now(),
            'name' => $booking->name . ' - Contact Payment',
            'band_id' => $booking->band_id,
            'user_id' => null, // No user_id for contact payments
        ]);

        Log::info('Contact payment recorded', [
            'payment_id' => $payment->id,
            'booking_id' => $bookingId,
            'contact_id' => $contactId,
            'amount' => $paymentAmount,
        ]);
    }
}
