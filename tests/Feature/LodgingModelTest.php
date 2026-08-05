<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Lodging;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_lodging_has_rooms_and_cascades_delete(): void
    {
        $band = Bands::factory()->create();
        $lodging = Lodging::create([
            'band_id'      => $band->id,
            'name'         => 'Hampton Inn',
            'check_in_at'  => now()->addDay(),
            'check_out_at' => now()->addDays(2),
        ]);
        $lodging->rooms()->create(['label' => 'King', 'confirmation_number' => 'ABC123', 'sort_order' => 0]);

        $this->assertCount(1, $lodging->fresh()->rooms);

        $lodging->forceDelete();
        $this->assertDatabaseCount('lodging_rooms', 0);
    }

    public function test_lodging_can_link_booking(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $lodging = Lodging::create([
            'band_id'      => $band->id,
            'name'         => 'Hotel',
            'check_in_at'  => now(),
            'check_out_at' => now()->addDay(),
            'booking_id'   => $booking->id,
        ]);
        $this->assertTrue($lodging->booking->is($booking));
        $this->assertTrue($booking->lodgings->first()->is($lodging));
    }

    public function test_token_abilities_include_lodging_for_owner(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $abilities = app(TokenService::class)->buildAbilities($user->fresh());
        $this->assertContains('read:lodging', $abilities);
        $this->assertContains('write:lodging', $abilities);
    }
}
