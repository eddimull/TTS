<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Events;
use App\Models\User;
use Database\Factories\EventsFactory;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GetEventsTest extends TestCase
{
    use WithFaker;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_getEventsAsOwner()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();

        BandOwners::create([
            'band_id' => $band->id,
            'user_id' => $user->id
        ]);

        $eventCount = $this->faker->numberBetween(0, 10);
        Events::factory($eventCount)->forBand($band)->create();
        $this->assertCount($eventCount, $user->events);
    }

    public function test_getEventsAsMember()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();

        BandMembers::create([
            'band_id' => $band->id,
            'user_id' => $user->id
        ]);

        $eventCount = $this->faker->numberBetween(0, 10);
        Events::factory($eventCount)->forBand($band)->create();
        $this->assertCount($eventCount, $user->events);
    }

    public function test_getEventsAsMemberAndOwner()
    {
        $bandOwner = Bands::factory()->create();
        $bandMember = Bands::factory()->create();
        $user = User::factory()->create();

        BandMembers::create([
            'band_id' => $bandMember->id,
            'user_id' => $user->id
        ]);

        BandOwners::create([
            'band_id' => $bandOwner->id,
            'user_id' => $user->id
        ]);

        $eventCountOwner = $this->faker->numberBetween(0, 10);
        $eventCountMember = $this->faker->numberBetween(0, 10);
        Events::factory($eventCountOwner)->forBand($bandOwner)->create();
        Events::factory($eventCountMember)->forBand($bandMember)->create();
        $this->assertCount(($eventCountOwner + $eventCountMember), $user->events);
    }

    public function test_getEventsMultipleOwnedBands()
    {
        $bands = Bands::factory($this->faker->numberBetween(1, 5))->create();
        $user = User::factory()->create();
        $totalCount = 0;
        foreach ($bands as $band)
        {
            BandOwners::create([
                'band_id' => $band->id,
                'user_id' => $user->id
            ]);
            $eventCount = $this->faker->numberBetween(0, 10);
            Events::factory($eventCount)->forBand($band)->create();
            $totalCount += $eventCount;
        }

        $this->assertCount($totalCount, $user->events);
    }

    public function test_getEventsMultipleJoinedBands()
    {
        $bands = Bands::factory($this->faker->numberBetween(1, 5))->create();
        $user = User::factory()->create();
        $totalCount = 0;
        foreach ($bands as $band)
        {
            BandMembers::create([
                'band_id' => $band->id,
                'user_id' => $user->id
            ]);
            $eventCount = $this->faker->numberBetween(0, 10);
            Events::factory($eventCount)->forBand($band)->create();
            $totalCount += $eventCount;
        }

        $this->assertCount($totalCount, $user->events);
    }

    public function test_noBandNoEvents()
    {
        $user = User::factory()->create();
        $this->assertCount(0, $user->events);
    }

    public function test_getOlderEvents()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();

        BandMembers::create([
            'band_id' => $band->id,
            'user_id' => $user->id
        ]);

        $eventCount = $this->faker->numberBetween(0, 10);
        Events::factory($eventCount)->forBand($band)->create();
        $this->assertCount($eventCount, $user->events);
        foreach ($user->events as $event)
        {
            $this->assertTrue($event->OldEvent);
        }
    }

    public function test_ISO_event_Date()
    {
        $event = Events::factory()->create([
            'date' => Carbon::now(),
            'time' => Carbon::now()
        ]);

        $this->assertEquals(Carbon::now()->isoFormat('YYYY-MM-DD Thh:mm:ss.sss'), $event->ISODate);
    }

    public function test_old_event()
    {
        $event = Events::factory()->create([
            'date' => Carbon::parse('1 month ago'),
        ]);

        $this->assertTrue($event->OldEvent);
    }

    public function test_get_advance_url()
    {
        $event = Events::factory()->create();

        $this->assertEquals(config('app.url') . '/events/' . $event->key . '/advance', $event->advanceURL());
    }
}
