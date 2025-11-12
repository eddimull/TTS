<?php

namespace App\Contracts;

use Stripe\StripeObject;

/**
 * Interface for Stripe client operations
 * Allows for mocking and testing of Stripe API calls
 */
interface StripeClientInterface
{
    /**
     * Create a Stripe customer
     */
    public function createCustomer(array $params, array $options = []): StripeObject;

    /**
     * Retrieve a Stripe customer
     */
    public function retrieveCustomer(string $customerId, array $options = []): StripeObject;

    /**
     * Create a Stripe product
     */
    public function createProduct(array $params): StripeObject;

    /**
     * Search for Stripe products
     */
    public function searchProducts(array $params): StripeObject;

    /**
     * Create a Stripe Checkout Session
     */
    public function createCheckoutSession(array $params): StripeObject;

    /**
     * Retrieve a Stripe account
     */
    public function retrieveAccount(string $accountId): StripeObject;

    /**
     * Create a Stripe invoice
     */
    public function createInvoice(array $params): StripeObject;

    /**
     * Create a Stripe invoice item
     */
    public function createInvoiceItem(array $params): StripeObject;

    /**
     * Send a Stripe invoice
     */
    public function sendInvoice(string $invoiceId, array $params): StripeObject;
}
