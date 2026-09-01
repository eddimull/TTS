<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\Bands;
use App\Models\Message;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Topic threads (booking / event / rehearsal chats) surface in the mobile
 * Messages list alongside band channels and DMs.
 */
class ConversationsIndexTopicsTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    private function topicRows(array $conversations): array
    {
        return collect($conversations)->where('type', 'topic')->values()->all();
    }

    public function test_member_sees_a_booking_topic_with_title_type_and_unread_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events', 'read:bookings']);

        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        $booking->forceFill(['name' => 'Smith Wedding'])->save();

        $conversation = app(ConversationService::class)->topicFor($booking);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'Load in at 5pm',
        ]);

        $response = $this->actingAs($member)->getJson('/api/mobile/conversations')->assertOk();

        $rows = $this->topicRows($response->json('conversations'));
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame($conversation->id, $row['id']);
        $this->assertSame('Smith Wedding', $row['title']);
        $this->assertSame('booking', $row['topic_type']);
        $this->assertSame($band->id, $row['band_id']);
        $this->assertSame('Load in at 5pm', $row['last_message_preview']);
        $this->assertNotNull($row['last_message_at']);
        // Member has no participant row at all — the "count everything" bucket.
        $this->assertSame(1, $row['unread_count']);
    }

    public function test_event_topic_is_titled_from_the_event_title(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $event->forceFill(['title' => 'Saturday Night Set'])->save();

        $conversation = app(ConversationService::class)->topicFor($event);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'Set list?',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $rows = $this->topicRows($response->json('conversations'));
        $this->assertCount(1, $rows);
        $this->assertSame('Saturday Night Set', $rows[0]['title']);
        $this->assertSame('event', $rows[0]['topic_type']);
    }

    public function test_rehearsal_topic_is_titled_from_its_event_and_typed_rehearsal(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);
        $event->forceFill(['title' => 'Dress Rehearsal'])->save();

        // topicFor() canonicalizes an Events wrapping a Rehearsal to the Rehearsal.
        $conversation = app(ConversationService::class)->topicFor($event);
        $this->assertSame($rehearsal->id, (int) $conversation->conversable_id);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'Bring charts',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $rows = $this->topicRows($response->json('conversations'));
        $this->assertCount(1, $rows);
        $this->assertSame('Dress Rehearsal', $rows[0]['title']);
        $this->assertSame('rehearsal', $rows[0]['topic_type']);
    }

    public function test_empty_topic_thread_is_excluded(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        // Auto-created by opening the chat tab, but nobody ever posted.
        app(ConversationService::class)->topicFor($event);

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $this->assertSame([], $this->topicRows($response->json('conversations')));
    }

    public function test_topic_whose_only_message_is_soft_deleted_still_appears_with_null_preview(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $conversation = app(ConversationService::class)->topicFor($event);
        $message      = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'oops',
        ]);
        $message->delete();

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $rows = $this->topicRows($response->json('conversations'));
        $this->assertCount(1, $rows, 'a thread with only a trashed message still belongs in the list');
        $this->assertNull($rows[0]['last_message_preview']);
        $this->assertNotNull($rows[0]['last_message_at']);
    }

    public function test_topic_for_a_band_the_user_is_not_in_is_excluded(): void
    {
        [$owner] = $this->makeOwnerWithBand();

        $otherBand  = Bands::factory()->create();
        $otherOwner = \App\Models\User::factory()->create();
        $otherBand->owners()->create(['user_id' => $otherOwner->id]);
        $otherEvent = $this->makeBookingEvent($otherBand);

        $conversation = app(ConversationService::class)->topicFor($otherEvent);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $otherOwner->id,
            'body'            => 'private business',
        ]);

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $this->assertSame([], $this->topicRows($response->json('conversations')));
    }

    public function test_sub_sees_entitled_event_topic_but_not_booking_or_unentitled_topics(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event      = $this->makeBookingEvent($band);
        $otherEvent = $this->makeBookingEvent($band);
        $sub        = $this->makeSubAssignedTo($band, $event);

        $service = app(ConversationService::class);

        $entitled = $service->topicFor($event);
        Message::factory()->create([
            'conversation_id' => $entitled->id,
            'user_id'         => $owner->id,
            'body'            => 'call time 6',
        ]);

        $unentitled = $service->topicFor($otherEvent);
        Message::factory()->create([
            'conversation_id' => $unentitled->id,
            'user_id'         => $owner->id,
            'body'            => 'not your gig',
        ]);

        $bookingThread = $service->topicFor($event->eventable);
        Message::factory()->create([
            'conversation_id' => $bookingThread->id,
            'user_id'         => $owner->id,
            'body'            => 'contract talk',
        ]);

        $response = $this->actingAs($sub)->getJson('/api/mobile/conversations')->assertOk();

        $rows = $this->topicRows($response->json('conversations'));
        $this->assertCount(1, $rows, 'subs see only threads the policy admits');
        $this->assertSame($entitled->id, $rows[0]['id']);
        $this->assertSame('event', $rows[0]['topic_type']);
        $this->assertSame(1, $rows[0]['unread_count']);
    }

    public function test_dm_and_band_rows_carry_null_topic_type(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'hey']);

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        $conversations = collect($response->json('conversations'));
        $this->assertNull($conversations->firstWhere('type', 'band')['topic_type']);
        $this->assertNull($conversations->firstWhere('type', 'dm')['topic_type']);
    }

    public function test_topic_unread_respects_the_users_read_marker(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events', 'read:bookings']);

        $event        = $this->makeBookingEvent($band);
        $conversation = app(ConversationService::class)->topicFor($event);

        $first = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'first',
        ]);

        // Member opens the thread → participant row + read marker.
        $this->actingAs($member)
            ->getJson("/api/mobile/events/{$event->id}/conversation")->assertOk();

        $rows = $this->topicRows(
            $this->actingAs($member)->getJson('/api/mobile/conversations')
                ->assertOk()->json('conversations')
        );
        $this->assertSame(0, $rows[0]['unread_count'], 'everything read');

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'second',
            'created_at'      => $first->created_at->copy()->addMinutes(5),
        ]);

        $rows = $this->topicRows(
            $this->actingAs($member)->getJson('/api/mobile/conversations')->json('conversations')
        );
        $this->assertSame(1, $rows[0]['unread_count'], 'only the newer message counts');
    }

    public function test_topic_with_a_deleted_conversable_falls_back_to_thread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $conversation = app(ConversationService::class)->topicFor($event);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'orphaned',
        ]);
        $event->delete();

        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();

        // The policy denies a thread whose target is gone, so it drops out of
        // the list entirely — but the summarize() helpers must still be
        // null-safe, since they run on any conversation the app resolves.
        $this->assertSame([], $this->topicRows($response->json('conversations')));

        $row = $this->summarizeFresh($conversation, $owner);
        $this->assertSame('Thread', $row['title']);
        $this->assertNull(
            $row['topic_type'],
            'a topic with no surviving item reports no type, matching the Thread title',
        );
    }

    /** Invoke the controller's private summarize() on a freshly loaded row. */
    private function summarizeFresh(\App\Models\Conversation $conversation, \App\Models\User $user): array
    {
        $controller = app(\App\Http\Controllers\Api\Mobile\ConversationsController::class);

        $summarize = new \ReflectionMethod($controller, 'summarize');
        $summarize->setAccessible(true);

        $prefetch = new \ReflectionMethod($controller, 'prefetchSummaryData');
        $prefetch->setAccessible(true);

        $ids = collect([$conversation->id]);

        return $summarize->invoke(
            $controller,
            $conversation->fresh(),
            $user,
            $prefetch->invoke($controller, $ids, $user, collect()),
        );
    }

    public function test_listing_many_topics_does_not_n_plus_one(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $service = app(ConversationService::class);

        for ($i = 0; $i < 6; $i++) {
            [$rehearsal] = $this->makeRehearsalEvent($band);
            $booking     = $this->makeBookingEvent($band)->eventable;

            foreach ([$service->topicFor($rehearsal), $service->topicFor($booking)] as $conversation) {
                Message::factory()->create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $owner->id,
                    'body'            => 'hi',
                ]);
            }
        }

        \DB::enableQueryLog();
        $response = $this->actingAs($owner)->getJson('/api/mobile/conversations')->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertCount(12, $this->topicRows($response->json('conversations')));
        $this->assertLessThan(
            40,
            $queries,
            "conversations index ran {$queries} queries for 12 topics — conversables must stay eager-loaded",
        );
    }

    public function test_delivered_ack_stamps_topic_participant_rows(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events', 'read:bookings']);

        $event        = $this->makeBookingEvent($band);
        $conversation = app(ConversationService::class)->topicFor($event);

        // Member registers as a participant by opening the thread.
        $this->actingAs($member)
            ->getJson("/api/mobile/events/{$event->id}/conversation")->assertOk();

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'body'            => 'anyone there?',
        ]);

        $this->actingAs($member)->postJson('/api/mobile/conversations/delivered')->assertNoContent();

        $this->assertNotNull(
            \App\Models\ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $member->id)->first()->last_delivered_at,
            'delivered ack must stamp topic participant rows',
        );
    }
}
