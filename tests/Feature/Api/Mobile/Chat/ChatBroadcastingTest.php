<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Events\BandDataChanged;
use App\Events\ConversationChanged;
use App\Events\ConversationStreamEvent;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatBroadcastingTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_band_thread_message_broadcasts_thin_band_signal_with_conversation_parent(): void
    {
        Event::fake([BandDataChanged::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $message = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'hi band']);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->bandId === $band->id
                && $e->model === 'message'
                && $e->id === $message->id
                && $e->action === 'created'
                && $e->parent === ['model' => 'conversation', 'id' => $channel->id],
        );
    }

    public function test_dm_message_signals_each_participants_user_channel_not_a_band(): void
    {
        Event::fake([BandDataChanged::class, ConversationChanged::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'psst']);

        Event::assertNotDispatched(BandDataChanged::class);
        foreach ([$owner->id, $member->id] as $userId) {
            Event::assertDispatched(
                ConversationChanged::class,
                fn (ConversationChanged $e) => $e->userId === $userId
                    && $e->conversationId === $dm->id
                    && $e->messageId === $message->id
                    && $e->action === 'created',
            );
        }
    }

    public function test_store_message_endpoint_emits_stream_event_with_full_payload(): void
    {
        Event::fake([ConversationStreamEvent::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$channel->id}/messages", ['body' => 'live'])
            ->assertStatus(201);

        Event::assertDispatched(
            ConversationStreamEvent::class,
            fn (ConversationStreamEvent $e) => $e->conversationId === $channel->id
                && $e->type === 'message.created'
                && $e->data['message']['body'] === 'live',
        );
    }

    public function test_edit_and_delete_emit_stream_events(): void
    {
        Event::fake([ConversationStreamEvent::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $message = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'v1']);

        $this->actingAs($owner)->patchJson("/api/mobile/messages/{$message->id}", ['body' => 'v2'])->assertOk();
        Event::assertDispatched(
            ConversationStreamEvent::class,
            fn (ConversationStreamEvent $e) => $e->type === 'message.updated'
                && $e->data['message']['body'] === 'v2',
        );

        $this->actingAs($owner)->deleteJson("/api/mobile/messages/{$message->id}")->assertStatus(204);
        Event::assertDispatched(
            ConversationStreamEvent::class,
            fn (ConversationStreamEvent $e) => $e->type === 'message.deleted'
                && $e->data['message_id'] === $message->id,
        );
    }

    public function test_read_and_typing_emit_stream_events(): void
    {
        Event::fake([ConversationStreamEvent::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $message = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'mark me']);

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$channel->id}/read", ['last_read_message_id' => $message->id])
            ->assertStatus(204);
        Event::assertDispatched(
            ConversationStreamEvent::class,
            fn (ConversationStreamEvent $e) => $e->type === 'conversation.read'
                && $e->data['user_id'] === $owner->id
                && is_string($e->data['last_read_at']),
        );

        $this->actingAs($owner)->postJson("/api/mobile/conversations/{$channel->id}/typing")->assertStatus(204);
        Event::assertDispatched(
            ConversationStreamEvent::class,
            fn (ConversationStreamEvent $e) => $e->type === 'conversation.typing'
                && $e->data['user_id'] === $owner->id
                && $e->data['name'] === $owner->name,
        );
    }

    public function test_typing_has_its_own_rate_limit_separate_from_the_shared_api_budget(): void
    {
        Event::fake([ConversationStreamEvent::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        // 30/min budget: the 30th ping is fine, the 31st is throttled — and
        // this alone (well under 60 calls) proves typing isn't sharing the
        // general 'api' 60/min limiter, since it exhausts at half that.
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($owner)
                ->postJson("/api/mobile/conversations/{$channel->id}/typing")
                ->assertStatus(204);
        }

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$channel->id}/typing")
            ->assertStatus(429);

        // Sending a message right after hitting the typing limit must not be
        // starved — it lives on a separate budget.
        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$channel->id}/messages", ['body' => 'still works'])
            ->assertStatus(201);
    }

    public function test_duplicate_read_posts_broadcast_conversation_read_only_once(): void
    {
        Event::fake([ConversationStreamEvent::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $message = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'mark me']);

        foreach (range(1, 2) as $attempt) {
            $this->actingAs($owner)
                ->postJson("/api/mobile/conversations/{$channel->id}/read", ['last_read_message_id' => $message->id])
                ->assertStatus(204);
        }

        // The marker only advances on the first POST — the duplicate must
        // not re-broadcast conversation.read.
        $this->assertCount(1, Event::dispatched(
            ConversationStreamEvent::class,
            fn (ConversationStreamEvent $e) => $e->type === 'conversation.read',
        ));
    }

    public function test_stream_event_broadcasts_as_its_type_with_no_envelope(): void
    {
        $event = new ConversationStreamEvent(7, 'message.deleted', ['message_id' => 42]);

        $this->assertSame('message.deleted', $event->broadcastAs());
        $this->assertSame(['message_id' => 42], $event->broadcastWith());
    }
}
