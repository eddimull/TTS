<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\EventMember;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class DashboardUnreadCommentTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    private User $user;

    private Bands $band;

    private $event;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->user, $this->band] = $this->makeOwnerWithBand();
        $this->event = $this->makeBookingEvent($this->band);
    }

    public function test_dashboard_events_include_unread_comment_count(): void
    {
        $author = User::factory()->create();
        $conversation = app(ConversationService::class)->topicFor($this->event);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'load in at 5?',
        ]);

        $token = $this->user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();
        $row = collect($response->json('events'))
            ->firstWhere('id', $this->event->id);
        $this->assertSame(1, $row['unread_comment_count']);
    }

    public function test_events_without_a_conversation_report_zero(): void
    {
        $token = $this->user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();
        $row = collect($response->json('events'))
            ->firstWhere('id', $this->event->id);
        $this->assertSame(0, $row['unread_comment_count']);
    }

    public function test_read_threads_report_zero(): void
    {
        $author = User::factory()->create();
        $conversation = app(ConversationService::class)->topicFor($this->event);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'load in at 5?',
        ]);
        $conversation->participants()->create([
            'user_id' => $this->user->id,
            'last_read_at' => now(),
        ]);

        $token = $this->user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();
        $row = collect($response->json('events'))
            ->firstWhere('id', $this->event->id);
        $this->assertSame(0, $row['unread_comment_count']);
    }

    /**
     * Regression for the canonicalization rule (ConversationService::canonicalTarget):
     * a rehearsal-backed Events row's topic conversation is keyed by
     * App\Models\Rehearsal + rehearsals.id, NOT Events::class. The dashboard
     * must look up unread counts using that same canonical key, or a message
     * posted from the rehearsal screen would never show as unread here.
     */
    public function test_rehearsal_backed_event_reports_unread_via_the_canonical_rehearsal_conversation(): void
    {
        // A Rehearsal needs a rehearsal_schedule_id for UserEventsService's
        // rehearsalQuery (inner-joins rehearsal_schedules) to surface it —
        // mirrors DashboardTest::test_dashboard_rehearsal_id_resolves_against_the_detail_route.
        $schedule = \App\Models\RehearsalSchedule::factory()->weekly()->create(['band_id' => $this->band->id]);
        $rehearsal = \App\Models\Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $this->band->id,
        ]);
        $rehearsalEvent = \App\Models\Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => \App\Models\EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        $author = User::factory()->create();
        // topicFor() canonicalizes internally: passing the Events row still
        // resolves to the Rehearsal's conversation.
        $conversation = app(ConversationService::class)->topicFor($rehearsalEvent);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'starting 10 min late',
        ]);

        $token = $this->user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();
        $row = collect($response->json('events'))
            ->firstWhere('event_source', 'rehearsal');

        $this->assertNotNull($row, 'expected the rehearsal in the dashboard payload');
        $this->assertSame($rehearsal->id, $row['id'], 'dashboard rehearsal id must be rehearsals.id');
        $this->assertSame(1, $row['unread_comment_count']);
    }

    /**
     * Regression (Copilot review, PR #527): a sub-only user's events come back
     * from UserEventsService::getSubEvents() as raw Eloquent `Events` models,
     * never converted to arrays before DashboardController hands the
     * collection to DashboardFormatter::conversablePairs(). That method used
     * to cast each row with `(array) $e`, which — for an Eloquent model —
     * yields PHP's internal property-storage keys (e.g. "\0*\0attributes")
     * instead of attribute names, so conversableFor() always saw a null `id`
     * and every sub-only dashboard row silently reported
     * unread_comment_count = 0 even with unread messages.
     */
    public function test_sub_only_user_sees_unread_comment_count_on_dashboard(): void
    {
        setPermissionsTeamId(0);
        Role::firstOrCreate(['name' => 'sub', 'guard_name' => 'web']);

        $sub = User::factory()->create();
        $sub->assignRole('sub');
        BandSubs::firstOrCreate(['user_id' => $sub->id, 'band_id' => $this->band->id]);

        EventMember::create([
            'event_id'         => $this->event->id,
            'band_id'          => $this->band->id,
            'user_id'          => $sub->id,
            'roster_member_id' => null,
            'name'             => $sub->name,
        ]);

        $author = User::factory()->create();
        $conversation = app(ConversationService::class)->topicFor($this->event);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'body' => 'load in at 5?',
        ]);

        $token = $sub->createToken('test-device')->plainTextToken;

        // Simulate a fresh request: no permissions team set, exactly as the
        // mobile DashboardController leaves it (and as UserEventsService
        // requires to correctly resolve hasRole('sub')).
        setPermissionsTeamId(0);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();
        $row = collect($response->json('events'))
            ->firstWhere('id', $this->event->id);

        $this->assertNotNull($row, 'expected the sub-assigned event in the dashboard payload');
        $this->assertSame(1, $row['unread_comment_count'], 'sub-only user must see the unread comment count, not silently 0');
    }
}
