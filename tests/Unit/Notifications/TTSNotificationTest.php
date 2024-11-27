<?php

namespace Tests\Unit\Notifications;

use App\Notifications\TTSNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\User;

class TTSNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $notificationData;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationData = [
            'text' => 'This is a test notification',
            'route' => 'test.route',
            'routeParams' => '123',
            'url' => '/test/123',
            'emailHeader' => 'Test Email Header',
            'actionText' => 'Click Here'
        ];

        $this->user = User::factory()->create(['emailNotifications' => true]);
    }

    public function testViaMethodReturnsCorrectChannels()
    {
        $notification = new TTSNotification($this->notificationData);

        $channels = $notification->via($this->user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function testViaMethodExcludesMailForUserWithoutEmailNotifications()
    {
        $this->user->emailNotifications = false;
        $this->user->save();

        $notification = new TTSNotification($this->notificationData);

        $channels = $notification->via($this->user);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function testToMailMethodReturnsCorrectMailMessage()
    {
        $notification = new TTSNotification($this->notificationData);

        $mailMessage = $notification->toMail($this->user);

        $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $mailMessage);
        $this->assertEquals($this->notificationData['emailHeader'], $mailMessage->introLines[0]);

        $this->assertEquals($this->notificationData['actionText'], $mailMessage->actionText);
        $this->assertEquals(config('app.url') . $this->notificationData['url'], $mailMessage->actionUrl);
    }

    public function testToMailMethodUsesDefaultValuesWhenNotProvided()
    {
        $minimalData = ['text' => 'Minimal notification'];
        $notification = new TTSNotification($minimalData);

        $mailMessage = $notification->toMail($this->user);

        $this->assertEquals('Check it out', $mailMessage->actionText);
        $this->assertEquals(config('app.url'), $mailMessage->actionUrl);
    }

    public function testToArrayMethodReturnsCorrectData()
    {
        $notification = new TTSNotification($this->notificationData);

        $arrayRepresentation = $notification->toArray();
        foreach ($this->notificationData as $key => $value)
        {
            $this->assertArrayHasKey($key, $arrayRepresentation);
            $this->assertEquals($value, $arrayRepresentation[$key]);
        }
    }

    public function testNotificationIsSentToUser()
    {
        Notification::fake();

        $this->user->notify(new TTSNotification($this->notificationData));

        Notification::assertSentTo(
            $this->user,
            TTSNotification::class,
            function ($notification, $channels)
            {
                $mailMessage = $notification->toMail($this->user);
                return $mailMessage->actionText === $this->notificationData['actionText']
                    && $mailMessage->actionUrl === config('app.url') . $this->notificationData['url'];
            }
        );
    }

    public function testNotificationHandlesMinimalData()
    {
        $minimalData = ['text' => 'Minimal notification'];
        $notification = new TTSNotification($minimalData);

        $mailMessage = $notification->toMail($this->user);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('Check it out', $mailMessage->actionText);
        $this->assertEquals(config('app.url'), $mailMessage->actionUrl);
        $this->assertArrayHasKey('text', $arrayData);
        $this->assertEquals($minimalData['text'], $arrayData['text']);
    }
}
