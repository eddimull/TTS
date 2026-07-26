<?php

namespace Tests\Feature\Api\Mobile;

use App\Jobs\ProcessRehearsalCancelled;
use App\Models\Bands;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RehearsalsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUserWithBandAndRehearsal(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $schedule = RehearsalSchedule::factory()->weekly()->create(['band_id' => $band->id]);

        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);

        $eventType = EventTypes::factory()->create();

        // Attach an upcoming event to the rehearsal
        $event = Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'schedule', 'rehearsal', 'event', 'token');
    }

    // -------------------------------------------------------------------------
    // rehearsals.schedules
    // -------------------------------------------------------------------------

    public function test_rehearsal_schedules_requires_authentication(): void
    {
        $band = Bands::factory()->create();

        $this->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules")
            ->assertUnauthorized();
    }

    public function test_rehearsal_schedules_returns_schedules_for_band(): void
    {
        [
            'band'     => $band,
            'schedule' => $schedule,
            'token'    => $token,
        ] = $this->createUserWithBandAndRehearsal();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules");

        $response->assertOk()
            ->assertJsonStructure([
                'schedules' => [
                    '*' => [
                        'id', 'name', 'description', 'frequency',
                        'location_name', 'location_address', 'active',
                        'upcoming_rehearsals',
                    ],
                ],
            ]);

        $ids = collect($response->json('schedules'))->pluck('id');
        $this->assertTrue($ids->contains($schedule->id));
    }

    public function test_rehearsal_schedules_includes_upcoming_rehearsals(): void
    {
        [
            'band'      => $band,
            'schedule'  => $schedule,
            'rehearsal' => $rehearsal,
            'token'     => $token,
        ] = $this->createUserWithBandAndRehearsal();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules");

        $response->assertOk();

        $scheduleData = collect($response->json('schedules'))->firstWhere('id', $schedule->id);
        $this->assertNotNull($scheduleData);

        $upcomingIds = collect($scheduleData['upcoming_rehearsals'])->pluck('id');
        $this->assertTrue($upcomingIds->contains($rehearsal->id));

        // Verify structure of an upcoming rehearsal
        $rehearsalData = collect($scheduleData['upcoming_rehearsals'])->firstWhere('id', $rehearsal->id);
        $this->assertArrayHasKey('date', $rehearsalData);
        $this->assertArrayHasKey('time', $rehearsalData);
        $this->assertArrayHasKey('event_key', $rehearsalData);
        $this->assertArrayHasKey('is_cancelled', $rehearsalData);
    }

    // -------------------------------------------------------------------------
    // rehearsals.show
    // -------------------------------------------------------------------------

    public function test_rehearsal_show_returns_rehearsal_detail(): void
    {
        [
            'rehearsal' => $rehearsal,
            'event'     => $event,
            'schedule'  => $schedule,
            'token'     => $token,
        ] = $this->createUserWithBandAndRehearsal();

        $response = $this->withToken($token)
            ->getJson("/api/mobile/rehearsals/{$rehearsal->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'rehearsal' => [
                    'id', 'date', 'time', 'venue_name', 'venue_address',
                    'is_cancelled', 'notes', 'event_key',
                    'schedule' => ['id', 'name', 'location_name'],
                    'associated_bookings',
                ],
            ]);

        $this->assertEquals($rehearsal->id, $response->json('rehearsal.id'));
        $this->assertEquals($event->key, $response->json('rehearsal.event_key'));
        $this->assertEquals($schedule->id, $response->json('rehearsal.schedule.id'));
    }

    public function test_rehearsal_show_returns_403_for_user_without_access(): void
    {
        ['rehearsal' => $rehearsal] = $this->createUserWithBandAndRehearsal();

        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('test-device')->plainTextToken;

        $this->withToken($otherToken)
            ->getJson("/api/mobile/rehearsals/{$rehearsal->id}")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // rehearsals.set-cancelled
    // -------------------------------------------------------------------------

    public function test_set_cancelled_cancels_a_rehearsal_and_dispatches_fanout(): void
    {
        Queue::fake();
        ['rehearsal' => $rehearsal, 'token' => $token] = $this->createUserWithBandAndRehearsal();

        $response = $this->withToken($token)
            ->patchJson("/api/mobile/rehearsals/{$rehearsal->id}/cancelled", ['is_cancelled' => true]);

        $response->assertOk();
        $this->assertTrue($response->json('rehearsal.is_cancelled'));
        $this->assertTrue($rehearsal->fresh()->is_cancelled);
        Queue::assertPushed(ProcessRehearsalCancelled::class, 1);
    }

    public function test_set_cancelled_restores_a_cancelled_rehearsal(): void
    {
        Queue::fake();
        ['rehearsal' => $rehearsal, 'token' => $token] = $this->createUserWithBandAndRehearsal();
        $rehearsal->update(['is_cancelled' => true]);

        $response = $this->withToken($token)
            ->patchJson("/api/mobile/rehearsals/{$rehearsal->id}/cancelled", ['is_cancelled' => false]);

        $response->assertOk();
        $this->assertFalse($response->json('rehearsal.is_cancelled'));
        $this->assertFalse($rehearsal->fresh()->is_cancelled);
        Queue::assertPushed(ProcessRehearsalCancelled::class, 1);
    }

    public function test_set_cancelled_is_idempotent_and_skips_fanout_when_unchanged(): void
    {
        Queue::fake();
        ['rehearsal' => $rehearsal, 'token' => $token] = $this->createUserWithBandAndRehearsal();
        $rehearsal->update(['is_cancelled' => true]);

        $this->withToken($token)
            ->patchJson("/api/mobile/rehearsals/{$rehearsal->id}/cancelled", ['is_cancelled' => true])
            ->assertOk();

        Queue::assertNotPushed(ProcessRehearsalCancelled::class);
    }

    public function test_set_cancelled_requires_boolean_body(): void
    {
        ['rehearsal' => $rehearsal, 'token' => $token] = $this->createUserWithBandAndRehearsal();

        $this->withToken($token)
            ->patchJson("/api/mobile/rehearsals/{$rehearsal->id}/cancelled", [])
            ->assertStatus(422);
    }

    public function test_set_cancelled_returns_403_for_user_without_access(): void
    {
        ['rehearsal' => $rehearsal] = $this->createUserWithBandAndRehearsal();
        $otherToken = User::factory()->create()->createToken('test-device')->plainTextToken;

        $this->withToken($otherToken)
            ->patchJson("/api/mobile/rehearsals/{$rehearsal->id}/cancelled", ['is_cancelled' => true])
            ->assertStatus(403);
    }

    public function test_set_cancelled_requires_authentication(): void
    {
        ['rehearsal' => $rehearsal] = $this->createUserWithBandAndRehearsal();

        $this->patchJson("/api/mobile/rehearsals/{$rehearsal->id}/cancelled", ['is_cancelled' => true])
            ->assertUnauthorized();
    }

    public function test_schedules_includes_recurrence_label(): void
    {
        ['band' => $band, 'token' => $token] = $this->createUserWithBandAndRehearsal();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules");

        $response->assertOk();
        // Factory weekly() state: day_of_week=wednesday, default_time=19:00:00.
        $this->assertSame(
            'Every Wednesday at 7:00 PM',
            $response->json('schedules.0.recurrence_label')
        );
    }

    public function test_schedules_default_response_has_no_virtuals(): void
    {
        ['band' => $band, 'token' => $token] = $this->createUserWithBandAndRehearsal();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules");

        $response->assertOk();
        foreach ($response->json('schedules.0.upcoming_rehearsals') as $item) {
            $this->assertArrayNotHasKey('is_virtual', $item,
                'default response must stay byte-compatible for old clients');
            $this->assertNotNull($item['id']);
        }
    }

    public function test_schedules_include_virtual_merges_virtual_occurrences(): void
    {
        [
            'band'      => $band,
            'schedule'  => $schedule,
            'rehearsal' => $rehearsal,
            'event'     => $event,
            'token'     => $token,
        ] = $this->createUserWithBandAndRehearsal();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules?include_virtual=1");

        $response->assertOk();
        $upcoming = collect($response->json('schedules.0.upcoming_rehearsals'));

        // The materialized rehearsal is present exactly once, flagged real.
        $real = $upcoming->where('id', $rehearsal->id);
        $this->assertCount(1, $real);
        $this->assertFalse($real->first()['is_virtual']);

        // Virtual occurrences exist, have null ids and parseable keys.
        $virtuals = $upcoming->where('is_virtual', true);
        $this->assertGreaterThanOrEqual(6, $virtuals->count(),
            'weekly schedule over 60 days should yield ~8 virtual occurrences');
        $virtuals->each(function ($v) use ($schedule) {
            $this->assertNull($v['id']);
            $this->assertStringStartsWith("virtual-rehearsal-{$schedule->id}-", $v['event_key']);
        });

        // No virtual duplicates the materialized rehearsal's date.
        $materializedDate = $event->date instanceof \Carbon\Carbon
            ? $event->date->toDateString()
            : \Carbon\Carbon::parse($event->date)->toDateString();
        $this->assertCount(0, $virtuals->where('date', $materializedDate));

        // Sorted ascending by date.
        $dates = $upcoming->pluck('date')->all();
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_schedules_until_extends_the_window(): void
    {
        ['band' => $band, 'token' => $token] = $this->createUserWithBandAndRehearsal();

        $until = now()->addDays(180)->toDateString();
        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules?include_virtual=1&until={$until}");

        $response->assertOk();
        $virtuals = collect($response->json('schedules.0.upcoming_rehearsals'))
            ->where('is_virtual', true);

        $beyondSixty = $virtuals->filter(
            fn ($v) => $v['date'] > now()->addDays(60)->toDateString()
        );
        $this->assertGreaterThanOrEqual(10, $beyondSixty->count(),
            'weekly virtuals must extend past the default 60-day cutoff');
        $virtuals->each(fn ($v) => $this->assertLessThanOrEqual($until, $v['date']));
    }

    public function test_schedules_inactive_schedule_gets_no_virtuals(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        RehearsalSchedule::factory()->weekly()->inactive()->create(['band_id' => $band->id]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules?include_virtual=1");

        $response->assertOk();
        $this->assertSame([], $response->json('schedules.0.upcoming_rehearsals'));
        $this->assertNotNull($response->json('schedules.0.recurrence_label'));
    }
}
