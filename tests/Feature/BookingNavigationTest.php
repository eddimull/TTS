<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use Tests\TestCase;

class BookingNavigationTest extends TestCase
{
    /**
     * Can navigate to the route.
     *
     * @return void
     */
    public function test_owner_can_show_bookings()
    {
        $band = Bands::factory()->hasOwners(1)->create();
        $owner = $band->owners->first()->user;
        $response = $this->actingAs($owner)->get('/bookings');
        $response->assertStatus(200);
    }

    public function test_member_can_show_bookings()
    {
        $band = Bands::factory()->hasMembers(1)->create();
        $member = $band->members->first()->user;
        setPermissionsTeamId($band->id);
        $member->givePermissionTo('read_bookings');
        $response = $this->actingAs($member)->get('/bookings');
        $response->dump();
        $response->assertStatus(200);
    }

    public function test_non_band_user_cannot_show_bookings()
    {
        $response = $this->get('/bookings');
        $response->assertStatus(302);
    }
}
