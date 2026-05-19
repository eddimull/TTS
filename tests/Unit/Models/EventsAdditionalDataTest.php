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
}
