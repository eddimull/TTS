<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Jobs\SendUserPush;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatPushTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_dm_message_pushes_only_to_the_other_participant(): void
    {
        Queue::fake([SendUserPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$dm->id}/messages", ['body' => 'ping'])
            ->assertStatus(201);

        Queue::assertPushed(SendUserPush::class, 1);
        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) =>
            $job->userId === $member->id
            && $job->data['type'] === 'chat_message'
            && $job->data['conversationId'] === (string) $dm->id
            && $job->data['title'] === $owner->name
            && $job->data['body'] === 'ping'
            && str_starts_with($job->dedupeKey, 'chat_message:'));
    }

    public function test_band_channel_message_pushes_to_owner_and_members_except_author(): void
    {
        Queue::fake([SendUserPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $memberA = $this->makeMember($band);
        $memberB = $this->makeMember($band);
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $this->actingAs($memberA)
            ->postJson("/api/mobile/conversations/{$channel->id}/messages", ['body' => 'sound check 6pm'])
            ->assertStatus(201);

        Queue::assertPushed(SendUserPush::class, 2);
        foreach ([$owner->id, $memberB->id] as $expected) {
            Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) => $job->userId === $expected);
        }
        Queue::assertNotPushed(SendUserPush::class, fn (SendUserPush $job) => $job->userId === $memberA->id);
    }

    public function test_event_topic_message_pushes_to_readers_and_entitled_subs(): void
    {
        Queue::fake([SendUserPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member     = $this->makeMember($band, ['read:events']);
        $noRead     = $this->makeMember($band, []);
        $event      = $this->makeBookingEvent($band);
        $otherEvent = $this->makeBookingEvent($band);
        $entitled   = $this->makeSubAssignedTo($band, $event);
        $unentitled = $this->makeSubAssignedTo($band, $otherEvent);
        $topic = app(ConversationService::class)->topicFor($event);

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$topic->id}/messages", ['body' => 'comment'])
            ->assertStatus(201);

        Queue::assertPushed(SendUserPush::class, 2); // member + entitled sub
        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) => $job->userId === $member->id);
        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) => $job->userId === $entitled->id);
        Queue::assertNotPushed(SendUserPush::class, fn (SendUserPush $job) => in_array($job->userId, [$noRead->id, $unentitled->id, $owner->id]));
    }

    public function test_image_only_message_pushes_a_photo_placeholder_body(): void
    {
        Queue::fake([SendUserPush::class]);
        \Illuminate\Support\Facades\Storage::fake(config('filesystems.default'));
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)->post(
            "/api/mobile/conversations/{$dm->id}/messages",
            ['images' => [\Illuminate\Http\UploadedFile::fake()->image('pic.jpg')]],
            ['Accept' => 'application/json'],
        )->assertStatus(201);

        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) => $job->data['body'] === '📷 Photo');
    }
}
