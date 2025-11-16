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
use Spatie\Permission\Models\Permission;

class BandApiPermissionTest extends TestCase
{
    use RefreshDatabase;

    private $band;
    private $owner;
    private $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'ApiPermissionsSeeder']);

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        // Cache permissions for easy access
        $this->permissions = [
            'read-events' => 'api:read-events',
            'write-events' => 'api:write-events',
            'read-bookings' => 'api:read-bookings',
            'write-bookings' => 'api:write-bookings',
        ];
    }

    /**
     * Helper method to create a token with specific permissions
     */
    private function createTokenWithPermissions(array $permissionNames): array
    {
        $plainTextToken = Str::random(60);
        $hashedToken = hash('sha256', $plainTextToken);

        $token = BandApiToken::create([
            'band_id' => $this->band->id,
            'token' => $hashedToken,
            'name' => 'Test Token',
            'is_active' => true,
        ]);

        if (!empty($permissionNames)) {
            $token->givePermissionTo($permissionNames);
        }

        return [$token, $plainTextToken];
    }

    /**
     * Helper method to create test booking and event
     */
    private function createTestBookingWithEvent(): array
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Test Event',
            'date' => '2025-12-01',
            'status' => 'confirmed',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Test Event',
            'date' => '2025-12-01',
        ]);

        return [$booking, $event];
    }

    // ========================================
    // READ EVENTS PERMISSION TESTS
    // ========================================

    public function test_token_with_read_bookings_permission_can_access_booked_dates(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([$this->permissions['read-bookings']]);
        $this->createTestBookingWithEvent();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'band',
                'bookings',
                'total',
            ]);
    }

    public function test_token_without_read_bookings_permission_cannot_access_booked_dates(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([$this->permissions['write-events']]);
        $this->createTestBookingWithEvent();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'This API token does not have permission to perform this action',
                'required_permission' => 'api:read-bookings',
            ]);
    }

    public function test_token_with_no_permissions_cannot_access_any_endpoint(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([]);
        $this->createTestBookingWithEvent();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
            ]);
    }

    // ========================================
    // MULTIPLE PERMISSIONS TESTS
    // ========================================

    public function test_token_with_multiple_permissions_has_access_to_all(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['read-bookings'],
            $this->permissions['write-bookings'],
        ]);
        $this->createTestBookingWithEvent();

        // Should be able to read bookings
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(200);
    }

    public function test_token_has_correct_permission_via_has_permission_to(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['read-events'],
        ]);

        $this->assertTrue($token->hasPermissionTo('api:read-events'));
        $this->assertFalse($token->hasPermissionTo('api:write-events'));
        $this->assertFalse($token->hasPermissionTo('api:read-bookings'));
        $this->assertFalse($token->hasPermissionTo('api:write-bookings'));
    }

    // ========================================
    // TOKEN CREATION WITH PERMISSIONS TESTS
    // ========================================

    public function test_owner_can_create_token_with_permissions(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('bands.apiTokens.store', $this->band), [
                'name' => 'Wix Integration',
                'permissions' => ['api:read-events', 'api:read-bookings'],
            ]);

        $response->assertSessionHas('new_api_token');
        $newToken = session('new_api_token');
        $this->assertNotNull($newToken['token']);

        $token = BandApiToken::where('band_id', $this->band->id)
            ->where('name', 'Wix Integration')
            ->first();

        $this->assertNotNull($token);
        $this->assertTrue($token->hasPermissionTo('api:read-events'));
        $this->assertTrue($token->hasPermissionTo('api:read-bookings'));
        $this->assertFalse($token->hasPermissionTo('api:write-events'));
    }

    public function test_owner_can_create_token_with_all_permissions(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('bands.apiTokens.store', $this->band), [
                'name' => 'Full Access Token',
                'permissions' => [
                    'api:read-events',
                    'api:write-events',
                    'api:read-bookings',
                    'api:write-bookings',
                ],
            ]);

        $response->assertSessionHas('new_api_token');

        $token = BandApiToken::where('band_id', $this->band->id)
            ->where('name', 'Full Access Token')
            ->first();

        $this->assertNotNull($token);
        $this->assertTrue($token->hasPermissionTo('api:read-events'));
        $this->assertTrue($token->hasPermissionTo('api:write-events'));
        $this->assertTrue($token->hasPermissionTo('api:read-bookings'));
        $this->assertTrue($token->hasPermissionTo('api:write-bookings'));
    }

    public function test_owner_can_create_token_without_permissions(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('bands.apiTokens.store', $this->band), [
                'name' => 'No Permissions Token',
                'permissions' => [],
            ]);

        $response->assertSessionHas('new_api_token');

        $token = BandApiToken::where('band_id', $this->band->id)
            ->where('name', 'No Permissions Token')
            ->first();

        $this->assertNotNull($token);
        $this->assertCount(0, $token->permissions);
    }

    public function test_cannot_create_token_with_invalid_permission(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('bands.apiTokens.store', $this->band), [
                'name' => 'Invalid Permission Token',
                'permissions' => ['api:invalid-permission'],
            ]);

        $response->assertSessionHasErrors('permissions.0');
    }

    // ========================================
    // TOKEN MANAGEMENT TESTS
    // ========================================

    public function test_disabled_token_cannot_access_endpoints_even_with_permissions(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['read-events'],
        ]);
        $this->createTestBookingWithEvent();

        // Disable the token
        $token->update(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or inactive API token',
            ]);
    }

    public function test_token_permissions_are_scoped_to_band(): void
    {
        // Create first band and token
        [$token1, $plainTextToken1] = $this->createTokenWithPermissions([
            $this->permissions['read-bookings'],
        ]);
        [$booking1, $event1] = $this->createTestBookingWithEvent();

        // Create second band with its own booking
        $band2 = Bands::factory()->create();
        $booking2 = Bookings::factory()->create([
            'band_id' => $band2->id,
            'name' => 'Band 2 Event',
            'date' => '2025-12-02',
            'status' => 'confirmed',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking2->id,
            'title' => 'Band 2 Event',
            'date' => '2025-12-02',
        ]);

        // Token1 should only see Band1's events
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken1,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(200);
        $bookings = $response->json('bookings');

        $this->assertCount(1, $bookings);
        $this->assertEquals($booking1->name, $bookings[0]['name']);
        $this->assertEquals($this->band->id, $response->json('band.id'));
    }

    // ========================================
    // PERMISSION PERSISTENCE TESTS
    // ========================================

    public function test_token_permissions_persist_after_token_toggle(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['read-events'],
        ]);

        // Disable and re-enable the token
        $token->update(['is_active' => false]);
        $token->update(['is_active' => true]);

        // Permissions should still exist
        $token->refresh();
        $this->assertTrue($token->hasPermissionTo('api:read-events'));
    }

    public function test_permissions_are_deleted_when_token_is_deleted(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['read-events'],
            $this->permissions['write-events'],
        ]);

        $tokenId = $token->id;

        // Delete the token
        $token->delete();

        // Check that permissions are cleaned up
        $this->assertDatabaseMissing('model_has_permissions', [
            'model_id' => $tokenId,
            'model_type' => BandApiToken::class,
        ]);
    }

    // ========================================
    // EDGE CASES AND SECURITY TESTS
    // ========================================

    public function test_permission_check_is_case_sensitive(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['read-events'],
        ]);

        // Exact match should work
        $this->assertTrue($token->hasPermissionTo('api:read-events'));

        // Different case should throw exception or return false
        try {
            $result = $token->hasPermissionTo('API:READ-EVENTS');
            $this->assertFalse($result, 'Permission check should be case-sensitive');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // This is also acceptable - permission doesn't exist with that exact name
            $this->assertTrue(true);
        }
    }

    public function test_token_with_only_write_permission_cannot_read(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['write-events'],
        ]);
        $this->createTestBookingWithEvent();

        // Write-only token should NOT have read access
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/api/booked-dates');

        $response->assertStatus(403);
    }

    public function test_all_api_permissions_exist_in_database(): void
    {
        $expectedPermissions = [
            'api:read-events',
            'api:write-events',
            'api:read-bookings',
            'api:write-bookings',
        ];

        foreach ($expectedPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'api_token')
                ->first();

            $this->assertNotNull($permission, "Permission {$permissionName} should exist");
        }
    }

    public function test_tokens_index_shows_permissions(): void
    {
        [$token, $plainTextToken] = $this->createTokenWithPermissions([
            $this->permissions['read-events'],
            $this->permissions['read-bookings'],
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('bands.apiTokens', $this->band));

        $response->assertStatus(200);

        $tokens = $response->viewData('page')['props']['tokens'];
        $createdToken = collect($tokens)->firstWhere('id', $token->id);

        $this->assertNotNull($createdToken);
        $this->assertArrayHasKey('permissions', $createdToken);
        $this->assertContains('api:read-events', $createdToken['permissions']);
        $this->assertContains('api:read-bookings', $createdToken['permissions']);
    }

    public function test_available_permissions_are_passed_to_view(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('bands.apiTokens', $this->band));

        $response->assertStatus(200);

        $availablePermissions = $response->viewData('page')['props']['availablePermissions'];

        $this->assertNotEmpty($availablePermissions);
        $this->assertGreaterThanOrEqual(4, count($availablePermissions));

        $permissionNames = collect($availablePermissions)->pluck('name')->toArray();
        $this->assertContains('api:read-events', $permissionNames);
        $this->assertContains('api:write-events', $permissionNames);
        $this->assertContains('api:read-bookings', $permissionNames);
        $this->assertContains('api:write-bookings', $permissionNames);
    }
}
