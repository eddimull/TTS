<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Payments;
use App\Models\BandOwners;
use App\Models\Contacts;
use App\Notifications\BandPaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

class BandPaymentReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_contains_payment_details()
    {
        // Create test data
        $user = User::factory()->create(['name' => 'Test User']);
        $band = Bands::factory()->create(['name' => 'Test Band']);
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'name' => 'Test Booking',
            'price' => 1000
        ]);

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        // Create notification
        $notification = new BandPaymentReceived($payment);

        // Get mail message
        $mailMessage = $notification->toMail($user);

        // Assert mail message is correct type
        $this->assertInstanceOf(MailMessage::class, $mailMessage);

        // Assert subject contains booking name
        $this->assertEquals('Payment Received - Test Booking', $mailMessage->subject);

        // Assert greeting contains user name
        $this->assertStringContainsString('Hello Test User', $mailMessage->greeting);

        // Assert payment details are included
        $this->assertStringContainsString('Test Band', implode(' ', $mailMessage->introLines));
        $this->assertStringContainsString('Test Booking', implode(' ', $mailMessage->introLines));
        $this->assertStringContainsString('$500.00', implode(' ', $mailMessage->introLines)); // amount paid
    }

    public function test_notification_channels_respect_email_preference()
    {
        // User with email notifications enabled
        $userWithNotifications = User::factory()->create(['emailNotifications' => true]);

        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $userWithNotifications->id]);

        $booking = Bookings::factory()->forBand($band)->create();
        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $notification = new BandPaymentReceived($payment);

        // Should send both database and mail to user with notifications enabled
        $channels = $notification->via($userWithNotifications);
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);

        // User with email notifications disabled
        $userWithoutNotifications = User::factory()->create(['emailNotifications' => false]);

        // Should only send database notification (not mail)
        $channels = $notification->via($userWithoutNotifications);
        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_notification_includes_booking_link()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'name' => 'Test Booking'
        ]);

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $notification = new BandPaymentReceived($payment);
        $mailMessage = $notification->toMail($user);

        // Assert action button is present
        $this->assertEquals('View Booking', $mailMessage->actionText);
        $this->assertStringContainsString("/bands/{$band->id}/booking/{$booking->id}", $mailMessage->actionUrl);
    }

    public function test_notification_shows_remaining_balance()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'price' => 1000
        ]);

        // Create first payment of $500
        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $notification = new BandPaymentReceived($payment);
        $mailMessage = $notification->toMail($user);

        // Should show remaining balance of $500
        $this->assertStringContainsString('$500.00', implode(' ', $mailMessage->introLines));
    }

    public function test_notification_to_array_contains_correct_data()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create(['name' => 'Test Band']);
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $booking = Bookings::factory()->forBand($band)->create([
            'name' => 'Test Booking'
        ]);

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $notification = new BandPaymentReceived($payment);
        $array = $notification->toArray($user);

        $this->assertEquals($payment->id, $array['payment_id']);
        $this->assertEquals($booking->id, $array['booking_id']);
        $this->assertEquals('Test Booking', $array['booking_name']);
        $this->assertEquals('Test Band', $array['band_name']);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('amount_formatted', $array);
        $this->assertArrayHasKey('balance', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertStringContainsString('Payment received', $array['message']);
    }
}
