<?php

namespace Tests\Unit\Observers;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\User;
use App\Models\Bookings;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Notifications\TTSNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingObserverTest extends TestCase
{
    // use RefreshDatabase;

    protected $band;
    protected $booking;
    protected $bandOwner;
    protected $bandMember;
    protected $author;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake notifications
        Notification::fake();

        // Create test data
        $this->author = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Create band owner
        $ownerUser = User::factory()->create();
        $this->bandOwner = BandOwners::factory()->create([
            'band_id' => $this->band->id,
            'user_id' => $ownerUser->id
        ]);

        // Create band member
        $memberUser = User::factory()->create();
        $this->bandMember = BandMembers::factory()->create([
            'band_id' => $this->band->id,
            'user_id' => $memberUser->id
        ]);

        // Create booking
        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'author_id' => $this->author->id,
            'status' => 'pending'
        ]);
    }


    public function test_it_sends_notifications_when_booking_status_changes()
    {
        // Update booking status
        $this->booking->status = 'confirmed';
        $this->booking->save();

        // Assert notifications were sent to band owner and member
        Notification::assertSentTo(
            [$this->bandOwner->user, $this->bandMember->user],
            TTSNotification::class,
            function ($notification)
            {
                $data = $notification->toArray();
                return $data['text'] === "Booking '{$this->booking->name}' status changed from pending to confirmed" &&
                    $data['route'] === 'Booking Details' &&
                    $data['routeParams']['band'] === $this->band->id &&
                    $data['routeParams']['booking'] === $this->booking->id;
            }
        );
    }


    public function test_it_does_not_send_notifications_when_other_fields_change()
    {
        // Update booking name (not status)
        $this->booking->name = 'Updated Name';
        $this->booking->save();

        // Assert no notifications were sent
        Notification::assertNothingSent();
    }


    public function test_it_sends_notifications_to_multiple_band_members()
    {
        // Create additional band member
        $additionalMember = User::factory()->create();
        BandMembers::factory()->create([
            'band_id' => $this->band->id,
            'user_id' => $additionalMember->id
        ]);

        // Update booking status
        $this->booking->status = 'confirmed';
        $this->booking->save();

        // Assert notifications were sent to all members
        Notification::assertSentTo(
            [$this->bandOwner->user, $this->bandMember->user, $additionalMember],
            TTSNotification::class
        );
    }

    public function test_notification_contains_correct_url()
    {
        // Update booking status
        $this->booking->status = 'confirmed';
        $this->booking->save();

        // Assert notification contains correct URL
        Notification::assertSentTo(
            $this->bandOwner->user,
            TTSNotification::class,
            function ($notification)
            {
                $data = $notification->toArray();
                return $data['url'] === "/bands/{$this->band->id}/booking/{$this->booking->id}";
            }
        );
    }
}
