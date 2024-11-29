<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use App\Models\Payments;
use Carbon\Carbon;

class StripeWebhookController extends Controller
{
    public function index()
    {
        $endpoint_secret = config('services.stripe.webhook_secret');

        $payload = @file_get_contents('php://input');
        $event = null;

        try {
            $event = \Stripe\Event::constructFrom(
                json_decode($payload, true)
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            echo '⚠️  Webhook error while parsing basic request.';
            http_response_code(400);
            exit();
        }
        if ($endpoint_secret) {
            // Only verify the event if there is an endpoint secret defined
            // Otherwise use the basic decoded event
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            try {
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $endpoint_secret
                );
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                // Invalid signature
                echo '⚠️  Webhook error while validating signature.';
                http_response_code(400);
                exit();
            }
        }

        // Handle the event
        switch ($event->type) {
            case 'invoice.paid':
                $stripeInvoice = $event->data->object; // contains a \Stripe\Invoice
                $ttsInvoice = Invoices::where('stripe_id', $stripeInvoice->id)->firstOrFail();
                $ttsInvoice->status = $stripeInvoice->status;
                $ttsInvoice->save();

                // update any payment linked to this invoice
                Payments::where('invoices_id', $ttsInvoice->id)->get()->each(function ($payment){
                    $payment->status = 'paid';
                    $payment->date = Carbon::now();
                    $payment->save();
                });
                break;
            default:
                // Unexpected event type
                error_log('Received unknown event type');
        }
    }
}
