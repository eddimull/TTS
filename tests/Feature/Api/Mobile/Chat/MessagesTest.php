<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_participant_can_send_and_list_messages(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$dm->id}/messages", ['body' => 'first!'])
            ->assertStatus(201)
            ->assertJsonPath('message.body', 'first!')
            ->assertJsonPath('message.user_id', $owner->id);

        $list = $this->actingAs($member)
            ->getJson("/api/mobile/conversations/{$dm->id}/messages")->assertOk();
        $this->assertCount(1, $list->json('messages'));
        $this->assertFalse($list->json('has_more'));
        $this->assertSame('first!', $list->json('messages.0.body'));
        $this->assertSame($owner->name, $list->json('messages.0.user_name'));
        $this->assertSame('dm', $list->json('conversation.type'));
        $this->assertSame('private-conversation.' . $dm->id, $list->json('channel'));
    }

    public function test_non_participant_cannot_send_or_read(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member   = $this->makeMember($band);
        $outsider = \App\Models\User::factory()->create();
        $dm = app(ConversationService::class)->dmBetween($owner, $member);

        $this->actingAs($outsider)
            ->postJson("/api/mobile/conversations/{$dm->id}/messages", ['body' => 'nope'])
            ->assertStatus(403);
        $this->actingAs($outsider)
            ->getJson("/api/mobile/conversations/{$dm->id}/messages")->assertStatus(403);
    }

    public function test_cursor_pagination_walks_backwards(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);
        for ($i = 1; $i <= 60; $i++) {
            $channel->messages()->create(['user_id' => $owner->id, 'body' => "m{$i}"]);
        }

        $page1 = $this->actingAs($owner)
            ->getJson("/api/mobile/conversations/{$channel->id}/messages")->assertOk();
        $this->assertCount(50, $page1->json('messages'));
        $this->assertTrue($page1->json('has_more'));
        $this->assertSame('m60', collect($page1->json('messages'))->last()['body']);

        $oldestId = $page1->json('messages')[0]['id'];
        $page2 = $this->actingAs($owner)
            ->getJson("/api/mobile/conversations/{$channel->id}/messages?before={$oldestId}")->assertOk();
        $this->assertCount(10, $page2->json('messages'));
        $this->assertFalse($page2->json('has_more'));
    }

    public function test_author_can_edit_own_message_and_gets_edited_marker(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $message = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'typo']);

        $response = $this->actingAs($owner)
            ->patchJson("/api/mobile/messages/{$message->id}", ['body' => 'fixed'])
            ->assertOk();

        $this->assertSame('fixed', $response->json('message.body'));
        $this->assertNotNull($response->json('message.edited_at'));
    }

    public function test_only_the_author_can_edit(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band);
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $message = $channel->messages()->create(['user_id' => $member->id, 'body' => 'mine']);

        // Even the owner (a moderator) cannot EDIT someone else's message.
        $this->actingAs($owner)
            ->patchJson("/api/mobile/messages/{$message->id}", ['body' => 'hijack'])
            ->assertStatus(403);
    }

    public function test_author_deletes_own_message_leaving_a_tombstone(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $message = $channel->messages()->create(['user_id' => $owner->id, 'body' => 'oops']);

        $this->actingAs($owner)->deleteJson("/api/mobile/messages/{$message->id}")->assertStatus(204);

        $list = $this->actingAs($owner)
            ->getJson("/api/mobile/conversations/{$channel->id}/messages")->assertOk();
        $row = collect($list->json('messages'))->firstWhere('id', $message->id);
        $this->assertTrue($row['is_deleted']);
        $this->assertNull($row['body']);
    }

    public function test_moderator_can_delete_others_messages_in_band_thread_but_plain_member_cannot(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $author  = $this->makeMember($band);
        $plain   = $this->makeMember($band);
        $channel = app(ConversationService::class)->bandChannelFor($band);
        $m1 = $channel->messages()->create(['user_id' => $author->id, 'body' => 'a']);
        $m2 = $channel->messages()->create(['user_id' => $author->id, 'body' => 'b']);

        $this->actingAs($plain)->deleteJson("/api/mobile/messages/{$m1->id}")->assertStatus(403);
        $this->actingAs($owner)->deleteJson("/api/mobile/messages/{$m1->id}")->assertStatus(204);

        setPermissionsTeamId($band->id);
        $plain->givePermissionTo('moderate:chat');
        setPermissionsTeamId(0);
        $this->actingAs($plain)->deleteJson("/api/mobile/messages/{$m2->id}")->assertStatus(204);
    }

    public function test_dm_messages_cannot_be_deleted_by_the_other_participant(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band);
        $dm      = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $member->id, 'body' => 'private']);

        $this->actingAs($owner)->deleteJson("/api/mobile/messages/{$message->id}")->assertStatus(403);
    }

    public function test_read_endpoint_zeroes_the_unread_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $message = $dm->messages()->create(['user_id' => $member->id, 'body' => 'unread me']);

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$dm->id}/read", ['last_read_message_id' => $message->id])
            ->assertStatus(204);

        $list = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();
        $dmRow = collect($list->json('conversations'))->firstWhere('type', 'dm');
        $this->assertSame(0, $dmRow['unread_count']);
    }

    public function test_read_marker_never_moves_backwards(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $older = $dm->messages()->create(['user_id' => $member->id, 'body' => 'older']);
        $newer = $dm->messages()->create(['user_id' => $member->id, 'body' => 'newer']);
        $newer->forceFill(['created_at' => now()->addMinute()])->save();

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$dm->id}/read", ['last_read_message_id' => $newer->id])
            ->assertStatus(204);
        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$dm->id}/read", ['last_read_message_id' => $older->id])
            ->assertStatus(204);

        $list = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();
        $dmRow = collect($list->json('conversations'))->firstWhere('type', 'dm');
        $this->assertSame(0, $dmRow['unread_count'], 'an out-of-order read call must not resurrect unreads');
    }

    public function test_body_is_required_without_images_and_capped(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$channel->id}/messages", [])
            ->assertStatus(422);
        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$channel->id}/messages", ['body' => str_repeat('x', 4001)])
            ->assertStatus(422);
    }
}
