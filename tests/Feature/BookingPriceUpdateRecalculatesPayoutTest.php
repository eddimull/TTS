<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Payout;
use App\Models\EventTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;


class BookingPriceUpdateRecalculatesPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected EventTypes $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'sync']);

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->eventType = EventTypes::factory()->create();

        $this->actingAs($this->user);
    }


    public function test_booking_price_change_recalculates_event_values_and_payout()
    {
        // Create a booking with original price
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 4145, 
            'event_type_id' => $this->eventType->id,
            'date' => now()->addDays(10),
        ]);

        // Create 2 events for the booking
        $event1 = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
        ]);

        $event2 = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date->addDay(),
        ]);

        
        $event1->update(['value' => 2072.50]); // 4145 / 2
        $event2->update(['value' => 2072.50]); // 4145 / 2

       
        $payout = Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $this->band->id,
            'base_amount' => 4145,
            'adjusted_amount' => 4145,
        ]);

        // Verify initial state
        $this->assertEquals(4145, $booking->price);
        $this->assertEquals(2072.50, $event1->fresh()->value);
        $this->assertEquals(2072.50, $event2->fresh()->value);
        $this->assertEquals(4145, $payout->fresh()->base_amount);

        
        $booking->update(['price' => 4000]);

        
        $controller = new \App\Http\Controllers\BookingsController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('redistributeEventValues');
        $method->setAccessible(true);
        $method->invoke($controller, $booking->fresh());

        
        $event1->refresh();
        $event2->refresh();
        $payout->refresh();

        
        $this->assertEquals(2000, $event1->value, 'Event 1 value should be recalculated to 2000');
        $this->assertEquals(2000, $event2->value, 'Event 2 value should be recalculated to 2000');

        
        $this->assertEquals(4000, $payout->base_amount, 'Payout base_amount should be recalculated to 4000');
        $this->assertEquals(4000, $payout->adjusted_amount, 'Payout adjusted_amount should be recalculated to 4000');

       
        $this->assertEquals(4000, $booking->fresh()->total_event_value);
    }


    public function test_booking_price_change_with_three_events()
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 3000,
            'event_type_id' => $this->eventType->id,
            'date' => now()->addDays(10),
        ]);


        $events = [];
        for ($i = 0; $i < 3; $i++) {
            $events[] = Events::factory()->create([
                'eventable_id' => $booking->id,
                'eventable_type' => Bookings::class,
                'event_type_id' => $this->eventType->id,
                'date' => $booking->date->addDays($i),
                'value' => 1000, 
            ]);
        }

        $payout = Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $this->band->id,
            'base_amount' => 3000,
            'adjusted_amount' => 3000,
        ]);

        $booking->update(['price' => 3300]);

        $controller = new \App\Http\Controllers\BookingsController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('redistributeEventValues');
        $method->setAccessible(true);
        $method->invoke($controller, $booking->fresh());

        // Each event should now have 3300 / 3 = 1100
        foreach ($events as $event) {
            $this->assertEquals(1100, $event->fresh()->value);
        }

        
        $this->assertEquals(3300, $payout->fresh()->base_amount);
    }


    public function test_booking_price_change_preserves_payout_adjustments()
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 2000,
            'event_type_id' => $this->eventType->id,
            'date' => now()->addDays(10),
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
            'value' => 2000,
        ]);

        $payout = Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $this->band->id,
            'base_amount' => 2000,
            'adjusted_amount' => 2000,
        ]);

        
        $adjustment = $payout->adjustments()->create([
            'amount' => -200,
            'description' => 'Travel expenses',
            'created_by' => $this->user->id,
        ]);

        
        $payout->recalculateAdjustedAmount();
        $this->assertEquals(1800, $payout->fresh()->adjusted_amount);

        
        $booking->update(['price' => 2500]);

        $controller = new \App\Http\Controllers\BookingsController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('redistributeEventValues');
        $method->setAccessible(true);
        $method->invoke($controller, $booking->fresh());

        // Base amount should be 2500, but adjustment should be preserved
        $payout->refresh();
        $this->assertEquals(2500, $payout->base_amount, 'Base amount should be updated to 2500');
        $this->assertEquals(2300, $payout->adjusted_amount, 'Adjusted amount should be 2500 - 200 = 2300');

        $this->assertEquals(-20000, $payout->adjustments()->sum('amount'));
        $this->assertCount(1, $payout->adjustments, 'Adjustment should still exist');
    }
}
