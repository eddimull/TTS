<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\BandPayoutConfig;
use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPayoutTest extends TestCase
{
    use RefreshDatabase;

    private function setup_booking(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);
        $config = BandPayoutConfig::factory()->create([
            'band_id' => $band->id, 'is_active' => true,
            'band_cut_type' => 'percentage', 'band_cut_value' => 20,
        ]);
        $booking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 1000]);
        Events::factory()->create(['eventable_id' => $booking->id, 'eventable_type' => Bookings::class, 'value' => 1000]);
        $token = $user->createToken('test-device')->plainTextToken;
        return compact('user', 'band', 'booking', 'config', 'token');
    }

    private function headers(string $token, int $bandId): array
    {
        return ['Authorization' => "Bearer {$token}", 'X-Band-ID' => $bandId, 'Accept' => 'application/json'];
    }

    public function test_payout_show_returns_breakdown_structure(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        $response = $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout");

        $response->assertOk()->assertJsonStructure([
            'payout' => ['id', 'base_amount', 'adjusted_amount', 'payout_config_id'],
            'config' => ['id', 'name', 'is_active'],
            'result' => ['total_amount', 'band_cut', 'distributable_amount', 'member_payouts', 'payment_group_payouts'],
            'adjustments',
            'events' => [['id', 'label', 'value', 'members']],
            'available_configs' => [['id', 'name', 'is_active']],
        ]);

        $response->assertJsonPath('config.is_active', true);
        // 20% of 1000 = 200; PHP returns an int here, assertJsonPath uses ===
        $response->assertJsonPath('result.band_cut', 200);
    }

    public function test_payout_show_forbidden_for_non_member(): void
    {
        ['band' => $band, 'booking' => $booking] = $this->setup_booking();
        $outsider = User::factory()->create();
        $token = $outsider->createToken('d')->plainTextToken;

        $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout")
            ->assertForbidden();
    }

    public function test_payout_show_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking] = $this->setup_booking();

        // Create a sub: a user who appears in band_subs but NOT in band_owners/band_members
        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);
        $token = $sub->createToken('sub-device')->plainTextToken;

        $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout")
            ->assertForbidden();
    }

    public function test_store_adjustment_recalculates_adjusted_amount(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        $response = $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount' => -250, 'description' => 'Gas / travel', 'notes' => 'Reimbursed',
            ]);

        $response->assertCreated()->assertJsonStructure(['adjustment' => ['id', 'amount', 'description', 'notes']]);
        $this->assertSame('750.00', (string) $booking->fresh()->payout->adjusted_amount);
    }

    public function test_store_adjustment_validates_description_required(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", ['amount' => 10])
            ->assertStatus(422);
    }

    public function test_destroy_adjustment_recalculates(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", ['amount' => -250, 'description' => 'X']);
        $adjId = $booking->fresh()->payout->adjustments->first()->id;

        $this->withHeaders($this->headers($token, $band->id))
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments/{$adjId}")
            ->assertOk();
        $this->assertSame('1000.00', (string) $booking->fresh()->payout->adjusted_amount);
    }

    public function test_destroy_adjustment_rejects_foreign_adjustment(): void
    {
        // booking1 belongs to the same band as booking2.  The band-membership
        // middleware passes for both routes (same user, same band).  The 403
        // must come exclusively from the payout-ownership guard:
        //   abort_unless($adjustment->payout_id === $payout->id, 403, …)
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        // Create a second booking for the SAME band and same owner.
        $booking2 = Bookings::factory()->create(['band_id' => $band->id, 'price' => 500]);
        Events::factory()->create([
            'eventable_id'   => $booking2->id,
            'eventable_type' => Bookings::class,
            'value'          => 500,
        ]);

        // Add an adjustment to booking #1's payout.
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount'      => -100,
                'description' => 'Booking1 adjustment',
            ]);

        $adjId = $booking->fresh()->payout->adjustments->first()->id;

        // Ensure booking #2 has a payout record so the cross-ownership guard is
        // reached (not the earlier 404 "payout not found" guard).
        $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking2->id}/payout")
            ->assertOk();

        // Attempt to delete booking #1's adjustment via booking #2's URL — must be 403.
        $this->withHeaders($this->headers($token, $band->id))
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking2->id}/payout/adjustments/{$adjId}")
            ->assertForbidden();

        // Adjustment must still exist in the DB.
        $this->assertDatabaseHas('payout_adjustments', ['id' => $adjId]);
    }

    public function test_store_adjustment_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking] = $this->setup_booking();

        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);
        $token = $sub->createToken('sub-device')->plainTextToken;

        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount' => -100, 'description' => 'Should be blocked',
            ])
            ->assertForbidden();
    }

    public function test_destroy_adjustment_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        // Owner creates an adjustment first
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount' => -100, 'description' => 'Owner adjustment',
            ]);
        $adjId = $booking->fresh()->payout->adjustments->first()->id;

        // Sub attempts to delete it — use actingAs to avoid Sanctum token-cache
        // interference from the preceding owner request in this same test.
        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);

        $this->actingAs($sub, 'sanctum')
            ->withHeaders(['X-Band-ID' => $band->id, 'Accept' => 'application/json'])
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments/{$adjId}")
            ->assertForbidden();
    }

    public function test_update_configuration_switches_and_returns_result(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $other = \App\Models\BandPayoutConfig::factory()->create([
            'band_id' => $band->id, 'is_active' => false,
            'band_cut_type' => 'percentage', 'band_cut_value' => 50,
        ]);

        $response = $this->withHeaders($this->headers($token, $band->id))
            ->putJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/configuration", [
                'payout_config_id' => $other->id,
            ]);

        $response->assertOk()->assertJsonStructure(['result' => ['band_cut', 'distributable_amount']]);
        $this->assertEquals($other->id, $booking->fresh()->payout->payout_config_id);
        $this->assertEqualsWithDelta(500.0, $response->json('result.band_cut'), 0.01);

        // Assert calculation_result is persisted to the DB and reflects the switched config.
        $persistedResult = $booking->fresh()->payout->calculation_result;
        $this->assertIsArray($persistedResult);
        $this->assertEqualsWithDelta(500.0, $persistedResult['band_cut'], 0.01);
    }

    public function test_update_configuration_rejects_config_from_other_band(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $foreign = \App\Models\BandPayoutConfig::factory()->create(['band_id' => Bands::factory()->create()->id]);

        $this->withHeaders($this->headers($token, $band->id))
            ->putJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/configuration", ['payout_config_id' => $foreign->id])
            ->assertStatus(404);
    }

    public function test_update_configuration_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        // Owner first switches config to ensure a payout record exists.
        $config = \App\Models\BandPayoutConfig::factory()->create([
            'band_id' => $band->id, 'is_active' => false,
            'band_cut_type' => 'percentage', 'band_cut_value' => 10,
        ]);

        $this->withHeaders($this->headers($token, $band->id))
            ->putJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/configuration", [
                'payout_config_id' => $config->id,
            ])
            ->assertOk();

        // Sub attempts the same — use actingAs to avoid Sanctum token-cache
        // interference from the preceding owner request in this same test.
        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);

        $this->actingAs($sub, 'sanctum')
            ->withHeaders(['X-Band-ID' => $band->id, 'Accept' => 'application/json'])
            ->putJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/configuration", [
                'payout_config_id' => $config->id,
            ])
            ->assertForbidden();
    }

    public function test_update_attendance_sets_status(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $event = $booking->fresh()->events->first();
        $member = \App\Models\EventMember::create([
            'event_id' => $event->id, 'band_id' => $band->id,
            'name' => 'Bob', 'attendance_status' => 'confirmed',
        ]);

        $response = $this->withHeaders($this->headers($token, $band->id))
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$event->id}/members/{$member->id}/attendance", [
                'attendance_status' => 'absent',
            ]);

        $response->assertOk()->assertJsonPath('member.attendance_status', 'absent');
        $this->assertSame('absent', $member->fresh()->attendance_status);
    }

    public function test_update_attendance_validates_status_enum(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $event = $booking->fresh()->events->first();
        $member = \App\Models\EventMember::create([
            'event_id' => $event->id, 'band_id' => $band->id, 'name' => 'Bob',
            'attendance_status' => 'confirmed',
        ]);

        $this->withHeaders($this->headers($token, $band->id))
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$event->id}/members/{$member->id}/attendance", ['attendance_status' => 'playing'])
            ->assertStatus(422);
    }

    public function test_update_attendance_member_from_other_event_404(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $event = $booking->fresh()->events->first();

        // Create a second event and an EventMember on it.
        $otherEvent = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'value' => 0,
        ]);
        $otherMember = \App\Models\EventMember::create([
            'event_id' => $otherEvent->id, 'band_id' => $band->id,
            'name' => 'Alice', 'attendance_status' => 'confirmed',
        ]);

        // PATCH via the FIRST event's URL with the member belonging to otherEvent → 404.
        $this->withHeaders($this->headers($token, $band->id))
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$event->id}/members/{$otherMember->id}/attendance", [
                'attendance_status' => 'absent',
            ])
            ->assertNotFound();
    }

    public function test_update_attendance_rejects_event_from_other_booking(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        // Create a second booking in the SAME band with its own event + member.
        $booking2 = Bookings::factory()->create(['band_id' => $band->id, 'price' => 500]);
        $event2 = Events::factory()->create([
            'eventable_id'   => $booking2->id,
            'eventable_type' => Bookings::class,
            'value'          => 500,
        ]);
        $member2 = \App\Models\EventMember::create([
            'event_id' => $event2->id, 'band_id' => $band->id,
            'name' => 'Alice', 'attendance_status' => 'confirmed',
        ]);

        // PATCH attendance via booking #1's URL but with booking #2's event and member → 404.
        $this->withHeaders($this->headers($token, $band->id))
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$event2->id}/members/{$member2->id}/attendance", [
                'attendance_status' => 'absent',
            ])
            ->assertNotFound();
    }

    public function test_update_attendance_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking] = $this->setup_booking();
        $event = $booking->fresh()->events->first();
        $member = \App\Models\EventMember::create([
            'event_id' => $event->id, 'band_id' => $band->id,
            'name' => 'Bob', 'attendance_status' => 'confirmed',
        ]);

        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);

        $this->actingAs($sub, 'sanctum')
            ->withHeaders(['X-Band-ID' => $band->id, 'Accept' => 'application/json'])
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$event->id}/members/{$member->id}/attendance", [
                'attendance_status' => 'absent',
            ])
            ->assertForbidden();
    }

    // ── C1: cross-band booking IDOR rejection ──────────────────────────
    // The four write endpoints sit in a route group WITHOUT scopeBindings(),
    // so {booking} resolves by id alone. An attacker who owns band A could pass
    // a DIFFERENT band's booking id and mutate its payout/attendance. Each test
    // authenticates as band A's owner, uses band A in the URL but band B's
    // booking id, and asserts the booking→band guard rejects it (404) with no
    // mutation on band B's data.

    public function test_store_adjustment_rejects_cross_band_booking(): void
    {
        // Band A — the authenticated attacker's own band.
        ['band' => $bandA, 'token' => $token] = $this->setup_booking();
        // Band B — a separate band with its own owner + booking.
        ['band' => $bandB, 'booking' => $bookingB] = $this->setup_booking();

        // Attacker (band A owner) targets band B's booking via band A's URL.
        $this->withHeaders($this->headers($token, $bandA->id))
            ->postJson("/api/mobile/bands/{$bandA->id}/bookings/{$bookingB->id}/payout/adjustments", [
                'amount' => -250, 'description' => 'Cross-band attack',
            ])
            ->assertNotFound();

        // No adjustment may have been created against band B's payout.
        $payoutB = $bookingB->fresh()->payout;
        $this->assertTrue($payoutB === null || $payoutB->adjustments()->count() === 0);
        $this->assertDatabaseMissing('payout_adjustments', ['description' => 'Cross-band attack']);
    }

    public function test_destroy_adjustment_rejects_cross_band_booking(): void
    {
        ['band' => $bandA, 'token' => $token] = $this->setup_booking();
        ['band' => $bandB, 'booking' => $bookingB, 'user' => $ownerB] = $this->setup_booking();

        // Band B's booking has a payout + adjustment (created directly to keep this
        // test on a single authenticated HTTP call — the attacker's).
        $payoutB = $bookingB->payout()->create([
            'band_id' => $bandB->id, 'base_amount' => 500, 'adjusted_amount' => 500,
        ]);
        $adjId = $payoutB->adjustments()->create([
            'amount' => -100, 'description' => 'BandB adjustment', 'created_by' => $ownerB->id,
        ])->id;

        // Attacker (band A owner) tries to delete it via band A's URL + band B's booking.
        $this->withHeaders($this->headers($token, $bandA->id))
            ->deleteJson("/api/mobile/bands/{$bandA->id}/bookings/{$bookingB->id}/payout/adjustments/{$adjId}")
            ->assertNotFound();

        // Band B's adjustment must still exist.
        $this->assertDatabaseHas('payout_adjustments', ['id' => $adjId]);
    }

    public function test_update_configuration_rejects_cross_band_booking(): void
    {
        ['band' => $bandA, 'token' => $token] = $this->setup_booking();
        ['band' => $bandB, 'booking' => $bookingB, 'config' => $configB] = $this->setup_booking();

        $this->withHeaders($this->headers($token, $bandA->id))
            ->putJson("/api/mobile/bands/{$bandA->id}/bookings/{$bookingB->id}/payout/configuration", [
                'payout_config_id' => $configB->id,
            ])
            ->assertNotFound();

        // Band B's booking must have no payout config set by the attacker.
        $payoutB = $bookingB->fresh()->payout;
        $this->assertTrue($payoutB === null || $payoutB->payout_config_id === null);
    }

    public function test_update_attendance_rejects_cross_band_booking(): void
    {
        ['band' => $bandA, 'token' => $token] = $this->setup_booking();
        ['band' => $bandB, 'booking' => $bookingB] = $this->setup_booking();

        // Band B's own event + member.
        $eventB = $bookingB->fresh()->events->first();
        $memberB = \App\Models\EventMember::create([
            'event_id' => $eventB->id, 'band_id' => $bandB->id,
            'name' => 'Bob', 'attendance_status' => 'confirmed',
        ]);

        // Attacker (band A owner) targets band B's booking/event/member.
        $this->withHeaders($this->headers($token, $bandA->id))
            ->patchJson("/api/mobile/bands/{$bandA->id}/bookings/{$bookingB->id}/events/{$eventB->id}/members/{$memberB->id}/attendance", [
                'attendance_status' => 'absent',
            ])
            ->assertNotFound();

        // Band B's member attendance must be unchanged.
        $this->assertSame('confirmed', $memberB->fresh()->attendance_status);
    }
}
