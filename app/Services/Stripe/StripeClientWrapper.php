<?php

namespace App\Services\Stripe;

use App\Contracts\StripeClientInterface;
use Stripe\Account;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\StripeObject;

/**
 * Production implementation of StripeClientInterface
 * Wraps the actual Stripe SDK for dependency injection and testing
 */ 
class StripeClientWrapper implements StripeClientInterface
{
    private StripeClient $client;
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.stripe.key') ?? 'sk_test_fake_key_for_testing';
        $this->client = new StripeClient($this->apiKey);
        Stripe::setApiKey($this->apiKey);
    }

    /**
     * Create a Stripe customer
     */
    public function createCustomer(array $params, array $options = []): StripeObject
    {
        return Customer::create($params, $options);
    }

    /**
     * Retrieve a Stripe customer
     */
    public function retrieveCustomer(string $customerId, array $options = []): StripeObject
    {
        return Customer::retrieve($customerId, $options);
    }

    /**
     * Create a Stripe product
     */
    public function createProduct(array $params): StripeObject
    {
        return $this->client->products->create($params);
    }

    /**
     * Search for Stripe products
     */
    public function searchProducts(array $params): StripeObject
    {
        return $this->client->products->search($params);
    }

    /**
     * Create a Stripe Checkout Session
     */
    public function createCheckoutSession(array $params): StripeObject
    {
        return Session::create($params);
    }

    /**
     * Retrieve a Stripe account
     */
    public function retrieveAccount(string $accountId): StripeObject
    {
        return Account::retrieve($accountId);
    }

    /**
     * Create a Stripe invoice
     */
    public function createInvoice(array $params): StripeObject
    {
        return Invoice::create($params);
    }

    /**
     * Create a Stripe invoice item
     */
    public function createInvoiceItem(array $params): StripeObject
    {
        return InvoiceItem::create($params);
    }

    /**
     * Send a Stripe invoice
     */
    public function sendInvoice(string $invoiceId, array $params): StripeObject
    {
        return $this->client->invoices->sendInvoice($invoiceId, $params);
    }
}
