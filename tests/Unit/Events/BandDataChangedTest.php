<?php

namespace Tests\Unit\Events;

use App\Events\BandDataChanged;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\TestCase;

class BandDataChangedTest extends TestCase
{
    public function test_broadcasts_on_the_band_private_channel(): void
    {
        $event = new BandDataChanged(42, 'bookings', 7, 'updated');

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-band.42', $channels[0]->name);
    }

    public function test_broadcast_alias_and_thin_payload(): void
    {
        $event = new BandDataChanged(42, 'bookings', 7, 'created');

        $this->assertSame('band.data-changed', $event->broadcastAs());
        $this->assertSame(
            ['model' => 'bookings', 'id' => 7, 'action' => 'created'],
            $event->broadcastWith(),
        );
    }

    public function test_payload_includes_parent_when_given(): void
    {
        $event = new BandDataChanged(42, 'event_member', 9, 'deleted', ['model' => 'events', 'id' => 3]);

        $this->assertSame(
            [
                'model'  => 'event_member',
                'id'     => 9,
                'action' => 'deleted',
                'parent' => ['model' => 'events', 'id' => 3],
            ],
            $event->broadcastWith(),
        );
    }
}
