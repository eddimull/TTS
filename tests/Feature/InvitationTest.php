<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\Bands;
use App\Models\Invitations;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;


class InvitationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_InvitationHasKey()
    {
        $band = Bands::factory()->create();
        $invitation = Invitations::factory([
            'band_id'=>$band->id
        ])->create();
        
        $this->assertDatabaseHas('invitations',['key'=>$invitation->key]);
    }
}
