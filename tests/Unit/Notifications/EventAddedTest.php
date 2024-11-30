<?php

namespace Tests\Unit\Notifications;

use App\Notifications\EventAdded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\User;
use App\Models\BandEvents;

class EventAddedTest extends TestCase
{
    use RefreshDatabase;

    protected $notificationData;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationData = [
            'text' => 'New event added',
            'route' => 'events.show',
            'routeParams' => '123',
            'url' => '/events/123'
        ];

        $this->user = User::factory()->create(['emailNotifications' => true]);
    }

    public function testViaMethodReturnsCorrectChannels()
    {
        $notification = new EventAdded($this->notificationData);

        $channels = $notification->via($this->user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function testViaMethodExcludesMailForUserWithoutEmailNotifications()
    {
        $this->user->emailNotifications = false;
        $this->user->save();

        $notification = new EventAdded($this->notificationData);

        $channels = $notification->via($this->user);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function testToMailMethodReturnsCorrectMailMessage()
    {
        $notification = new EventAdded($this->notificationData);

        $mailMessage = $notification->toMail($this->user);

        $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $mailMessage);
        $this->assertEquals('There was an update to an event', $mailMessage->introLines[0]);
        $this->assertEquals('Check out the event', $mailMessage->actionText);
        $this->assertEquals(config('app.url') . $this->notificationData['url'], $mailMessage->actionUrl);
    }
    public function testToDatabaseMethodReturnsCorrectArray()
    {
        $notification = new EventAdded($this->notificationData);

        $databaseNotification = $notification->toDatabase($this->user);

        $this->assertEquals($this->notificationData, $databaseNotification);
    }

    public function testNotificationIsSentToUser()
    {
        Notification::fake();

        $event = BandEvents::factory()->create();
        $this->user->notify(new EventAdded($this->notificationData));
        Notification::assertSentTo(
            $this->user,
            EventAdded::class
        );
    }
}
