<?php

namespace Tests\Feature;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CanCreateProposalTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_can_create_proposal()
    {
        $band = Bands::factory()->hasOwner()->create();
        $user = $band->owner[0]->user;
        
        $response = $this->actingAs($user)->post('/proposals/' . $band->site_name . '/create',[
            'date'=> "2021-12-06T01:00:32.388Z",
            'event_type_id'=> 2,
            'hours'=> "5",
            'name'=> "Test ElectricBoogaloo",
            'notes'=> "",
            'price'=> 1000,
        ]);

        // dd($response);
        $response->assertStatus(302);
        $this->assertDatabaseHas('proposals',[
            'name'=>'Test ElectricBoogaloo'
        ]);
    }
}
