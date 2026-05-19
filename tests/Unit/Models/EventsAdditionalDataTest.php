<?php

namespace Tests\Unit\Models;

use App\Models\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventsAdditionalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_additional_data_returns_object_for_normal_json(): void
    {
        $event = Events::factory()->create();
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(['public' => true])]);

        $fresh = Events::find($event->id);
        $this->assertIsObject($fresh->additional_data);
        $this->assertTrue($fresh->additional_data->public);
    }

    public function test_additional_data_returns_object_for_double_encoded_json(): void
    {
        $event = Events::factory()->create();
        // Simulate the production corruption: the column holds a JSON string
        // whose decoded content is itself JSON.
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(json_encode(['public' => true]))]);

        $fresh = Events::find($event->id);
        $this->assertIsObject($fresh->additional_data);
        $this->assertTrue($fresh->additional_data->public);
    }

    public function test_additional_data_is_null_when_absent(): void
    {
        $event = Events::factory()->create();
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => null]);

        $fresh = Events::find($event->id);
        $this->assertNull($fresh->additional_data);
    }

    public function test_additional_data_returns_null_for_json_array_payload(): void
    {
        // The column semantically holds an object map. A JSON array payload
        // is malformed for our purposes and returns null rather than letting
        // an array silently flow to ->public / ->times readers.
        $event = Events::factory()->create();
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(['one', 'two', 'three'])]);

        $fresh = Events::find($event->id);
        $this->assertNull($fresh->additional_data);
    }

    public function test_assigning_array_to_additional_data_round_trips_through_set(): void
    {
        // The `set` closure preserves the prior `object` cast's write behavior
        // by json_encoding arrays/objects. Without this, the 32 call sites that
        // assign PHP arrays to additional_data would fail with "Array to string
        // conversion" on save.
        $event = Events::factory()->create();
        $event->additional_data = ['public' => true, 'venue' => 'Studio A'];
        $event->save();

        $fresh = Events::find($event->id);
        $this->assertIsObject($fresh->additional_data);
        $this->assertTrue($fresh->additional_data->public);
        $this->assertSame('Studio A', $fresh->additional_data->venue);
    }
}
