<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\User;

trait ChatTestHelpers
{
    /** @return array{0: User, 1: Bands} */
    protected function makeOwnerWithBand(): array
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);

        return [$owner, $band];
    }

    protected function makeMember(Bands $band, array $permissions = ['read:events']): User
    {
        $member = User::factory()->create();
        $band->members()->create(['user_id' => $member->id]);

        setPermissionsTeamId($band->id);
        foreach ($permissions as $permission) {
            $member->givePermissionTo($permission);
        }
        setPermissionsTeamId(0);

        return $member;
    }

    protected function makeBookingEvent(Bands $band): Events
    {
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'title'          => 'Test Gig',
        ]);
    }

    /** @return array{0: Rehearsal, 1: Events} */
    protected function makeRehearsalEvent(Bands $band): array
    {
        $rehearsal = Rehearsal::factory()->create(['band_id' => $band->id]);
        $event     = Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'title'          => 'Rehearsal',
        ]);

        return [$rehearsal, $event];
    }

    /** A sub-only user assigned to $event (event_members path, like MobileSubEventsParityTest). */
    protected function makeSubAssignedTo(Bands $band, Events $event): User
    {
        $sub = User::factory()->create();
        BandSubs::firstOrCreate(['user_id' => $sub->id, 'band_id' => $band->id]);
        EventMember::create([
            'event_id'         => $event->id,
            'band_id'          => $band->id,
            'user_id'          => $sub->id,
            'roster_member_id' => null,
            'name'             => $sub->name,
        ]);

        return $sub;
    }

    /** A sub of the band NOT assigned to any event. */
    protected function makeUnassignedSub(Bands $band): User
    {
        $sub = User::factory()->create();
        BandSubs::firstOrCreate(['user_id' => $sub->id, 'band_id' => $band->id]);

        return $sub;
    }
}
