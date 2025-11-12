<?php

namespace Tests\Unit\Listeners;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Events\PaymentWasReceived;
use App\Listeners\SendPaymentNotification;
use App\Notifications\PaymentReceived;
use App\Notifications\BandPaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class SendPaymentNotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_listener_sends_notification_to_contacts()
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->forBand($band)->create();

        $contact = Contacts::factory()->create([
            'name' => 'Test Contact',
            'email' => 'contact@example.com',
        ]);

        $booking->contacts()->attach($contact->id, [
            'role' => 'client',
            'is_primary' => true,
        ]);

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $event = new PaymentWasReceived($payment);
        $listener = new SendPaymentNotification();
        $listener->handle($event);

        // Assert customer was notified
        Notification::assertSentTo($contact, PaymentReceived::class);
    }

    public function test_listener_sends_notification_to_band_owners()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create();

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $event = new PaymentWasReceived($payment);
        $listener = new SendPaymentNotification();
        $listener->handle($event);

        // Assert band owner was notified
        Notification::assertSentTo($owner, BandPaymentReceived::class);
    }

    public function test_listener_sends_notification_to_band_members()
    {
        $member = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $member->id]);

        $booking = Bookings::factory()->forBand($band)->create();

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $event = new PaymentWasReceived($payment);
        $listener = new SendPaymentNotification();
        $listener->handle($event);

        // Assert band member was notified
        Notification::assertSentTo($member, BandPaymentReceived::class);
    }

    public function test_listener_sends_notification_to_multiple_band_users()
    {
        $owner1 = User::factory()->create(['emailNotifications' => true]);
        $owner2 = User::factory()->create(['emailNotifications' => true]);
        $member1 = User::factory()->create(['emailNotifications' => true]);
        $member2 = User::factory()->create(['emailNotifications' => true]);

        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner1->id]);
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner2->id]);
        BandMembers::create(['band_id' => $band->id, 'user_id' => $member1->id]);
        BandMembers::create(['band_id' => $band->id, 'user_id' => $member2->id]);

        $booking = Bookings::factory()->forBand($band)->create();

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $event = new PaymentWasReceived($payment);
        $listener = new SendPaymentNotification();
        $listener->handle($event);

        // Assert all band users were notified
        Notification::assertSentTo([$owner1, $owner2, $member1, $member2], BandPaymentReceived::class);
    }

    public function test_listener_handles_duplicate_users_correctly()
    {
        // Create a user who is both owner and member (edge case)
        $user = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $booking = Bookings::factory()->forBand($band)->create();

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $event = new PaymentWasReceived($payment);
        $listener = new SendPaymentNotification();
        $listener->handle($event);

        // Assert user was notified (only once, not twice)
        Notification::assertSentTo($user, BandPaymentReceived::class);

        // Make sure the notification was only sent once
        Notification::assertCount(1);
    }

    public function test_listener_sends_both_customer_and_band_notifications()
    {
        $owner = User::factory()->create(['emailNotifications' => true]);
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $owner->id]);

        $booking = Bookings::factory()->forBand($band)->create();

        $contact = Contacts::factory()->create();
        $booking->contacts()->attach($contact->id, [
            'role' => 'client',
            'is_primary' => true,
        ]);

        $payment = $booking->payments()->create([
            'name' => 'Test Payment',
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'band_id' => $band->id,
        ]);

        $event = new PaymentWasReceived($payment);
        $listener = new SendPaymentNotification();
        $listener->handle($event);

        // Assert both customer and band owner were notified with different notification types
        Notification::assertSentTo($contact, PaymentReceived::class);
        Notification::assertSentTo($owner, BandPaymentReceived::class);
    }
}
