<?php

namespace Tests\Unit\Models;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test to make sure that an eventable morph with the same band ID can load correctly
     */
    public function testEventableBandRestrictionCanLoad()
    {
        \DB::listen(function($query) {
            echo $query->sql . ' [' . implode(', ', $query->bindings) . ']' . PHP_EOL;
        });
        // Create a new event and a booking with the same band ID
        /** @var Bookings $booking */
        $booking = Bookings::factory()->create();
        /** @var Events $event */
        $event = Events::factory()->forBand($booking->band)->withEventable($booking)->create();

        // Make sure the event has an eventable
        $this->assertNotNull($event->eventable);
        // Make sure the eventable is the booking we made
        $this->assertEquals($booking->id, $event->eventable->id);
    }

    /**
     * Test to make sure that an eventable morph with a different band ID doesn't load
     */
    public function testEventableBandRestrictionDoesNotLoad()
    {
        \DB::listen(function($query) {
            echo $query->sql . ' [' . implode(', ', $query->bindings) . ']' . PHP_EOL;
        });
        // Create a new event and a booking with a different band ID
        /** @var Bookings $booking */
        $booking = Bookings::factory()->create();
        /** @var Events $event */
        $event = Events::factory()->withEventable($booking)->create();

        // Make sure the event does not load an eventable due to the band ID mismatch
        $this->assertNull($event->eventable);
    }

    /**
     * Test to make sure that an eventable morph with no band_id property doesn't load
     */
    public function testEventableBandRestrictionDoesNotLoadNoBandId()
    {
        \DB::listen(function ($query) {
            echo $query->sql . ' [' . implode(', ', $query->bindings) . ']' . PHP_EOL;
        });
        // Create a new event and a booking with a different band ID
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Events $event */
        $event = Events::factory()->withEventable($user)->create();

        // Make sure the event does not load an eventable because it has no band_id
        // It will throw an exception instead
        $this->expectException(QueryException::class);
        $this->assertNull($event->eventable);
    }
}
