<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Events;
use App\Models\User;
use App\Models\EventDistanceForMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a user moves, only their cached mileage for events on/after the move date
 * should be invalidated — past events keep the mileage that was correct then.
 * The move date is supplied by the user (defaulting to today) since the system
 * stores only the current address and can't otherwise know when they moved.
 */
class AccountMoveMileageInvalidationTest extends TestCase
{
    use RefreshDatabase;

    /** Seed a cached mileage row for the given user + event date. */
    private function seedMileage(User $user, string $date): EventDistanceForMembers
    {
        $event = Events::factory()->create(['date' => $date]);

        return EventDistanceForMembers::create([
            'user_id'  => $user->id,
            'event_id' => $event->id,
            'miles'    => 42.0,
            'minutes'  => 50,
        ]);
    }

    private function patchAddress(User $user, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $token = $user->createToken('test')->plainTextToken;

        return $this->withToken($token)->patchJson('/api/mobile/account', array_merge([
            'name'                => $user->name,
            'email'               => $user->email,
            'address1'            => '500 New Place',
            'city'                => 'Baton Rouge',
            'state_id'            => 12,
            'zip'                 => '70802',
            'email_notifications' => true,
        ], $overrides));
    }

    public function test_only_events_on_or_after_move_date_are_invalidated(): void
    {
        $user = User::factory()->create([
            'Address1' => '1 Old St', 'City' => 'Old Town', 'StateID' => '5', 'Zip' => '00001',
        ]);

        $before = $this->seedMileage($user, '2026-01-10'); // before move
        $onDay  = $this->seedMileage($user, '2026-03-01'); // exactly on move date
        $after  = $this->seedMileage($user, '2026-05-20'); // after move

        $this->patchAddress($user, ['moved_at' => '2026-03-01'])->assertOk();

        // Past event keeps its locked-in mileage.
        $this->assertDatabaseHas('event_distance_for_members', ['id' => $before->id]);
        // On/after the move date are invalidated so they recompute on next view.
        $this->assertDatabaseMissing('event_distance_for_members', ['id' => $onDay->id]);
        $this->assertDatabaseMissing('event_distance_for_members', ['id' => $after->id]);
    }

    public function test_move_date_defaults_to_today_when_omitted(): void
    {
        $user = User::factory()->create([
            'Address1' => '1 Old St', 'City' => 'Old Town', 'StateID' => '5', 'Zip' => '00001',
        ]);

        $past   = $this->seedMileage($user, now()->subMonth()->toDateString());
        $future = $this->seedMileage($user, now()->addMonth()->toDateString());

        // No moved_at → defaults to today: only today-or-later recompute.
        $this->patchAddress($user, ['moved_at' => null])->assertOk();

        $this->assertDatabaseHas('event_distance_for_members', ['id' => $past->id]);
        $this->assertDatabaseMissing('event_distance_for_members', ['id' => $future->id]);
    }

    public function test_unchanged_address_does_not_invalidate_any_mileage(): void
    {
        $user = User::factory()->create([
            'Address1' => '500 New Place', 'Address2' => null, 'City' => 'Baton Rouge',
            'StateID' => '12', 'Zip' => '70802',
        ]);

        $future = $this->seedMileage($user, now()->addMonth()->toDateString());

        // Re-submit the SAME address (only the name differs) — mileage must survive.
        $this->patchAddress($user, ['name' => 'Renamed Person', 'moved_at' => '2026-01-01'])
            ->assertOk();

        $this->assertDatabaseHas('event_distance_for_members', ['id' => $future->id]);
    }

    public function test_only_the_moving_users_mileage_is_invalidated(): void
    {
        $mover = User::factory()->create([
            'Address1' => '1 Old St', 'City' => 'Old Town', 'StateID' => '5', 'Zip' => '00001',
        ]);
        $other = User::factory()->create();

        $event = Events::factory()->create(['date' => now()->addMonth()->toDateString()]);
        $moverRow = EventDistanceForMembers::create([
            'user_id' => $mover->id, 'event_id' => $event->id, 'miles' => 10, 'minutes' => 20,
        ]);
        $otherRow = EventDistanceForMembers::create([
            'user_id' => $other->id, 'event_id' => $event->id, 'miles' => 10, 'minutes' => 20,
        ]);

        $this->patchAddress($mover, ['moved_at' => now()->toDateString()])->assertOk();

        $this->assertDatabaseMissing('event_distance_for_members', ['id' => $moverRow->id]);
        $this->assertDatabaseHas('event_distance_for_members', ['id' => $otherRow->id]);
    }

    public function test_invalid_move_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->patchAddress($user, ['moved_at' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['moved_at']);
    }
}
