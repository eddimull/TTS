<?php

namespace Tests\Feature\Web\Chat;

use App\Jobs\SendUserPush;
use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ChatWebRoutesTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        // Matches every analogous Api/Mobile/Chat test (e.g. MessageAttachmentsTest,
        // MessagesTest, ChatPushTest): storeMessage() unconditionally dispatches
        // ProcessChatMessagePush, which resolves kreait/firebase-php — unconfigured
        // in tests. Faking SendUserPush keeps that job's own effects untested here
        // (routing is this file's only concern) without touching any assertion.
        Queue::fake([SendUserPush::class]);
    }

    public function test_all_chat_routes_are_registered_with_expected_names(): void
    {
        foreach ([
            'chat.events.conversation', 'chat.rehearsals.conversation', 'chat.bookings.conversation',
            'chat.conversations.messages.index', 'chat.conversations.messages.store',
            'chat.conversations.read', 'chat.conversations.typing',
            'chat.messages.update', 'chat.messages.destroy', 'chat.messages.attachments.show',
            'chat.messages.reactions.store', 'chat.messages.reactions.destroy',
        ] as $name) {
            $this->assertTrue(Route::has($name), "missing route {$name}");
        }
    }

    public function test_member_resolves_event_thread_with_a_session(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $response = $this->actingAs($owner)
            ->getJson(route('chat.events.conversation', $event))
            ->assertOk();

        $this->assertSame('topic', $response->json('conversation.type'));
        $this->assertSame('private-conversation.' . $response->json('conversation.id'), $response->json('channel'));
    }

    public function test_guest_is_redirected_or_401(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $this->get(route('chat.events.conversation', $event))->assertRedirect(route('login'));
        $this->getJson(route('chat.events.conversation', $event))->assertUnauthorized();
    }

    public function test_outsider_gets_403_from_the_policy(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson(route('chat.events.conversation', $event))
            ->assertForbidden();
    }

    public function test_rehearsal_route_reaches_the_canonical_thread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);

        $viaEvent     = $this->actingAs($owner)->getJson(route('chat.events.conversation', $event))->assertOk();
        $viaRehearsal = $this->actingAs($owner)->getJson(route('chat.rehearsals.conversation', $rehearsal))->assertOk();

        $this->assertSame($viaEvent->json('conversation.id'), $viaRehearsal->json('conversation.id'));
    }

    public function test_booking_route_is_band_scoped_and_denies_subs(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        $sub     = $this->makeSubAssignedTo($band, $event);

        $this->actingAs($owner)
            ->getJson(route('chat.bookings.conversation', ['band' => $band, 'booking' => $booking]))
            ->assertOk();

        $this->actingAs($sub)
            ->getJson(route('chat.bookings.conversation', ['band' => $band, 'booking' => $booking]))
            ->assertForbidden();
    }

    public function test_member_can_post_edit_react_and_delete(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);

        $created = $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', $topic), ['body' => 'first'])
            ->assertCreated();
        $id = $created->json('message.id');

        $this->actingAs($owner)
            ->getJson(route('chat.conversations.messages.index', $topic))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'first');

        $this->actingAs($owner)
            ->patchJson(route('chat.messages.update', $id), ['body' => 'edited'])
            ->assertOk()
            ->assertJsonPath('message.body', 'edited');

        $this->actingAs($owner)
            ->postJson(route('chat.messages.reactions.store', $id), ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reactions.0.emoji', '👍');

        $this->actingAs($owner)
            ->deleteJson(route('chat.messages.reactions.destroy', ['message' => $id, 'emoji' => '👍']))
            ->assertOk()
            ->assertJsonPath('reactions', []);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.read', $topic), ['last_read_message_id' => $id])
            ->assertNoContent();

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.typing', $topic))
            ->assertNoContent();

        $this->actingAs($owner)
            ->deleteJson(route('chat.messages.destroy', $id))
            ->assertNoContent();
    }

    public function test_attachment_route_streams_for_a_viewer_and_403s_an_outsider(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $event  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($event);

        $created = $this->actingAs($owner)
            ->post(route('chat.conversations.messages.store', $topic), [
                'images' => [UploadedFile::fake()->image('photo.jpg', 120, 80)],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $messageId    = $created->json('message.id');
        $attachmentId = $created->json('message.attachments.0.id');
        $url = route('chat.messages.attachments.show', ['message' => $messageId, 'attachment' => $attachmentId]);

        $this->actingAs($member)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_inbox_routes_are_registered(): void
    {
        foreach (['messages.index', 'chat.conversations.index', 'chat.conversations.dm', 'chat.contacts', 'chat.conversations.delivered', 'chat.unread-count'] as $name) {
            $this->assertTrue(Route::has($name), "missing route {$name}");
        }
    }

    public function test_list_dm_contacts_delivered_and_unread_count_work_with_a_session(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        $contacts = $this->actingAs($owner)->getJson(route('chat.contacts'))->assertOk();
        $this->assertContains($member->id, collect($contacts->json('contacts'))->pluck('id')->all());

        $dm = $this->actingAs($owner)
            ->postJson(route('chat.conversations.dm'), ['user_id' => $member->id])
            ->assertOk();
        $this->assertSame('dm', $dm->json('conversation.type'));

        $this->actingAs($member)
            ->postJson(route('chat.conversations.messages.store', $dm->json('conversation.id')), ['body' => 'yo'])
            ->assertCreated();

        $this->actingAs($owner)->getJson(route('chat.unread-count'))->assertOk()->assertJson(['count' => 1]);

        $list = $this->actingAs($owner)->getJson(route('chat.conversations.index'))->assertOk();
        $this->assertSame(1, collect($list->json('conversations'))->firstWhere('type', 'dm')['unread_count']);

        $this->actingAs($owner)->postJson(route('chat.conversations.delivered'))->assertNoContent();
    }

    public function test_dm_requires_a_shared_band(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $stranger = User::factory()->create();

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.dm'), ['user_id' => $stranger->id])
            ->assertForbidden();
    }
}
