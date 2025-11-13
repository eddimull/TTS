<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\User;
use App\Notifications\DepositPaymentReminder;
use App\Notifications\FinalPaymentReminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class PaymentReminderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->user->id]);
    }

    public function test_deposit_reminder_notification_contains_correct_information(): void
    {
        Notification::fake();

        $signedDate = now()->subWeeks(3);
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Summer Wedding',
            'price' => 1500.00,
            'date' => now()->addMonth(),
        ]);

        $booking->contract()->create([
            'status' => 'completed',
            'author_id' => $this->user->id,
            'updated_at' => $signedDate,
        ]);

        $booking->payments()->create([
            'name' => 'Partial Payment',
            'band_id' => $this->band->id,
            'amount' => 200, // $200 paid
            'date' => now(),
            'status' => 'paid',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        // Send notification
        $contact->notify(new DepositPaymentReminder($booking->fresh()));

        Notification::assertSentTo($contact, DepositPaymentReminder::class, function ($notification, $channels) use ($booking, $contact) {
            $mailMessage = $notification->toMail($contact);

            // Check subject
            $this->assertStringContainsString('Summer Wedding', $mailMessage->subject);

            // Check array representation
            $array = $notification->toArray($notification);
            $this->assertEquals($booking->id, $array['booking_id']);
            $this->assertEquals('550.00', $array['deposit_due']); // $750 expected - $200 paid
            $this->assertEquals('deposit_reminder', $array['type']);

            return true;
        });
    }

    public function test_final_payment_reminder_notification_contains_correct_information(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Corporate Event',
            'price' => 2000.00,
            'date' => now()->addDays(7),
            'venue_name' => 'Grand Ballroom',
        ]);

        $booking->payments()->create([
            'name' => 'Full Payment',
            'band_id' => $this->band->id,
            'amount' => 1000, // $1000 paid
            'date' => now(),
            'status' => 'paid',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        // Send notification
        $contact->notify(new FinalPaymentReminder($booking->fresh()));

        Notification::assertSentTo($contact, FinalPaymentReminder::class, function ($notification, $channels) use ($booking, $contact) {
            $mailMessage = $notification->toMail($contact);

            // Check subject contains days (can be 6-7 due to timing)
            $this->assertMatchesRegularExpression('/[67] days away/', $mailMessage->subject);
            $this->assertStringContainsString('Corporate Event', $mailMessage->subject);

            // Check array representation
            $array = $notification->toArray($notification);
            $this->assertEquals($booking->id, $array['booking_id']);
            $this->assertEquals('1000.00', $array['amount_due']);
            $this->assertGreaterThanOrEqual(6, $array['days_until_event']);
            $this->assertLessThanOrEqual(7, $array['days_until_event']);
            $this->assertEquals('final_payment_reminder', $array['type']);

            return true;
        });
    }

    public function test_all_contacts_receive_deposit_reminder(): void
    {
        Notification::fake();

        $signedDate = now()->subWeeks(3);
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addMonth(),
        ]);

        $booking->contract()->create([
            'status' => 'completed',
            'author_id' => $this->user->id,
            'updated_at' => $signedDate,
        ]);

        $contact1 = Contacts::factory()->create(['band_id' => $this->band->id]);
        $contact2 = Contacts::factory()->create(['band_id' => $this->band->id]);
        $contact3 = Contacts::factory()->create(['band_id' => $this->band->id]);

        $booking->contacts()->attach($contact1->id, ['role' => 'primary', 'is_primary' => true]);
        $booking->contacts()->attach($contact2->id, ['role' => 'secondary']);
        $booking->contacts()->attach($contact3->id, ['role' => 'other']);

        // Send to all contacts
        foreach ($booking->contacts as $contact) {
            $contact->notify(new DepositPaymentReminder($booking));
        }

        Notification::assertSentTo($contact1, DepositPaymentReminder::class);
        Notification::assertSentTo($contact2, DepositPaymentReminder::class);
        Notification::assertSentTo($contact3, DepositPaymentReminder::class);
        Notification::assertCount(3);
    }

    public function test_all_contacts_receive_final_payment_reminder(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addDays(7),
        ]);

        $booking->payments()->create([
            'name' => 'Partial Payment',
            'band_id' => $this->band->id,
            'amount' => 500, // $500 paid
            'date' => now(),
            'status' => 'paid',
        ]);

        $contact1 = Contacts::factory()->create(['band_id' => $this->band->id]);
        $contact2 = Contacts::factory()->create(['band_id' => $this->band->id]);

        $booking->contacts()->attach($contact1->id, ['role' => 'primary']);
        $booking->contacts()->attach($contact2->id, ['role' => 'billing']);

        // Send to all contacts
        foreach ($booking->contacts as $contact) {
            $contact->notify(new FinalPaymentReminder($booking));
        }

        Notification::assertSentTo($contact1, FinalPaymentReminder::class);
        Notification::assertSentTo($contact2, FinalPaymentReminder::class);
        Notification::assertCount(2);
    }

    public function test_notifications_are_queued(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addDays(7),
        ]);

        $contact = Contacts::factory()->create(['band_id' => $this->band->id]);

        $depositNotification = new DepositPaymentReminder($booking);
        $finalNotification = new FinalPaymentReminder($booking);

        // Check that notifications implement ShouldQueue
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $depositNotification);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $finalNotification);
    }

    public function test_deposit_reminder_mail_includes_portal_link(): void
    {
        $signedDate = now()->subWeeks(3);
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addMonth(),
        ]);

        $booking->contract()->create([
            'status' => 'completed',
            'author_id' => $this->user->id,
            'updated_at' => $signedDate,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Test User',
        ]);

        $notification = new DepositPaymentReminder($booking);
        $mailMessage = $notification->toMail($contact);

        // Check that the action button exists and points to portal
        $this->assertNotEmpty($mailMessage->actionText);
        $this->assertEquals('Pay Online Now', $mailMessage->actionText);
        $this->assertStringContainsString('/portal/login', $mailMessage->actionUrl);
    }

    public function test_final_payment_reminder_mail_includes_portal_link(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addDays(7),
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Test User',
        ]);

        $notification = new FinalPaymentReminder($booking);
        $mailMessage = $notification->toMail($contact);

        // Check that the action button exists and points to portal
        $this->assertNotEmpty($mailMessage->actionText);
        $this->assertEquals('Pay Online Now', $mailMessage->actionText);
        $this->assertStringContainsString('/portal/login', $mailMessage->actionUrl);
    }
}
