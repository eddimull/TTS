<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Invoices;
use App\Models\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function index(Request $request)
    {
        $endpoint_secret = config('services.stripe.webhook_secret');

        $payload = $request->getContent();
        $event = null;

        try
        {
            $event = \Stripe\Event::constructFrom(
                json_decode($payload, true)
            );
        }
        catch (\UnexpectedValueException $e)
        {
            // Invalid payload
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
                Log::error('⚠️  Webhook error while verifying signature.');
                http_response_code(400);
                throw $e;
            }
        }

        // Handle the event
        switch ($event->type)
        {
            case 'invoice.paid':
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
                });
                break;
            default:
                // Unexpected event type
                Log::info('Unexpected event type: ' . $event->type);
                break;
        }

        return response()->json(['status' => 'success']);
    }
}
