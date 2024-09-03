<?php

namespace Tests\Feature;

use App\Models\Bookings;
use App\Models\User;
use App\Models\Bands;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\userPermissions;

class BookingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private $band;
    private $owner;
    private $member;
    private $nonMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->nonMember = User::factory()->create();

        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->member->id]);
    }

    public function test_owner_can_view_bookings_index()
    {
        $bookings = Bookings::factory()->count(3)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->get(route('bookings.index', $this->band));


        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Index')
                ->has('bookings', 3)
                ->has('bands')
        );
    }

    public function test_member_can_view_bookings_index()
    {
        $bookings = Bookings::factory()->count(3)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->member)->get(route('bookings.index', $this->band));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Index')
                ->has('bookings', 3)
        );
    }

    public function test_owner_can_create_booking()
    {
        $duration = 4;
        $bookingData = Bookings::factory()->duration($duration)->make(['band_id' => $this->band->id])->toArray();
        $bookingData['duration'] = $duration;
        $bookingData['start_time'] = Carbon::parse($bookingData['start_time'])->format('H:i');
        unset($bookingData['end_time']);
        // dd($bookingData);
        $response = $this->actingAs($this->owner)->post(route('bands.booking.store', $this->band), $bookingData);

        unset($bookingData['duration']);
        // $response->assertRedirect(route('bands.booking.show', [$this->band, $bookingData]));
        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', $bookingData);
    }

    public function test_member_can_create_booking()
    {
        $duration = 2;

        userPermissions::create([
            'user_id' => $this->member->id,
            'band_id' => $this->band->id,
            'read_bookings' => true,
            'write_bookings' => true,
        ]);



        $bookingData = Bookings::factory()->duration($duration)->make(['band_id' => $this->band->id])->toArray();
        $bookingData['duration'] = $duration;
        $bookingData['start_time'] = Carbon::parse($bookingData['start_time'])->format('H:i');
        unset($bookingData['end_time']);

        $response = $this->actingAs($this->member)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(302); // Assert that a redirect occurred

        unset($bookingData['duration']);
        $this->assertDatabaseHas('bookings', $bookingData);

        $booking = Bookings::where('band_id', $this->band->id)->latest()->first();

        $this->assertNotNull($booking, 'Booking was not created');

        $response->assertRedirect(route('bands.booking.show', ['band' => $this->band, 'booking' => $booking]));
    }

    public function test_non_member_cannot_create_booking()
    {
        $bookingData = Bookings::factory()->make(['band_id' => $this->band->id])->toArray();

        $response = $this->actingAs($this->nonMember)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('bookings', $bookingData);
    }

    public function test_owner_can_update_booking()
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $updatedData = Bookings::factory()->make(['band_id' => $this->band->id])->toArray();

        $response = $this->actingAs($this->owner)->put(route('bands.booking.update', [$this->band, $booking]), $updatedData);

        $response->assertRedirect(route('bookings.index', $this->band));
        $this->assertDatabaseHas('bookings', $updatedData);
    }

    public function test_owner_can_delete_booking()
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->delete(route('bands.booking.destroy', [$this->band, $booking]));

        $response->assertRedirect(route('bookings.index', $this->band));
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }
}
