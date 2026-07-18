<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
