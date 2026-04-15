<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\EventAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EventAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected Events $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->user = User::factory()->create();

        $this->band = Bands::factory()->create();
        $this->band->owners()->create([
            'user_id' => $this->user->id,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
        ]);

        $eventType = EventTypes::factory()->create();
        $this->event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => $eventType->id,
        ]);
    }

    // --- upload ---

    public function test_upload_resolves_event_by_key()
    {
        $file = UploadedFile::fake()->create('setlist.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('events.attachments.upload', $this->event->key), [
                'files' => [$file],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'attachments']);
        $this->assertDatabaseHas('event_attachments', [
            'event_id' => $this->event->id,
            'filename' => 'setlist.pdf',
        ]);
    }

    public function test_upload_resolves_event_by_id()
    {
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('events.attachments.upload', $this->event->id), [
                'files' => [$file],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'attachments']);
        $this->assertDatabaseHas('event_attachments', [
            'event_id' => $this->event->id,
            'filename' => 'contract.pdf',
        ]);
    }

    public function test_upload_returns_404_for_unknown_event_identifier()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post('/events/non-existent-key/attachments', [
                'files' => [$file],
            ]);

        $response->assertStatus(404);
    }

    public function test_upload_requires_files()
    {
        $response = $this->actingAs($this->user)
            ->post(route('events.attachments.upload', $this->event->key), []);

        $response->assertStatus(400);
    }

    // --- index ---

    public function test_index_resolves_event_by_key()
    {
        EventAttachment::factory()->create(['event_id' => $this->event->id]);

        $response = $this->actingAs($this->user)
            ->get(route('events.attachments.index', $this->event->key));

        $response->assertStatus(200);
        $response->assertJsonStructure(['attachments']);
        $this->assertCount(1, $response->json('attachments'));
    }

    public function test_index_resolves_event_by_id()
    {
        EventAttachment::factory()->create(['event_id' => $this->event->id]);

        $response = $this->actingAs($this->user)
            ->get(route('events.attachments.index', $this->event->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['attachments']);
        $this->assertCount(1, $response->json('attachments'));
    }
}
