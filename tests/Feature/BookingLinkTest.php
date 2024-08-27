<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bands;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class BookingLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_band_owner_can_see_booking_link()
    {
        $band = Bands::factory()->hasOwners(1)->create();
        $owner = $band->owners->first()->user;

        $response = $this->actingAs($owner)
            ->get('/dashboard');



        $response->assertInertia(function (Assert $assert)
        {
            $assert->has('auth.user.navigation', function (Assert $assert)
            {
                $assert->where('Booking', true)
                    ->etc();
            });
        });
    }

    public function test_band_member_can_see_booking_link()
    {
        $band = Bands::factory()->hasMembers(1)->create();
        $member = $band->members->first()->user;

        $response = $this->actingAs($member)
            ->get('/dashboard');

        $response->assertInertia(function (Assert $assert)
        {
            $assert->has('auth.user.navigation', function (Assert $assert)
            {
                $assert->where('Booking', true)
                    ->etc();
            });
        });
    }

    public function test_non_band_user_cannot_see_booking_link()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/dashboard');

        $response->assertInertia(function (Assert $assert)
        {
            $assert->has('auth.user.navigation', function (Assert $assert)
            {
                $assert->where('Booking', false)
                    ->etc();
            });
        });
    }
}
