<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Events\PaymentWasReceived;
use App\Models\Invoices;
use App\Models\Payments;
use App\Services\ContactPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function index(Request $request)
    {
        Log::info('Stripe webhook received', [
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        $endpoint_secret = config('services.stripe.webhook_secret');

        $payload = $request->getContent();
        $event = null;

        try
        {
            $event = \Stripe\Event::constructFrom(
                json_decode($payload, true)
            );
            Log::info('Stripe webhook event parsed', ['type' => $event->type, 'id' => $event->id]);
        }
        catch (\UnexpectedValueException $e)
        {
            // Invalid payload
            Log::error('Stripe webhook parsing error', ['error' => $e->getMessage()]);
            echo '⚠️  Webhook error while parsing basic request.';
            http_response_code(400);
            exit();
        }
        if ($endpoint_secret)
        {
            // Only verify the event if there is an endpoint secret defined
            // Otherwise use the basic decoded event
            $sig_header = $request->header('Stripe-Signature');
            try
            {
                $event = \Stripe\Webhook::constructEvent(
                    $payload,
                    $sig_header,
                    $endpoint_secret
                );
            }
            catch (\Stripe\Exception\SignatureVerificationException $e)
            {
                Log::error('⚠️  Webhook signature verification failed', [
                    'error' => $e->getMessage(),
                    'secret_configured' => !empty($endpoint_secret),
                ]);
                http_response_code(400);
                throw $e;
            }
        }

        Log::info('Stripe webhook signature verified successfully');

        // Handle the event
        switch ($event->type)
        {
            case 'invoice.paid':
                Log::info('Processing invoice.paid event', ['invoice_id' => $event->data->object->id]);
                $stripeInvoice = $event->data->object; // contains a \Stripe\Invoice
                $ttsInvoice = Invoices::where('stripe_id', $stripeInvoice->id)->firstOrFail();
                $ttsInvoice->status = $stripeInvoice->status;
                $ttsInvoice->save();

                // update any payment linked to this invoice
                Payments::where('invoices_id', $ttsInvoice->id)->get()->each(function ($payment)
                {
                    $payment->status = 'paid';
                    $payment->date = Carbon::now();
                    $payment->save();

                    // Fire payment received event to trigger notifications
                    event(new PaymentWasReceived($payment));
                });
                Log::info('Invoice marked as paid', ['invoice_id' => $ttsInvoice->id]);
                break;
            
            case 'checkout.session.completed':
                Log::info('Processing checkout.session.completed event', ['session_id' => $event->data->object->id]);
                // Handle contact portal payments via Checkout Session
                $session = $event->data->object;
                
                // Check if this is a contact payment (has booking_id in metadata)
                if (isset($session->metadata->booking_id)) {
                    $paymentService = app(ContactPaymentService::class);
                    $paymentService->processSuccessfulPayment((array) $session);
                    Log::info('Contact payment processed via checkout session', [
                        'session_id' => $session->id,
                        'booking_id' => $session->metadata->booking_id,
                    ]);
                } else {
                    Log::info('Checkout session completed but no booking_id in metadata', [
                        'session_id' => $session->id,
                        'metadata' => (array) $session->metadata,
                    ]);
                }
                break;
            
            case 'payment_intent.succeeded':
                Log::info('Processing payment_intent.succeeded event', ['payment_intent_id' => $event->data->object->id]);
                $paymentIntent = $event->data->object;
                
                // Check if this is a contact payment (has booking_id in metadata)
                if (isset($paymentIntent->metadata->booking_id)) {
                    $paymentService = app(ContactPaymentService::class);
                    
                    // Convert payment intent to session-like format for processing
                    $sessionData = [
                        'id' => $paymentIntent->id,
                        'payment_intent' => $paymentIntent->id,
                        'metadata' => [
                            'booking_id' => $paymentIntent->metadata->booking_id,
                            'contact_id' => $paymentIntent->metadata->contact_id,
                            'payment_amount' => $paymentIntent->metadata->payment_amount,
                        ],
                    ];
                    
                    $paymentService->processSuccessfulPayment($sessionData);
                    Log::info('Contact payment processed via payment_intent.succeeded', [
                        'payment_intent_id' => $paymentIntent->id,
                        'booking_id' => $paymentIntent->metadata->booking_id,
                    ]);
                } else {
                    Log::info('payment_intent.succeeded but no booking_id in metadata', [
                        'payment_intent_id' => $paymentIntent->id,
                        'metadata' => (array) $paymentIntent->metadata,
                    ]);
                }
                break;
            
            default:
                // Unexpected event type
                Log::info('Unexpected event type: ' . $event->type);
                break;
        }

        Log::info('Stripe webhook processed successfully', ['event_type' => $event->type]);
        return response()->json(['status' => 'success']);
    }
}
