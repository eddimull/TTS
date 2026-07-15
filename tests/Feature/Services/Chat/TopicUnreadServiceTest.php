<?php

namespace Tests\Feature\Services\Chat;

use App\Models\Events;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ConversationService;
use App\Services\Chat\TopicUnreadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class TopicUnreadServiceTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_counts_unread_messages_per_conversable(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $reader = User::factory()->create();
        $author = $owner;
        $event  = $this->makeBookingEvent($band);

        $conversation = app(ConversationService::class)->topicFor($event);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'load in at 5?',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'anyone?',
        ]);

        $counts = app(TopicUnreadService::class)->unreadCountsForConversables(
            $reader,
            [[Events::class, $event->id]],
        );

        $this->assertSame(
            [Events::class . ':' . $event->id => 2],
            $counts,
        );
    }

    public function test_messages_before_last_read_marker_do_not_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $reader = User::factory()->create();
        $author = $owner;
        $event  = $this->makeBookingEvent($band);

        $conversation = app(ConversationService::class)->topicFor($event);
        $old = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'old news',
        ]);
        // created_at is not mass-assignable on Message; force it after
        // create() to backdate the message, mirroring MessagesTest's pattern.
        $old->forceFill(['created_at' => now()->subHour()])->save();
        $conversation->participants()->create([
            'user_id' => $reader->id,
            'last_read_at' => now()->subMinutes(30),
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'fresh',
        ]);

        $counts = app(TopicUnreadService::class)->unreadCountsForConversables(
            $reader,
            [[Events::class, $event->id]],
        );

        $this->assertSame(
            [Events::class . ':' . $event->id => 1],
            $counts,
        );
    }

    public function test_own_messages_and_missing_conversations_are_zero(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $reader = $owner;
        $event  = $this->makeBookingEvent($band);
        $noThreadEvent = $this->makeBookingEvent($band);

        $conversation = app(ConversationService::class)->topicFor($event);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $reader->id,
            'body' => 'my own note',
        ]);

        $counts = app(TopicUnreadService::class)->unreadCountsForConversables(
            $reader,
            [[Events::class, $event->id], [Events::class, $noThreadEvent->id]],
        );

        $this->assertSame([], $counts);
    }
}
