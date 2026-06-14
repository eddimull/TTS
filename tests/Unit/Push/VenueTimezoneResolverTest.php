<?php

namespace Tests\Unit\Push;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Services\Push\VenueTimezoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueTimezoneResolverTest extends TestCase
{
    use RefreshDatabase;

    private function eventWithAddress(?string $address): Events
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'venue_address'  => $address,
        ]);
    }

    public function test_returns_cached_timezone_without_calling_lookup(): void
    {
        $event = $this->eventWithAddress('100 Main St');
        $event->venue_timezone = 'America/Chicago';
        $event->save();

        $resolver = new VenueTimezoneResolver(function () {
            $this->fail('should not look up when cached');
        });

        $this->assertSame('America/Chicago', $resolver->forEvent($event));
    }

    public function test_looks_up_caches_and_returns(): void
    {
        $event = $this->eventWithAddress('100 Main St, Austin TX');
        $resolver = new VenueTimezoneResolver(fn (string $addr) => 'America/Chicago');

        $this->assertSame('America/Chicago', $resolver->forEvent($event));
        $this->assertSame('America/Chicago', $event->fresh()->venue_timezone);
    }

    public function test_falls_back_to_app_tz_without_caching_on_failure(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $event = $this->eventWithAddress('nowhere');
        $resolver = new VenueTimezoneResolver(fn (string $addr) => null);

        $this->assertSame('America/New_York', $resolver->forEvent($event));
        $this->assertNull($event->fresh()->venue_timezone);
    }

    public function test_no_address_uses_app_tz(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $event = $this->eventWithAddress(null);
        $resolver = new VenueTimezoneResolver(function () {
            $this->fail('no lookup for empty address');
        });

        $this->assertSame('America/New_York', $resolver->forEvent($event));
    }
}
