<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'time'           => '19:00:00',
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
}
