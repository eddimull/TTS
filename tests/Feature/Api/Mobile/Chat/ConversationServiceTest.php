<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\Conversation;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    private ConversationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConversationService::class);
    }

    public function test_event_wrapping_a_rehearsal_canonicalizes_to_the_rehearsal(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);

        $viaEvent     = $this->service->topicFor($event);
        $viaRehearsal = $this->service->topicFor($rehearsal);

        $this->assertSame($viaEvent->id, $viaRehearsal->id, 'both entry points must reach ONE thread');
        $this->assertSame('App\\Models\\Rehearsal', $viaEvent->conversable_type);
        $this->assertSame($rehearsal->id, (int) $viaEvent->conversable_id);
        $this->assertSame($band->id, (int) $viaEvent->band_id);
    }

    public function test_booking_event_topic_attaches_to_the_event_not_the_booking(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $topic = $this->service->topicFor($event);

        $this->assertSame('App\\Models\\Events', $topic->conversable_type);
        $this->assertSame($event->id, (int) $topic->conversable_id);
        $this->assertSame($band->id, (int) $topic->band_id);
    }

    public function test_booking_topic_is_separate_from_its_events_topic(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;

        $this->assertNotSame(
            $this->service->topicFor($booking)->id,
            $this->service->topicFor($event)->id,
        );
    }

    public function test_topic_for_is_idempotent(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $this->assertSame($this->service->topicFor($event)->id, $this->service->topicFor($event)->id);
        $this->assertSame(1, Conversation::count());
    }

    public function test_band_channel_is_one_per_band(): void
    {
        [, $band] = $this->makeOwnerWithBand();

        $a = $this->service->bandChannelFor($band);
        $b = $this->service->bandChannelFor($band);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(Conversation::TYPE_BAND, $a->type);
        $this->assertSame($band->id, (int) $a->band_id);
    }

    public function test_dm_is_one_global_thread_per_user_pair_with_both_participants(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        $a = $this->service->dmBetween($owner, $member);
        $b = $this->service->dmBetween($member, $owner); // reversed order

        $this->assertSame($a->id, $b->id);
        $this->assertNull($a->band_id);
        $this->assertSame(Conversation::TYPE_DM, $a->type);
        $this->assertEqualsCanonicalizing(
            [$owner->id, $member->id],
            $a->participants()->pluck('user_id')->all(),
        );
    }

    public function test_can_dm_requires_a_shared_band(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member   = $this->makeMember($band);
        $event    = $this->makeBookingEvent($band);
        $sub      = $this->makeSubAssignedTo($band, $event);
        $stranger = \App\Models\User::factory()->create();

        $this->assertTrue($this->service->canDm($owner, $member));
        $this->assertTrue($this->service->canDm($member, $sub), 'member can DM a sub of their band');
        $this->assertTrue($this->service->canDm($sub, $member), 'sub can DM a member of a band they sub for');
        $this->assertFalse($this->service->canDm($owner, $stranger));
        $this->assertFalse($this->service->canDm($owner, $owner), 'no self-DM');
    }

    public function test_touch_participant_upserts_and_bumps_last_read(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $channel = $this->service->bandChannelFor($band);

        $first = $this->service->touchParticipant($channel, $owner);
        $this->assertNotNull($first->last_read_at);

        $again = $this->service->touchParticipant($channel, $owner);
        $this->assertSame($first->id, $again->id, 'no duplicate participant row');
    }
}
