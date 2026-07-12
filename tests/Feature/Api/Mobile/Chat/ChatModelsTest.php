<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatModelsTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_key_builders_are_deterministic(): void
    {
        $this->assertSame('dm:3:9', Conversation::dmKeyFor(9, 3));
        $this->assertSame('dm:3:9', Conversation::dmKeyFor(3, 9));
        $this->assertSame('band:5', Conversation::bandKeyFor(5));

        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $this->assertSame('topic:App\\Models\\Events:' . $event->id, Conversation::topicKeyFor($event));
    }

    public function test_unique_key_is_enforced_at_the_database(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        Conversation::create([
            'type' => Conversation::TYPE_BAND, 'band_id' => $band->id,
            'unique_key' => Conversation::bandKeyFor($band->id),
        ]);

        $this->expectException(QueryException::class);
        Conversation::create([
            'type' => Conversation::TYPE_BAND, 'band_id' => $band->id,
            'unique_key' => Conversation::bandKeyFor($band->id),
        ]);
    }

    public function test_message_soft_deletes_and_relations_work(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $conversation = Conversation::create([
            'type' => Conversation::TYPE_BAND, 'band_id' => $band->id,
            'unique_key' => Conversation::bandKeyFor($band->id),
        ]);
        $conversation->participants()->create(['user_id' => $owner->id, 'last_read_at' => now()]);
        $message = $conversation->messages()->create(['user_id' => $owner->id, 'body' => 'hello']);

        $this->assertSame($conversation->id, $message->conversation->id);
        $this->assertSame($owner->id, $message->user->id);
        $this->assertCount(1, $conversation->participants);

        $message->delete();
        $this->assertSoftDeleted('messages', ['id' => $message->id]);
        $this->assertCount(1, $conversation->messages()->withTrashed()->get());
    }

    public function test_moderate_chat_permission_exists(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'moderate:chat', 'guard_name' => 'web']);
    }
}
