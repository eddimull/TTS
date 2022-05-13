<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use App\Services\FinanceServices;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StripeWebhookController extends Controller
{
    public function index()
    {
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        $payload = @file_get_contents('php://input');
        $event = null;

        try {
            $event = \Stripe\Event::constructFrom(
              json_decode($payload, true)
            );
          } catch(\UnexpectedValueException $e) {
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
            } catch(\Stripe\Exception\SignatureVerificationException $e) {
              // Invalid signature
              echo '⚠️  Webhook error while validating signature.';
              http_response_code(400);
              exit();
            }
          }
          
          // Handle the event
          switch ($event->type) {
            case 'invoice.paid':
              $stripeInvoice = $event->data->object; // contains a \Stripe\PaymentIntent
              $ttsInvoice = Invoices::where('stripe_id',$stripeInvoice->id)->firstOrFail();
              $ttsInvoice->status = $stripeInvoice->status;
            $ttsInvoice->save();
            $amount = $stripeInvoice->amount_paid;

            $staticApplicationFee = 500;
            $staticStripeFee = 30;
            $stripePercent = 1.029;

            if($ttsInvoice->convenience_fee)
            {
                $amount = (($amount- $staticStripeFee) - $staticApplicationFee)/$stripePercent;
            }


            (new FinanceServices())->makePayment($ttsInvoice->proposal,'Invoice Payment', $amount,Carbon::now());

              
              break;
            default:
              // Unexpected event type
              error_log('Received unknown event type');
          }
    }
}
