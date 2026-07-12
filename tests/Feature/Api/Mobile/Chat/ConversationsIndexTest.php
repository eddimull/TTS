<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationsIndexTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_index_lists_band_channels_and_dms_with_unread_counts(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band);
        $service = app(ConversationService::class);

        $dm = $service->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'hey there']);

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $conversations = collect($response->json('conversations'));
        $this->assertCount(2, $conversations); // band channel (lazily created) + dm

        $bandRow = $conversations->firstWhere('type', 'band');
        $this->assertSame($band->name, $bandRow['title']);

        $dmRow = $conversations->firstWhere('type', 'dm');
        $this->assertSame($member->name, $dmRow['title']);
        $this->assertSame('hey there', $dmRow['last_message_preview']);
        $this->assertNotNull($dmRow['last_message_at']);
        $this->assertSame(1, $dmRow['unread_count']);
        $this->assertTrue($bandRow['can_moderate'], 'owner moderates the band channel');
        $this->assertFalse($dmRow['can_moderate'], 'DMs are never moderatable');
    }

    public function test_own_messages_do_not_count_as_unread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $owner->id, 'body' => 'my own']);

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $dmRow = collect($response->json('conversations'))->firstWhere('type', 'dm');
        $this->assertSame(0, $dmRow['unread_count']);
    }

    public function test_sub_only_user_sees_no_band_channel(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $sub   = $this->makeSubAssignedTo($band, $event);

        $response = $this->actingAs($sub)->getJson('/api/mobile/conversations')->assertOk();

        $this->assertSame([], $response->json('conversations'));
    }

    public function test_store_dm_creates_thread_with_a_bandmate(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        $response = $this->actingAs($owner)
            ->postJson('/api/mobile/conversations/dm', ['user_id' => $member->id])
            ->assertOk();

        $this->assertSame('dm', $response->json('conversation.type'));
        $this->assertSame($member->name, $response->json('conversation.title'));

        // Idempotent: same pair → same conversation id.
        $again = $this->actingAs($member)
            ->postJson('/api/mobile/conversations/dm', ['user_id' => $owner->id])
            ->assertOk();
        $this->assertSame($response->json('conversation.id'), $again->json('conversation.id'));
    }

    public function test_store_dm_rejects_users_with_no_shared_band(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $stranger = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/mobile/conversations/dm', ['user_id' => $stranger->id])
            ->assertStatus(403);
    }

    public function test_contacts_lists_bandmates_and_subs_with_context_but_not_strangers(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $event  = $this->makeBookingEvent($band);
        $sub    = $this->makeSubAssignedTo($band, $event);
        User::factory()->create(); // stranger

        $response = $this->actingAs($owner)->getJson('/api/mobile/chat/contacts')->assertOk();

        $contacts = collect($response->json('contacts'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$member->id, $sub->id], $contacts->keys()->all());

        $this->assertSame($band->name, $contacts[$member->id]['context']);
        $this->assertFalse($contacts[$member->id]['is_sub']);
        $this->assertNull($contacts[$member->id]['avatar_url']);

        $this->assertSame('Sub — ' . $band->name, $contacts[$sub->id]['context']);
        $this->assertTrue($contacts[$sub->id]['is_sub']);
    }

    public function test_contacts_for_a_sub_lists_the_bands_owner_and_members(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $event  = $this->makeBookingEvent($band);
        $sub    = $this->makeSubAssignedTo($band, $event);

        $response = $this->actingAs($sub)->getJson('/api/mobile/chat/contacts')->assertOk();

        $contacts = collect($response->json('contacts'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$owner->id, $member->id], $contacts->keys()->all());
        $this->assertSame($band->name, $contacts[$owner->id]['context']);
        $this->assertFalse($contacts[$owner->id]['is_sub']);
    }
}
