<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\BandApiToken;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingsApiTest extends TestCase
{
    use RefreshDatabase;

    private $band;
    private $owner;
    private $readToken;
    private $writeToken;
    private $fullAccessToken;
    private $readPlainText;
    private $writePlainText;
    private $fullAccessPlainText;
    private $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed API permissions
        $this->artisan('db:seed', ['--class' => 'ApiPermissionsSeeder']);

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        // Create an event type for testing
        $this->eventType = EventTypes::first() ?? EventTypes::factory()->create();

        // Create read-only token
        $this->readPlainText = Str::random(60);
        $hashedToken = hash('sha256', $this->readPlainText);
        $this->readToken = BandApiToken::create([
            'band_id' => $this->band->id,
            'token' => $hashedToken,
            'name' => 'Read Only Token',
            'is_active' => true,
        ]);
        $this->readToken->givePermissionTo(['api:read-bookings']);

        // Create write-only token
        $this->writePlainText = Str::random(60);
        $hashedToken = hash('sha256', $this->writePlainText);
        $this->writeToken = BandApiToken::create([
            'band_id' => $this->band->id,
            'token' => $hashedToken,
            'name' => 'Write Only Token',
            'is_active' => true,
        ]);
        $this->writeToken->givePermissionTo('api:write-bookings');

        // Create full access token
        $this->fullAccessPlainText = Str::random(60);
        $hashedToken = hash('sha256', $this->fullAccessPlainText);
        $this->fullAccessToken = BandApiToken::create([
            'band_id' => $this->band->id,
            'token' => $hashedToken,
            'name' => 'Full Access Token',
            'is_active' => true,
        ]);
        $this->fullAccessToken->givePermissionTo([
            'api:read-bookings',
            'api:write-bookings',
            'api:read-events',
        ]);
    }

    // ========================================
    // READ OPERATIONS TESTS
    // ========================================

    public function test_can_list_bookings_with_read_permission(): void
    {
        Bookings::factory()->count(3)->create(['band_id' => $this->band->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/bookings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'bookings' => [
                    '*' => [
                        'id',
                        'name',
                        'date',
                        'start_time',
                        'end_time',
                        'venue_name',
                        'price',
                        'status',
                    ],
                ],
                'total',
            ])
            ->assertJson(['total' => 3]);
    }

    public function test_cannot_list_bookings_without_read_permission(): void
    {
        Bookings::factory()->create(['band_id' => $this->band->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->writePlainText,
        ])->getJson('/api/bookings');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'required_permission' => 'api:read-bookings',
            ]);
    }

    public function test_can_show_single_booking_with_read_permission(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Test Booking',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/bookings/' . $booking->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'booking' => [
                    'id' => $booking->id,
                    'name' => 'Test Booking',
                ],
            ]);
    }

    public function test_cannot_show_booking_from_different_band(): void
    {
        $otherBand = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $otherBand->id,
            'name' => 'Other Band Booking',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/bookings/' . $booking->id);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Not Found',
                'message' => 'Booking not found or does not belong to your band',
            ]);
    }

    // ========================================
    // CREATE OPERATIONS TESTS
    // ========================================

    public function test_can_create_booking_with_write_permission(): void
    {
        $bookingData = [
            'name' => 'Wedding Reception',
            'event_type_id' => $this->eventType->id,
            'date' => '2025-12-15',
            'start_time' => '18:00',
            'duration' => 4,
            'price' => 1500,
            'venue_name' => 'Grand Ballroom',
            'venue_address' => '123 Main St',
            'contract_option' => 'default',
            'status' => 'pending',
            'notes' => 'Special requests here',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->postJson('/api/bookings', $bookingData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'booking' => [
                    'id',
                    'name',
                    'date',
                    'start_time',
                    'end_time',
                ],
            ])
            ->assertJson([
                'success' => true,
                'booking' => [
                    'name' => 'Wedding Reception',
                    'venue_name' => 'Grand Ballroom',
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'band_id' => $this->band->id,
            'name' => 'Wedding Reception',
            'venue_name' => 'Grand Ballroom',
        ]);
    }

    public function test_cannot_create_booking_without_write_permission(): void
    {
        $bookingData = [
            'name' => 'Test Event',
            'event_type_id' => $this->eventType->id,
            'date' => '2025-12-15',
            'start_time' => '18:00',
            'duration' => 4,
            'price' => 1500,
            'contract_option' => 'default',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->postJson('/api/bookings', $bookingData);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'required_permission' => 'api:write-bookings',
            ]);
    }

    public function test_create_booking_validates_required_fields(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->postJson('/api/bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'event_type_id',
                'date',
                'start_time',
                'duration',
                'price',
                'contract_option',
            ]);
    }

    public function test_create_booking_validates_date_is_not_in_past(): void
    {
        $bookingData = [
            'name' => 'Past Event',
            'event_type_id' => $this->eventType->id,
            'date' => '2020-01-01',
            'start_time' => '18:00',
            'duration' => 4,
            'price' => 1500,
            'contract_option' => 'default',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->postJson('/api/bookings', $bookingData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_create_booking_calculates_end_time_from_duration(): void
    {
        $bookingData = [
            'name' => 'Test Event',
            'event_type_id' => $this->eventType->id,
            'date' => '2025-12-15',
            'start_time' => '18:00',
            'duration' => 3,
            'price' => 1500,
            'contract_option' => 'default',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->postJson('/api/bookings', $bookingData);

        $response->assertStatus(201);
        $booking = Bookings::latest()->first();

        $this->assertEquals('18:00', $booking->start_time->format('H:i'));
        $this->assertEquals('21:00', $booking->end_time->format('H:i'));
    }

    // ========================================
    // UPDATE OPERATIONS TESTS
    // ========================================

    public function test_can_update_booking_with_write_permission(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Original Name',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'venue_name' => 'New Venue',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->putJson('/api/bookings/' . $booking->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'booking' => [
                    'id' => $booking->id,
                    'name' => 'Updated Name',
                    'venue_name' => 'New Venue',
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'name' => 'Updated Name',
            'venue_name' => 'New Venue',
        ]);
    }

    public function test_cannot_update_booking_without_write_permission(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->putJson('/api/bookings/' . $booking->id, [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'required_permission' => 'api:write-bookings',
            ]);
    }

    public function test_cannot_update_booking_from_different_band(): void
    {
        $otherBand = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $otherBand->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->putJson('/api/bookings/' . $booking->id, [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Not Found',
            ]);
    }

    public function test_update_booking_allows_partial_updates(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Original Name',
            'venue_name' => 'Original Venue',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->patchJson('/api/bookings/' . $booking->id, [
            'name' => 'Updated Name Only',
        ]);

        $response->assertStatus(200);
        $booking->refresh();

        $this->assertEquals('Updated Name Only', $booking->name);
        $this->assertEquals('Original Venue', $booking->venue_name);
    }

    // ========================================
    // DELETE OPERATIONS TESTS
    // ========================================

    public function test_can_delete_booking_with_write_permission(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->deleteJson('/api/bookings/' . $booking->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Booking deleted successfully',
            ]);

        $this->assertDatabaseMissing('bookings', [
            'id' => $booking->id,
        ]);
    }

    public function test_cannot_delete_booking_without_write_permission(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->deleteJson('/api/bookings/' . $booking->id);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'required_permission' => 'api:write-bookings',
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
        ]);
    }

    public function test_cannot_delete_booking_from_different_band(): void
    {
        $otherBand = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $otherBand->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->deleteJson('/api/bookings/' . $booking->id);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Not Found',
            ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
        ]);
    }

    // ========================================
    // BOOKED DATES TESTS
    // ========================================

    public function test_can_get_all_booked_dates_with_read_bookings_permission(): void
    {
        // Create some bookings
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Event 1',
            'date' => '2025-01-15',
            'start_time' => '18:00',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Event 2',
            'date' => '2025-02-20',
            'start_time' => '19:00',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'band' => ['id', 'name'],
                'bookings' => [
                    '*' => [
                        'id',
                        'name',
                        'date',
                        'start_time',
                        'end_time',
                        'duration',
                        'event_type',
                        'event_type_id',
                        'venue_name',
                        'venue_address',
                        'status',
                        'price',
                        'notes',
                    ],
                ],
                'total',
            ])
            ->assertJson(['total' => 2]);
    }

    public function test_can_filter_booked_dates_by_specific_date(): void
    {
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-01-15',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-02-20',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/booked-dates?date=2025-01-15');

        $response->assertStatus(200)
            ->assertJson([
                'total' => 1,
                'filters' => [
                    'date' => '2025-01-15',
                ],
            ]);

        $bookings = $response->json('bookings');
        $this->assertEquals('2025-01-15', $bookings[0]['date']);
    }

    public function test_can_filter_booked_dates_by_date_range(): void
    {
        // Create bookings in different months
        for ($day = 1; $day <= 5; $day++) {
            Bookings::factory()->create([
                'band_id' => $this->band->id,
                'date' => "2025-01-{$day}",
            ]);
        }

        for ($day = 1; $day <= 3; $day++) {
            Bookings::factory()->create([
                'band_id' => $this->band->id,
                'date' => "2025-02-{$day}",
            ]);
        }

        // Query for January only
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/booked-dates?from=2025-01-01&to=2025-01-31');

        $response->assertStatus(200)
            ->assertJson([
                'total' => 5,
                'filters' => [
                    'from' => '2025-01-01',
                    'to' => '2025-01-31',
                ],
            ]);

        foreach ($response->json('bookings') as $booking) {
            $this->assertStringStartsWith('2025-01', $booking['date']);
        }
    }

    public function test_can_filter_booked_dates_after_specific_date(): void
    {
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-01-15',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-02-20',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-03-25',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/booked-dates?after=2025-02-01');

        $response->assertStatus(200)
            ->assertJson([
                'total' => 2,
                'filters' => [
                    'after' => '2025-02-01',
                ],
            ]);

        foreach ($response->json('bookings') as $booking) {
            $this->assertGreaterThan('2025-02-01', $booking['date']);
        }
    }

    public function test_can_filter_booked_dates_before_specific_date(): void
    {
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-01-15',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-02-20',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/booked-dates?before=2025-02-01');

        $response->assertStatus(200)
            ->assertJson([
                'total' => 1,
                'filters' => [
                    'before' => '2025-02-01',
                ],
            ]);

        $bookings = $response->json('bookings');
        $this->assertEquals('2025-01-15', $bookings[0]['date']);
    }

    public function test_booked_dates_validates_date_format(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/booked-dates?date=invalid-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_booked_dates_requires_read_bookings_permission(): void
    {
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-01-15',
        ]);

        // Write-only token should not have access
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->writePlainText,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'required_permission' => 'api:read-bookings',
            ]);
    }

    public function test_booked_dates_only_returns_bookings_for_authenticated_band(): void
    {
        // Create booking for authenticated band
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-01-15',
        ]);

        // Create booking for different band
        $otherBand = Bands::factory()->create();
        Bookings::factory()->create([
            'band_id' => $otherBand->id,
            'date' => '2025-01-16',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(200)
            ->assertJson(['total' => 1]);

        $bookings = $response->json('bookings');
        $this->assertEquals('2025-01-15', $bookings[0]['date']);
    }

    // ========================================
    // EVENTS API TESTS
    // ========================================

    public function test_can_get_all_events_with_read_events_permission(): void
    {
        // Create some bookings with events
        $booking1 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Event 1',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking1->id,
            'date' => '2025-01-15',
            'time' => '18:00',
            'title' => 'Event 1',
        ]);

        $booking2 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Event 2',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking2->id,
            'date' => '2025-02-20',
            'time' => '19:00',
            'title' => 'Event 2',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'band' => ['id', 'name'],
                'events' => [
                    '*' => [
                        'id',
                        'title',
                        'date',
                        'time',
                        'start_datetime',
                        'end_datetime',
                        'event_type',
                        'event_type_id',
                        'eventable_type',
                        'eventable_id',
                        'venue_name',
                        'venue_address',
                        'status',
                        'price',
                        'notes',
                        'is_public',
                    ],
                ],
                'total',
            ])
            ->assertJson(['total' => 2]);
    }

    public function test_can_filter_events_by_specific_date(): void
    {
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking1->id,
            'date' => '2025-01-15',
            'title' => 'January Event',
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking2->id,
            'date' => '2025-02-20',
            'title' => 'February Event',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->getJson('/api/events?date=2025-01-15');

        $response->assertStatus(200)
            ->assertJson([
                'total' => 1,
                'filters' => [
                    'date' => '2025-01-15',
                ],
            ]);

        $events = $response->json('events');
        $this->assertEquals('2025-01-15', $events[0]['date']);
        $this->assertEquals('January Event', $events[0]['title']);
    }

    public function test_can_filter_events_by_date_range(): void
    {
        // Create events in different months
        for ($day = 1; $day <= 5; $day++) {
            $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
            Events::factory()->create([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'date' => "2025-01-{$day}",
                'title' => "January Event {$day}",
            ]);
        }

        for ($day = 1; $day <= 3; $day++) {
            $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
            Events::factory()->create([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'date' => "2025-02-{$day}",
                'title' => "February Event {$day}",
            ]);
        }

        // Query for January only
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->getJson('/api/events?from=2025-01-01&to=2025-01-31');

        $response->assertStatus(200)
            ->assertJson([
                'total' => 5,
                'filters' => [
                    'from' => '2025-01-01',
                    'to' => '2025-01-31',
                ],
            ]);

        foreach ($response->json('events') as $event) {
            $this->assertStringStartsWith('2025-01', $event['date']);
        }
    }

    public function test_can_filter_events_after_specific_date(): void
    {
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking1->id,
            'date' => '2025-01-15',
            'title' => 'January Event',
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking2->id,
            'date' => '2025-02-20',
            'title' => 'February Event',
        ]);

        $booking3 = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking3->id,
            'date' => '2025-03-25',
            'title' => 'March Event',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->getJson('/api/events?after=2025-02-01');

        $response->assertStatus(200)
            ->assertJson([
                'total' => 2,
                'filters' => [
                    'after' => '2025-02-01',
                ],
            ]);

        foreach ($response->json('events') as $event) {
            $this->assertGreaterThan('2025-02-01', $event['date']);
        }
    }

    public function test_events_requires_read_events_permission(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => '2025-01-15',
        ]);

        // Token without read-events permission should not have access
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->readPlainText,
        ])->getJson('/api/events');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'required_permission' => 'api:read-events',
            ]);
    }

    public function test_events_only_returns_events_for_authenticated_band(): void
    {
        // Create event for authenticated band
        $myBooking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $myBooking->id,
            'date' => '2025-01-15',
            'title' => 'My Band Event',
        ]);

        // Create event for different band
        $otherBand = Bands::factory()->create();
        $otherBooking = Bookings::factory()->create(['band_id' => $otherBand->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $otherBooking->id,
            'date' => '2025-01-16',
            'title' => 'Other Band Event',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJson(['total' => 1]);

        $events = $response->json('events');
        $this->assertEquals('2025-01-15', $events[0]['date']);
        $this->assertEquals('My Band Event', $events[0]['title']);
    }

    public function test_events_includes_eventable_type_information(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => '2025-01-15',
            'title' => 'Booking Event',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->fullAccessPlainText,
        ])->getJson('/api/events');

        $response->assertStatus(200);

        $events = $response->json('events');
        $this->assertEquals('Bookings', $events[0]['eventable_type']);
        $this->assertEquals($booking->id, $events[0]['eventable_id']);
    }
}
