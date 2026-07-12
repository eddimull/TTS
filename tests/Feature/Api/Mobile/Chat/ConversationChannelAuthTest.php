<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ConversationChannelAuthTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        // The pusher driver signs auth responses locally (no network) — give
        // it deterministic creds regardless of the surrounding .env.
        config([
            'broadcasting.default'                    => 'pusher',
            'broadcasting.connections.pusher.key'     => 'test-key',
            'broadcasting.connections.pusher.secret'  => 'test-secret',
            'broadcasting.connections.pusher.app_id'  => 'test-app',
        ]);

        // routes/channels.php registers Broadcast::channel(...) callbacks
        // against whichever driver is default at app-boot time (phpunit.xml
        // pins BROADCAST_DRIVER=null). BroadcastManager::driver() caches one
        // instance per driver name, so switching the default above resolves
        // to a *new*, channel-less "pusher" broadcaster unless we purge the
        // cache and replay the channel registrations against it.
        Broadcast::purge();
        require base_path('routes/channels.php');
    }

    private function authChannel(User $user, int $conversationId): TestResponse
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => 'private-conversation.' . $conversationId,
            'socket_id'    => '123.456',
        ]);
    }

    public function test_participant_passes_and_outsider_fails_conversation_channel_auth(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member   = $this->makeMember($band);
        $outsider = User::factory()->create();
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->authChannel($owner, $dm->id)->assertOk();
        $this->authChannel($outsider, $dm->id)->assertStatus(403);
    }

    public function test_entitled_sub_passes_topic_channel_auth(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event      = $this->makeBookingEvent($band);
        $otherEvent = $this->makeBookingEvent($band);
        $entitled   = $this->makeSubAssignedTo($band, $event);
        $unentitled = $this->makeSubAssignedTo($band, $otherEvent);
        $topic = app(ConversationService::class)->topicFor($event);

        $this->authChannel($entitled, $topic->id)->assertOk();
        $this->authChannel($unentitled, $topic->id)->assertStatus(403);
    }

    public function test_unknown_conversation_fails_auth(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $this->authChannel($owner, 999999)->assertStatus(403);
    }
}
