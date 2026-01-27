<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Events;
use App\Models\Bookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MediaFolderFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_table_has_media_folder_path_field(): void
    {
        $booking = Bookings::factory()->create();

        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'time' => '19:00:00',
            'title' => 'Test Event',
            'media_folder_path' => '2024/03/Test-Event',
            'key' => (string) Str::uuid(),
        ]);

        $this->assertNotNull($event->id);
        $this->assertEquals('2024/03/Test-Event', $event->media_folder_path);
    }

    public function test_events_table_has_enable_portal_media_access_field(): void
    {
        $booking = Bookings::factory()->create();

        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Test Event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        $this->assertTrue($event->enable_portal_media_access);
    }

    public function test_events_enable_portal_media_access_defaults_to_true(): void
    {
        $booking = Bookings::factory()->create();

        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Test Event',
            'key' => (string) Str::uuid(),
        ]);

        $this->assertTrue($event->enable_portal_media_access);
    }

    public function test_events_media_folder_path_can_be_updated(): void
    {
        $booking = Bookings::factory()->create();

        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Test Event',
            'key' => (string) Str::uuid(),
        ]);

        $this->assertNull($event->media_folder_path);

        $event->update(['media_folder_path' => '2024/03/Smith-Wedding']);
        $event->refresh();

        $this->assertEquals('2024/03/Smith-Wedding', $event->media_folder_path);
    }

    public function test_bookings_table_has_enable_portal_media_access_field(): void
    {
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
        ]);

        $this->assertTrue($booking->enable_portal_media_access);
    }

    public function test_bookings_enable_portal_media_access_defaults_to_true(): void
    {
        $booking = Bookings::factory()->create();

        $this->assertTrue($booking->enable_portal_media_access);
    }

    public function test_bookings_enable_portal_media_access_can_be_toggled(): void
    {
        $booking = Bookings::factory()->create();

        $this->assertTrue($booking->enable_portal_media_access);

        $booking->update(['enable_portal_media_access' => false]);
        $booking->refresh();

        $this->assertFalse($booking->enable_portal_media_access);

        $booking->update(['enable_portal_media_access' => true]);
        $booking->refresh();

        $this->assertTrue($booking->enable_portal_media_access);
    }
}
