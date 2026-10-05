<?php

namespace Tests\Feature\Web\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class DashboardUnreadCommentTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_dashboard_rows_carry_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $busy   = $this->makeBookingEvent($band);
        $quiet  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($busy);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'a']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'b']);

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();
        $events = collect($response->viewData('page')['props']['events']);

        $this->assertSame(2, $events->firstWhere('id', $busy->id)['unread_comment_count']);
        $this->assertSame(0, $events->firstWhere('id', $quiet->id)['unread_comment_count']);
    }

    public function test_load_older_events_rows_carry_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $past   = $this->makeBookingEvent($band);
        $past->forceFill(['date' => now()->subDays(10)->format('Y-m-d')])->save();
        $topic = app(ConversationService::class)->topicFor($past);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'thanks all']);

        $response = $this->actingAs($owner)
            ->getJson('/dashboard/load-older-events?before_date=' . now()->subDays(1)->toDateString())
            ->assertOk();

        $row = collect($response->json('events'))->firstWhere('id', $past->id);
        $this->assertNotNull($row, 'older event should be in the page');
        $this->assertSame(1, $row['unread_comment_count']);
    }
}
