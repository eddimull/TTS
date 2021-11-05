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
    public function test_example()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id'=>$band->id,
            'user_id'=>$user->id
        ]);
        // dd($band->site_name);
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
