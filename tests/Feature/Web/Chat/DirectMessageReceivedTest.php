<?php

namespace Tests\Feature\Web\Chat;

use App\Jobs\SendUserPush;
use App\Notifications\CommentPosted;
use App\Notifications\DirectMessageReceived;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class DirectMessageReceivedTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake([SendUserPush::class]);
    }

    public function test_dm_notifies_only_the_other_participant_database_only(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', $dm), ['body' => 'Soundcheck moved to 4, can you make it?'])
            ->assertCreated();

        Notification::assertSentTo($member, DirectMessageReceived::class, function (DirectMessageReceived $n, array $channels) use ($owner, $dm) {
            $data = $n->toArray($owner);

            return $channels === ['database']
                && $data['route'] === 'messages.index'
                && $data['routeParams'] === ['conversation' => $dm->id]
                && $data['text'] === $owner->name . ': Soundcheck moved to 4, can you make it?'
                && $data['conversation_id'] === $dm->id;
        });
        Notification::assertNotSentTo($owner, DirectMessageReceived::class);
    }

    public function test_bell_route_params_resolve_to_the_inbox_url(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $owner->id, 'body' => 'x']);

        $data = (new DirectMessageReceived($message, $dm))->toArray($member);

        $this->assertStringEndsWith('/messages/' . $dm->id, route($data['route'], $data['routeParams']));
    }

    public function test_image_only_dm_uses_photo_placeholder(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['filesystems.default' => 'local']);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->post(route('chat.conversations.messages.store', $dm), [
                'images' => [\Illuminate\Http\UploadedFile::fake()->image('p.jpg', 40, 40)],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        Notification::assertSentTo($member, DirectMessageReceived::class, fn (DirectMessageReceived $n) =>
            str_ends_with($n->toArray($member)['text'], ': 📷 Photo'));
    }

    public function test_band_channel_messages_create_no_bell_entry_and_topics_still_use_comment_posted(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:events']);
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $topic   = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));

        $this->actingAs($owner)->postJson(route('chat.conversations.messages.store', $channel), ['body' => 'all'])->assertCreated();
        $this->actingAs($owner)->postJson(route('chat.conversations.messages.store', $topic), ['body' => 'cmt'])->assertCreated();

        Notification::assertNotSentTo($member, DirectMessageReceived::class);
        Notification::assertSentTo($member, CommentPosted::class);
    }
}
