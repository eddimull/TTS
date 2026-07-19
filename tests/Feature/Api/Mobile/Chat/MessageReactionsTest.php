<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Events\ConversationStreamEvent;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageReactionsTest extends TestCase
{
    use RefreshDatabase;
    use ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_formatter_aggregates_reactions_by_emoji(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        MessageReaction::create(['message_id' => $message->id, 'user_id' => $owner->id, 'emoji' => '👍']);
        MessageReaction::create(['message_id' => $message->id, 'user_id' => $member->id, 'emoji' => '👍']);
        MessageReaction::create(['message_id' => $message->id, 'user_id' => $member->id, 'emoji' => '🎉']);

        $formatted = app(MessageFormatter::class)->format($message->fresh(['user', 'attachments', 'reactions']));

        $this->assertSame([
            ['emoji' => '👍', 'count' => 2, 'user_ids' => [$owner->id, $member->id]],
            ['emoji' => '🎉', 'count' => 1, 'user_ids' => [$member->id]],
        ], $formatted['reactions']);
    }

    public function test_formatter_returns_empty_reactions_array_when_none(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $formatted = app(MessageFormatter::class)->format($message->fresh(['user', 'attachments', 'reactions']));

        $this->assertSame([], $formatted['reactions']);
    }

    public function test_duplicate_reaction_rows_are_rejected_by_unique_index(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        MessageReaction::create(['message_id' => $message->id, 'user_id' => $owner->id, 'emoji' => '👍']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        MessageReaction::create(['message_id' => $message->id, 'user_id' => $owner->id, 'emoji' => '👍']);
    }

    public function test_deleting_message_cascades_reactions(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);
        MessageReaction::create(['message_id' => $message->id, 'user_id' => $owner->id, 'emoji' => '👍']);

        $message->forceDelete();

        $this->assertDatabaseCount('message_reactions', 0);
    }

    public function test_participant_can_add_and_remove_reaction(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $this->actingAs($member)
            ->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])
            ->assertOk()
            ->assertJson(['reactions' => [['emoji' => '👍', 'count' => 1, 'user_ids' => [$member->id]]]]);

        $this->actingAs($member)
            ->deleteJson("/api/mobile/messages/{$message->id}/reactions/" . rawurlencode('👍'))
            ->assertOk()
            ->assertJsonCount(0, 'reactions');

        $this->assertDatabaseCount('message_reactions', 0);
    }

    public function test_add_is_idempotent_and_remove_of_absent_is_ok(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $this->actingAs($member)->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertOk();
        $this->actingAs($member)->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseCount('message_reactions', 1);

        $this->actingAs($member)
            ->deleteJson("/api/mobile/messages/{$message->id}/reactions/" . rawurlencode('😂'))
            ->assertOk();
        $this->assertDatabaseCount('message_reactions', 1);
    }

    public function test_non_participant_cannot_react(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        [$outsider] = $this->makeOwnerWithBand();
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $this->actingAs($outsider)
            ->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])
            ->assertForbidden();
    }

    public function test_reacting_to_soft_deleted_message_is_404(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);
        $message->delete();

        $this->actingAs($member)
            ->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])
            ->assertNotFound();
    }

    public function test_emoji_is_required_and_bounded(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $this->actingAs($member)
            ->postJson("/api/mobile/messages/{$message->id}/reactions", [])
            ->assertUnprocessable();
        $this->actingAs($member)
            ->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => str_repeat('x', 17)])
            ->assertUnprocessable();
    }

    public function test_formatter_empties_reactions_on_tombstoned_message(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        MessageReaction::create(['message_id' => $message->id, 'user_id' => $owner->id, 'emoji' => '👍']);

        $message->delete();

        $formatted = app(MessageFormatter::class)->format($message->fresh(['user', 'attachments', 'reactions']));

        $this->assertSame([], $formatted['reactions']);
    }

    public function test_reaction_changes_broadcast_message_updated(): void
    {
        Event::fake([ConversationStreamEvent::class]);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $this->actingAs($member)->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertOk();

        // broadcastOn() returns an array here (see ConversationStreamEvent), so
        // we follow ChatBroadcastingTest's idiom and assert on the event's
        // public properties instead of $event->broadcastOn()->name.
        Event::assertDispatched(ConversationStreamEvent::class, function ($event) use ($dm, $message) {
            return $event->broadcastAs() === 'message.updated'
                && $event->conversationId === $dm->id
                && $event->broadcastWith()['message']['id'] === $message->id
                && $event->broadcastWith()['message']['reactions'][0]['emoji'] === '👍';
        });
    }

    public function test_duplicate_reaction_post_does_not_rebroadcast(): void
    {
        Event::fake([ConversationStreamEvent::class]);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $this->actingAs($member)->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertOk();
        $this->actingAs($member)->postJson("/api/mobile/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertOk();

        Event::assertDispatchedTimes(ConversationStreamEvent::class, 1);
    }

    public function test_deleting_absent_reaction_does_not_broadcast(): void
    {
        Event::fake([ConversationStreamEvent::class]);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        $this->actingAs($member)
            ->deleteJson("/api/mobile/messages/{$message->id}/reactions/" . rawurlencode('😂'))
            ->assertOk();

        Event::assertNotDispatched(ConversationStreamEvent::class);
    }
}
