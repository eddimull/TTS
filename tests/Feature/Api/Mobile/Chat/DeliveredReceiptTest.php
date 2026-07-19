<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Events\ConversationStreamEvent;
use App\Models\ConversationParticipant;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveredReceiptTest extends TestCase
{
    use RefreshDatabase;
    use ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_thread_participants_include_last_delivered_at(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        ConversationParticipant::where('conversation_id', $dm->id)
            ->where('user_id', $member->id)
            ->update(['last_delivered_at' => '2026-01-02 03:04:05']);

        $response = $this->actingAs($owner)
            ->getJson("/api/mobile/conversations/{$dm->id}/messages")
            ->assertOk();

        $participants = collect($response->json('participants'));
        $other = $participants->firstWhere('user_id', $member->id);
        $this->assertNotNull($other['last_delivered_at']);
        $this->assertStringStartsWith('2026-01-02T03:04:05', $other['last_delivered_at']);
        $me = $participants->firstWhere('user_id', $owner->id);
        $this->assertNull($me['last_delivered_at']);
    }

    public function test_bulk_ack_stamps_only_conversations_with_newer_messages(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $service = app(ConversationService::class);
        $withNew = $service->dmBetween($owner, $member);
        $channel = $service->bandChannelFor($band); // no messages → untouched

        $withNew->messages()->create(['user_id' => $owner->id, 'body' => 'undelivered']);

        $this->actingAs($member)
            ->postJson('/api/mobile/conversations/delivered')
            ->assertStatus(204);

        $stamped = ConversationParticipant::where('conversation_id', $withNew->id)
            ->where('user_id', $member->id)->first();
        $this->assertNotNull($stamped->last_delivered_at);

        $untouched = ConversationParticipant::where('conversation_id', $channel->id)
            ->where('user_id', $member->id)->first();
        $this->assertNull($untouched?->last_delivered_at);

        // Owner's own rows are not the caller's — never stamped by member's ack.
        $ownerRow = ConversationParticipant::where('conversation_id', $withNew->id)
            ->where('user_id', $owner->id)->first();
        $this->assertNull($ownerRow?->last_delivered_at);
    }

    public function test_bulk_ack_broadcasts_delivered_per_affected_conversation(): void
    {
        Event::fake([ConversationStreamEvent::class]);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $owner->id, 'body' => 'undelivered']);

        $this->actingAs($member)->postJson('/api/mobile/conversations/delivered')->assertStatus(204);

        Event::assertDispatched(ConversationStreamEvent::class, function ($event) use ($dm, $member) {
            return $event->broadcastAs() === 'conversation.delivered'
                && $event->conversationId === $dm->id
                && $event->broadcastWith()['user_id'] === $member->id
                && is_string($event->broadcastWith()['last_delivered_at']);
        });
    }

    public function test_bulk_ack_is_noop_and_silent_when_nothing_new(): void
    {
        Event::fake([ConversationStreamEvent::class]);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $owner->id, 'body' => 'old']);

        // First ack stamps; second ack (nothing newer) must not broadcast again.
        $this->actingAs($member)->postJson('/api/mobile/conversations/delivered')->assertStatus(204);
        $this->actingAs($member)->postJson('/api/mobile/conversations/delivered')->assertStatus(204);

        Event::assertDispatchedTimes(ConversationStreamEvent::class, 1);
    }

    public function test_own_messages_do_not_require_delivery(): void
    {
        Event::fake([ConversationStreamEvent::class]);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        // Only the caller's OWN message exists — acking your own send is pointless churn.
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'mine']);

        $this->actingAs($member)->postJson('/api/mobile/conversations/delivered')->assertStatus(204);

        Event::assertNotDispatched(ConversationStreamEvent::class);
    }
}
