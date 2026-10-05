<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Events\NotificationChanged;
use App\Models\Bandnotification;
use App\Notifications\TTSNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationRealtimeTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_event_shape(): void
    {
        $e = new NotificationChanged(7, 'abc', 'created');

        $this->assertSame('user.data-changed', $e->broadcastAs());
        $this->assertSame('private-App.Models.User.7', $e->broadcastOn()[0]->name);
        $this->assertSame(['model' => 'notification', 'id' => 'abc', 'action' => 'created'], $e->broadcastWith());
    }

    public function test_created_read_and_seen_broadcast_for_the_owner(): void
    {
        Event::fake([NotificationChanged::class]);
        [$owner] = $this->makeOwnerWithBand();

        $owner->notify(new TTSNotification(['text' => 'hi', 'route' => 'dashboard', 'routeParams' => null]));
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->userId === $owner->id && $e->notificationId === $row->id && $e->action === 'created');

        $this->actingAs($owner)->postJson("/api/mobile/notifications/{$row->id}/read")->assertNoContent();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->userId === $owner->id && $e->notificationId === $row->id && $e->action === 'read');

        $this->actingAs($owner)->postJson('/api/mobile/notifications/seen')->assertNoContent();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->userId === $owner->id && $e->notificationId === null && $e->action === 'seen');

        $this->actingAs($owner)->postJson('/api/mobile/notifications/read-all')->assertNoContent();
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->notificationId === null && $e->action === 'read');
    }

    public function test_web_bell_routes_broadcast_too(): void
    {
        Event::fake([NotificationChanged::class]);
        [$owner] = $this->makeOwnerWithBand();
        $owner->notify(new TTSNotification(['text' => 'hi', 'route' => 'dashboard', 'routeParams' => null]));
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();

        $this->actingAs($owner)->post("/notification/{$row->id}");
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->notificationId === $row->id && $e->action === 'read');

        $this->actingAs($owner)->post('/seentIt');
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->action === 'seen');

        $this->actingAs($owner)->post('/readAllNotifications');
        Event::assertDispatched(NotificationChanged::class, fn ($e) => $e->notificationId === null && $e->action === 'read');
    }

    public function test_notifications_to_contacts_do_not_broadcast(): void
    {
        Event::fake([NotificationChanged::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $contact = \App\Models\Contacts::factory()->create(['band_id' => $band->id]);

        $contact->notify(new TTSNotification(['text' => 'portal', 'route' => 'dashboard', 'routeParams' => null]));

        Event::assertNotDispatched(NotificationChanged::class);
    }
}
