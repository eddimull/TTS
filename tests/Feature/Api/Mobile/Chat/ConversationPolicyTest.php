<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationPolicyTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    private ConversationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConversationService::class);
    }

    // ── DM ───────────────────────────────────────────────────────────

    public function test_dm_is_visible_only_to_its_participants(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member   = $this->makeMember($band);
        $outsider = User::factory()->create();

        $dm = $this->service->dmBetween($owner, $member);

        $this->assertTrue($owner->can('view', $dm));
        $this->assertTrue($member->can('post', $dm));
        $this->assertFalse($outsider->can('view', $dm));
        $this->assertFalse($owner->can('moderate', $dm), 'DMs are never moderatable');
    }

    // ── Band channel ─────────────────────────────────────────────────

    public function test_band_channel_admits_owner_and_member_but_never_subs(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $event  = $this->makeBookingEvent($band);
        $sub    = $this->makeSubAssignedTo($band, $event);

        $channel = $this->service->bandChannelFor($band);

        $this->assertTrue($owner->can('view', $channel));
        $this->assertTrue($member->can('view', $channel));
        $this->assertTrue($member->can('post', $channel));
        $this->assertFalse($sub->can('view', $channel), 'subs never see the band channel');
    }

    // ── Topic: event ─────────────────────────────────────────────────

    public function test_event_topic_admits_members_with_read_events_and_entitled_subs_only(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $memberWithRead    = $this->makeMember($band, ['read:events']);
        $memberWithoutRead = $this->makeMember($band, []);
        $event         = $this->makeBookingEvent($band);
        $otherEvent    = $this->makeBookingEvent($band);
        $entitledSub   = $this->makeSubAssignedTo($band, $event);
        $unentitledSub = $this->makeSubAssignedTo($band, $otherEvent);

        $topic = $this->service->topicFor($event);

        $this->assertTrue($owner->can('view', $topic));
        $this->assertTrue($memberWithRead->can('post', $topic));
        $this->assertFalse($memberWithoutRead->can('view', $topic));
        $this->assertTrue($entitledSub->can('view', $topic), 'sub invited to THIS event may comment');
        $this->assertTrue($entitledSub->can('post', $topic));
        $this->assertFalse($unentitledSub->can('view', $topic), 'sub on a DIFFERENT event may not');
    }

    // ── Topic: rehearsal (canonicalized) ─────────────────────────────

    public function test_rehearsal_topic_uses_rehearsal_read_for_members_and_event_entitlement_for_subs(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:rehearsals']);
        $memberEventsOnly = $this->makeMember($band, ['read:events']);
        [, $rehearsalEvent] = $this->makeRehearsalEvent($band);
        $entitledSub = $this->makeSubAssignedTo($band, $rehearsalEvent);

        $topic = $this->service->topicFor($rehearsalEvent); // canonicalizes to Rehearsal

        $this->assertTrue($owner->can('view', $topic));
        $this->assertTrue($member->can('view', $topic));
        $this->assertFalse($memberEventsOnly->can('view', $topic), 'rehearsal topics need read:rehearsals');
        $this->assertTrue($entitledSub->can('view', $topic), 'sub on the wrapping event reaches the rehearsal thread');
    }

    // ── Topic: booking ───────────────────────────────────────────────

    public function test_booking_topic_requires_read_bookings_and_always_excludes_subs(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $memberWithBookings = $this->makeMember($band, ['read:bookings']);
        $memberEventsOnly   = $this->makeMember($band, ['read:events']);
        $event = $this->makeBookingEvent($band);
        $sub   = $this->makeSubAssignedTo($band, $event);

        $topic = $this->service->topicFor($event->eventable);

        $this->assertTrue($owner->can('view', $topic));
        $this->assertTrue($memberWithBookings->can('view', $topic));
        $this->assertFalse($memberEventsOnly->can('view', $topic));
        $this->assertFalse($sub->can('view', $topic), 'subs can never read booking threads');
    }

    // ── Moderation ───────────────────────────────────────────────────

    public function test_moderate_requires_ownership_or_the_moderate_chat_permission(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $plainMember = $this->makeMember($band);
        $moderator   = $this->makeMember($band, ['read:events', 'moderate:chat']);

        $channel = $this->service->bandChannelFor($band);

        $this->assertTrue($owner->can('moderate', $channel));
        $this->assertTrue($moderator->can('moderate', $channel));
        $this->assertFalse($plainMember->can('moderate', $channel));
    }
}
