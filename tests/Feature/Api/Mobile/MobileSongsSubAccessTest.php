<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventSubs;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product decision: a sub may read a band's song list only while they're
 * scheduled on one of that band's events (accepted invitation or sub slot),
 * including a 48-hour grace period after the event date. Mirrors the
 * assignedChartIdsForBand()/hasCurrentSubAssignmentForBand() windowing
 * already used for charts, applied to songs on both mobile and web.
 */
class MobileSongsSubAccessTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
    }

    private function makeSub(): User
    {
        $sub = User::factory()->create();

        BandSubs::firstOrCreate(['user_id' => $sub->id, 'band_id' => $this->band->id]);

        return $sub;
    }

    private function makeBookingEvent(string $date): Events
    {
        $eventType = EventTypes::factory()->create();
        $booking   = Bookings::factory()->create(['band_id' => $this->band->id]);

        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => $eventType->id,
            'date'           => $date,
        ]);
    }

    private function assignViaAcceptedInvitation(User $sub, Events $event): void
    {
        EventSubs::create([
            'event_id'        => $event->id,
            'band_id'         => $this->band->id,
            'user_id'         => $sub->id,
            'invitation_key'  => \Illuminate\Support\Str::random(36),
            'pending'         => false,
            'accepted_at'     => now(),
        ]);
    }

    private function assignViaPendingInvitation(User $sub, Events $event): void
    {
        EventSubs::create([
            'event_id'        => $event->id,
            'band_id'         => $this->band->id,
            'user_id'         => $sub->id,
            'invitation_key'  => \Illuminate\Support\Str::random(36),
            'pending'         => true,
        ]);
    }

    private function mobileHeaders(User $user): array
    {
        $token = $user->createToken('test-device', ['mobile', 'read:songs'])->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $this->band->id,
            'Accept'        => 'application/json',
        ];
    }

    // -------------------------------------------------------------------------
    // mobile
    // -------------------------------------------------------------------------

    public function test_sub_with_upcoming_event_can_read_songs(): void
    {
        $sub = $this->makeSub();
        $event = $this->makeBookingEvent(now()->addDays(3)->format('Y-m-d'));
        $this->assignViaAcceptedInvitation($sub, $event);

        $this->withHeaders($this->mobileHeaders($sub))
            ->getJson("/api/mobile/bands/{$this->band->id}/songs")
            ->assertOk();
    }

    public function test_sub_with_recently_ended_event_can_read_songs(): void
    {
        $sub = $this->makeSub();
        // Yesterday — inside the 48h grace window and timezone-robust (avoids a
        // "24 hours ago" instant landing on the wrong side of midnight when
        // `date` is compared as a plain date).
        $event = $this->makeBookingEvent(now()->subDay()->format('Y-m-d'));
        $this->assignViaAcceptedInvitation($sub, $event);

        $this->withHeaders($this->mobileHeaders($sub))
            ->getJson("/api/mobile/bands/{$this->band->id}/songs")
            ->assertOk();
    }

    public function test_sub_with_event_two_days_ago_is_still_within_grace(): void
    {
        $sub = $this->makeSub();
        // Two days ago — cutoff is now()->subHours(48), so its date equals
        // now()->subHours(48)->format('Y-m-d'), still passing >= check.
        // Boundary test: proves the grace window is date-deterministic (before
        // the fix, this could flap by time of day with a timestamp comparison).
        $event = $this->makeBookingEvent(now()->subDays(2)->format('Y-m-d'));
        $this->assignViaAcceptedInvitation($sub, $event);

        $this->withHeaders($this->mobileHeaders($sub))
            ->getJson("/api/mobile/bands/{$this->band->id}/songs")
            ->assertOk();
    }

    public function test_sub_with_only_old_event_cannot_read_songs(): void
    {
        $sub = $this->makeSub();
        $event = $this->makeBookingEvent(now()->subDays(5)->format('Y-m-d'));
        $this->assignViaAcceptedInvitation($sub, $event);

        $this->withHeaders($this->mobileHeaders($sub))
            ->getJson("/api/mobile/bands/{$this->band->id}/songs")
            ->assertForbidden();
    }

    public function test_sub_with_pending_invitation_cannot_read_songs(): void
    {
        $sub = $this->makeSub();
        $event = $this->makeBookingEvent(now()->addDays(3)->format('Y-m-d'));
        $this->assignViaPendingInvitation($sub, $event);

        $this->withHeaders($this->mobileHeaders($sub))
            ->getJson("/api/mobile/bands/{$this->band->id}/songs")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // web
    // -------------------------------------------------------------------------

    public function test_web_songs_index_applies_same_window(): void
    {
        $scheduledSub = $this->makeSub();
        $upcoming = $this->makeBookingEvent(now()->addDays(3)->format('Y-m-d'));
        $this->assignViaAcceptedInvitation($scheduledSub, $upcoming);

        $this->actingAs($scheduledSub)
            ->get("/songs?band_id={$this->band->id}")
            ->assertOk();

        $staleSub = $this->makeSub();
        $old = $this->makeBookingEvent(now()->subDays(5)->format('Y-m-d'));
        $this->assignViaAcceptedInvitation($staleSub, $old);

        $this->actingAs($staleSub)
            ->get("/songs?band_id={$this->band->id}")
            ->assertForbidden();
    }
}
