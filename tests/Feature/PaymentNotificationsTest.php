<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Invoices;
use App\Models\Payments;
use App\Enums\PaymentType;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Models\StripeAccounts;
use Illuminate\Support\Facades\Event;
use App\Notifications\PaymentReceived;
use App\Notifications\BandPaymentReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_manual_payment_triggers_notification_to_band_owner()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'price' => 1000
        ]);

        // Add a contact to the booking
        $contact = Contacts::factory()->create();
        $booking->contacts()->attach($contact->id, [
            'role' => 'client',
            'is_primary' => true,
        ]);

        // Create manual payment
        $response = $this->actingAs($owner)->post(
            "/bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => 'Manual Payment',
                'date' => now(),
                'amount' => 500,
                'status' => 'paid',
                'band_id' => $band->id,
                'payment_type' => PaymentType::Cash->value,
            ]
        );

        $response->assertSessionHas('successMessage');

        // Assert band owner received notification
        Notification::assertSentTo($owner, BandPaymentReceived::class);

        // Assert contact received notification
        Notification::assertSentTo($contact, PaymentReceived::class);
    }

    public function test_manual_payment_triggers_notification_to_band_members()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $member1 = User::factory()->create(['emailNotifications' => true]);
        $member2 = User::factory()->create(['emailNotifications' => true]);

        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);
        BandMembers::create(['band_id' => $band->id, 'user_id' => $member1->id]);
        BandMembers::create(['band_id' => $band->id, 'user_id' => $member2->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'price' => 1000
        ]);

        // Create manual payment
        $response = $this->actingAs($owner)->post(
            "/bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => 'Manual Payment',
                'date' => now(),
                'amount' => 500,
                'status' => 'paid',
                'payment_type' => PaymentType::Cash->value,
                'band_id' => $band->id,
            ]
        );

        $response->assertSessionHas('successMessage');

        // Assert all band users received notification
        Notification::assertSentTo([$owner, $member1, $member2], BandPaymentReceived::class);
    }

    public function test_invoice_payment_status_change_triggers_notification()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'price' => 1000
        ]);

        // Add contact
        $contact = Contacts::factory()->create();
        $booking->contacts()->attach($contact->id, [
            'role' => 'client',
            'is_primary' => true,
        ]);

        // Create invoice and payment with pending status
        $invoice = Invoices::create([
            'band_id' => $band->id,
            'booking_id' => $booking->id,
            'stripe_id' => 'inv_test_123',
            'status' => 'draft',
            'amount' => 500,
        ]);

        $payment = $booking->payments()->create([
            'name' => 'Invoice Payment',
            'amount' => 500,
            'status' => 'pending',
            'date' => now(),
            'band_id' => $band->id,
            'invoices_id' => $invoice->id,
        ]);

        // Simulate payment being marked as paid (like webhook would do)
        $payment->status = 'paid';
        $payment->save();

        // Fire the event manually (as the webhook handler does)
        event(new \App\Events\PaymentWasReceived($payment));

        // Assert band owner received notification
        Notification::assertSentTo($owner, BandPaymentReceived::class);

        // Assert contact received notification
        Notification::assertSentTo($contact, PaymentReceived::class);
    }

    public function test_notification_respects_email_notification_preference()
    {
        // Create user with notifications disabled
        $ownerNoNotif = User::factory()->create([
            'name' => 'No Notif Owner',
            'emailNotifications' => false
        ]);

        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $ownerNoNotif->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'price' => 1000
        ]);

        // Test that the notification's via() method includes database but not mail for users with notifications disabled
        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $notification = new BandPaymentReceived($payment);

        // User with notifications disabled should get database but not mail channel
        $channels = $notification->via($ownerNoNotif);
        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);

        // User with notifications enabled should get both channels
        $ownerWithNotif = User::factory()->create(['emailNotifications' => true]);
        $channels = $notification->via($ownerWithNotif);
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_multiple_payments_trigger_multiple_notifications()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'price' => 1000
        ]);

        // Create first payment
        $this->actingAs($owner)->post(
            "/bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => 'Payment 1',
                'date' => now(),
                'amount' => 300,
                'status' => 'paid',
                'payment_type' => PaymentType::Cash->value,
                'band_id' => $band->id,
            ]
        );

        // Create second payment
        $this->actingAs($owner)->post(
            "/bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => 'Payment 2',
                'date' => now(),
                'amount' => 400,
                'status' => 'paid',
                'payment_type' => PaymentType::Venmo->value,
                'band_id' => $band->id,
            ]
        );

        // Create third payment
        $this->actingAs($owner)->post(
            "/bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => 'Payment 3',
                'date' => now(),
                'amount' => 300,
                'status' => 'paid',
                'payment_type' => PaymentType::Cash->value,
                'band_id' => $band->id,
            ]
        );

        // Assert owner received 3 notifications (one for each payment)
        Notification::assertSentToTimes($owner, BandPaymentReceived::class, 3);
    }

    public function test_notification_contains_correct_payment_information()
    {
        $owner = User::factory()->create([
            'name' => 'John Doe',
            'emailNotifications' => true
        ]);
        $band = Bands::factory()->create(['name' => 'The Test Band']);
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'name' => 'Wedding Reception',
            'price' => 2000
        ]);

        // Create payment
        $this->actingAs($owner)->post(
            "/bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => 'Deposit Payment',
                'date' => now(),
                'amount' => 500,
                'status' => 'paid',
                'payment_type' => PaymentType::Check->value,
                'band_id' => $band->id,
            ]
        );

        // Assert notification was sent with correct callback
        Notification::assertSentTo($owner, BandPaymentReceived::class, function ($notification, $channels, $notifiable) use ($booking) {
            $mailMessage = $notification->toMail($notifiable);

            // Check subject
            if ($mailMessage->subject !== 'Payment Received - Wedding Reception') {
                return false;
            }

            // Check greeting
            if (!str_contains($mailMessage->greeting, 'John Doe')) {
                return false;
            }

            // Check content contains band name
            $content = implode(' ', $mailMessage->introLines);
            if (!str_contains($content, 'The Test Band')) {
                return false;
            }

            return true;
        });
    }

    /**
     * A payment logged via the finances page submits a dollar amount (e.g. 4250).
     * The Price cast multiplies by 100 for storage, then divides by 100 on read.
     * The email view must NOT divide by 100 again.
     */
    public function test_finances_payment_email_shows_correct_dollar_amount()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'name' => 'Corporate Gig',
            'price' => 4250, // $4,250.00
        ]);

        $contact = Contacts::factory()->create(['email' => 'client@example.com']);
        $booking->contacts()->attach($contact->id, ['role' => 'client', 'is_primary' => true]);

        // Finances page submits dollar value — Price cast stores as cents
        $this->actingAs($owner)->post(
            "/bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => 'Deposit',
                'date' => now(),
                'amount' => 4250,
                'status' => 'paid',
                'payment_type' => PaymentType::Cash->value,
                'band_id' => $band->id,
            ]
        );

        $payment = $booking->payments()->latest()->first();

        // Price cast must return dollars, not cents
        $this->assertEquals('4250.00', $payment->amount,
            'Price cast should return $4,250.00 not 425000 cents'
        );

        $rendered = view('email.payment', [
            'performance' => $payment->payable->name,
            'amount'      => $payment->amount,
            'balance'     => $payment->payable->amountLeft,
        ])->render();

        $this->assertStringContainsString('4,250.00', $rendered,
            'Email should show $4,250.00 — not $42.50 from double-dividing by 100'
        );
        $this->assertStringNotContainsString('42.50', $rendered,
            'Email must not show $42.50'
        );
    }

    /**
     * A portal payment comes in from Stripe as cents (e.g. 425000).
     * ContactPaymentService divides by 100 before create(), then the Price cast
     * multiplies by 100 for storage and divides by 100 on read.
     * The email view must NOT divide by 100 again.
     */
    public function test_portal_payment_email_shows_correct_dollar_amount()
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->forBand($band)->create([
            'name' => 'Corporate Gig',
            'price' => 4250, // $4,250.00
        ]);

        $contact = Contacts::factory()->create(['email' => 'client@example.com']);
        $booking->contacts()->attach($contact->id, ['role' => 'client', 'is_primary' => true]);

        // ContactPaymentService::processSuccessfulPayment() divides Stripe cents by 100
        // before passing to create(), mirroring the finances page path
        $stripeAmountInCents = 425000;
        $payment = $booking->payments()->create([
            'name' => $booking->name . ' - Portal Payment',
            'amount' => $stripeAmountInCents / 100, // $4,250.00 — Price cast stores as cents
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
            'payer_type' => 'App\\Models\\Contacts',
            'payer_id' => $contact->id,
            'payment_type' => 'portal',
        ]);

        $payment = $payment->fresh();

        // Price cast must return dollars, not cents
        $this->assertEquals('4250.00', $payment->amount,
            'Price cast should return $4,250.00 not 425000 cents'
        );

        $rendered = view('email.payment', [
            'performance' => $payment->payable->name,
            'amount'      => $payment->amount,
            'balance'     => $payment->payable->amountLeft,
        ])->render();

        $this->assertStringContainsString('4,250.00', $rendered,
            'Email should show $4,250.00 — not $42.50 from double-dividing by 100'
        );
        $this->assertStringNotContainsString('42.50', $rendered,
            'Email must not show $42.50'
        );
    }

    public function test_remaining_balance_in_email_is_correct_after_partial_payment()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create(['price' => 4250]);

        $booking->payments()->create([
            'name' => 'Partial Payment',
            'amount' => 1000, // $1,000.00
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $booking = $booking->fresh();

        $this->assertEquals('3250.00', $booking->amountLeft,
            'amountLeft should be $3,250.00 after a $1,000 payment on a $4,250 booking'
        );

        $rendered = view('email.payment', [
            'performance' => $booking->name,
            'amount'      => '1000.00',
            'balance'     => $booking->amountLeft,
        ])->render();

        $this->assertStringContainsString('3,250.00', $rendered,
            'Remaining balance in email should show $3,250.00 not $32.50'
        );
        $this->assertStringNotContainsString('32.50', $rendered,
            'Email must not show $32.50 from double-dividing the balance by 100'
        );
    }
}
