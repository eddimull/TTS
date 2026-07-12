<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicConversationTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_event_conversation_resolves_and_returns_messages(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $response = $this->actingAs($owner)
            ->getJson("/api/mobile/events/{$event->id}/conversation")
            ->assertOk();

        $this->assertSame('topic', $response->json('conversation.type'));
        $this->assertSame([], $response->json('messages'));
        $this->assertFalse($response->json('has_more'));
        $this->assertTrue($response->json('conversation.can_moderate'));
        $this->assertSame(
            'private-conversation.' . $response->json('conversation.id'),
            $response->json('channel'),
        );
        // Opening the thread registered the viewer as a participant.
        $this->assertContains($owner->id, collect($response->json('participants'))->pluck('user_id')->all());

        // Same event → same conversation.
        $again = $this->actingAs($owner)->getJson("/api/mobile/events/{$event->id}/conversation");
        $this->assertSame($response->json('conversation.id'), $again->json('conversation.id'));
    }

    public function test_event_and_rehearsal_endpoints_reach_the_same_canonical_thread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);

        $viaEvent = $this->actingAs($owner)
            ->getJson("/api/mobile/events/{$event->id}/conversation")->assertOk();
        $viaRehearsal = $this->actingAs($owner)
            ->getJson("/api/mobile/rehearsals/{$rehearsal->id}/conversation")->assertOk();

        $this->assertSame(
            $viaEvent->json('conversation.id'),
            $viaRehearsal->json('conversation.id'),
        );
    }

    public function test_booking_conversation_is_reachable_and_distinct_from_event_thread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;

        $bookingThread = $this->actingAs($owner)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/conversation")
            ->assertOk();
        $eventThread = $this->actingAs($owner)
            ->getJson("/api/mobile/events/{$event->id}/conversation")->assertOk();

        $this->assertNotSame(
            $bookingThread->json('conversation.id'),
            $eventThread->json('conversation.id'),
        );
    }

    public function test_entitled_sub_reaches_event_thread_but_unentitled_sub_is_403(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event      = $this->makeBookingEvent($band);
        $otherEvent = $this->makeBookingEvent($band);
        $entitled   = $this->makeSubAssignedTo($band, $event);
        $unentitled = $this->makeSubAssignedTo($band, $otherEvent);

        $this->actingAs($entitled)
            ->getJson("/api/mobile/events/{$event->id}/conversation")->assertOk();
        $this->actingAs($unentitled)
            ->getJson("/api/mobile/events/{$event->id}/conversation")->assertStatus(403);
    }

    public function test_sub_cannot_reach_a_booking_thread(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $sub   = $this->makeSubAssignedTo($band, $event);

        $this->actingAs($sub)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$event->eventable->id}/conversation")
            ->assertStatus(403);
    }
}
