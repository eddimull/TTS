<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Events\RehearsalPlannerStreamEvent;
use Tests\TestCase;

class StreamEventTest extends TestCase
{
    public function test_broadcast_shape(): void
    {
        $event = new RehearsalPlannerStreamEvent(7, 'text_delta', ['delta' => 'Hi']);

        $this->assertSame('planner.stream', $event->broadcastAs());
        $this->assertSame('private-rehearsal-planner.7', $event->broadcastOn()[0]->name);
        $this->assertSame(['type' => 'text_delta', 'delta' => 'Hi'], $event->broadcastWith());
    }
}
