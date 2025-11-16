<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\BandApiToken;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BandApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private $band;
    private $owner;
    private $apiToken;
    private $plainTextToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed API permissions
        $this->artisan('db:seed', ['--class' => 'ApiPermissionsSeeder']);

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        // Create a test API token
        $this->plainTextToken = Str::random(60);
        $hashedToken = hash('sha256', $this->plainTextToken);

        $this->apiToken = BandApiToken::create([
            'band_id' => $this->band->id,
            'token' => $hashedToken,
            'name' => 'Test Token',
            'is_active' => true,
        ]);

        // Give the token read-bookings permission for booked-dates endpoint
        $this->apiToken->givePermissionTo('api:read-bookings');
    }

    public function test_can_access_booked_dates_with_valid_token(): void
    {
        // Create a test booking with event
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Test Event',
            'date' => '2025-12-01',
            'status' => 'confirmed',
        ]);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Test Event',
            'date' => '2025-12-01',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'band' => ['id', 'name'],
                'bookings',
                'total',
            ])
            ->assertJson([
                'success' => true,
                'band' => [
                    'id' => $this->band->id,
                    'name' => $this->band->name,
                ],
            ]);
    }

    public function test_cannot_access_booked_dates_without_token(): void
    {
        $response = $this->getJson('/api/booked-dates');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'API token is required',
            ]);
    }

    public function test_cannot_access_booked_dates_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/booked-dates');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or inactive API token',
            ]);
    }

    public function test_cannot_access_booked_dates_with_inactive_token(): void
    {
        // Disable the token
        $this->apiToken->update(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or inactive API token',
            ]);
    }

    public function test_owner_can_create_api_token(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('bands.apiTokens.store', $this->band), [
                'name' => 'Wix Integration',
            ]);

        $response->assertSessionHas('new_api_token');
        $newToken = session('new_api_token');
        $this->assertNotNull($newToken['token']);
        $this->assertEquals('Wix Integration', $newToken['name']);

        $this->assertDatabaseHas('band_api_tokens', [
            'band_id' => $this->band->id,
            'name' => 'Wix Integration',
            'is_active' => true,
        ]);
    }

    public function test_owner_can_toggle_token_status(): void
    {
        $this->assertTrue($this->apiToken->is_active);

        $response = $this->actingAs($this->owner)
            ->post(route('bands.apiTokens.toggle', [$this->band, $this->apiToken]));

        $response->assertSessionHas('message', 'Token status updated');

        $this->apiToken->refresh();
        $this->assertFalse($this->apiToken->is_active);
    }

    public function test_owner_can_delete_api_token(): void
    {
        $response = $this->actingAs($this->owner)
            ->delete(route('bands.apiTokens.destroy', [$this->band, $this->apiToken]));

        $response->assertSessionHas('message', 'Token deleted successfully');

        $this->assertDatabaseMissing('band_api_tokens', [
            'id' => $this->apiToken->id,
        ]);
    }

    public function test_non_owner_cannot_manage_tokens(): void
    {
        $nonOwner = User::factory()->create();

        $response = $this->actingAs($nonOwner)
            ->post(route('bands.apiTokens.store', $this->band), [
                'name' => 'Unauthorized Token',
            ]);

        $response->assertStatus(403);
    }

    public function test_booked_dates_returns_correct_booking_structure(): void
    {
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Wedding Reception',
            'date' => '2025-12-15',
            'start_time' => '18:00:00',
            'venue_name' => 'Grand Ballroom',
            'venue_address' => '123 Main St',
            'status' => 'confirmed',
            'price' => 150000, // $1500.00
            'notes' => 'Special requests noted',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(200)
            ->assertJsonStructure([
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
            ]);

        $booking = $response->json('bookings.0');
        $this->assertEquals('Wedding Reception', $booking['name']);
        $this->assertEquals('2025-12-15', $booking['date']);
        $this->assertEquals('Grand Ballroom', $booking['venue_name']);
        $this->assertEquals('confirmed', $booking['status']);
    }
}
