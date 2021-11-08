<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\Proposals;

class CanCreateProposalContactTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_canAddContact()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id'=>$band->id,
            'user_id'=>$user->id
        ]);
        $proposal = Proposals::factory()->create([
            'band_id'=>$band->id,
            'author_id'=>$user->id
        ]);

        $response = $this->actingAs($user)->post('/proposals/createContact/' . $proposal->key,[
            'email' => "test@usertest.com",
            'name' => "Test",
            'phonenumber' => "Test"
        ]);

        $response->assertSessionHas(['successMessage']);
        $this->assertDatabaseHas('proposal_contacts',[
            'proposal_id'=>$proposal->id,
            'email'=>'test@usertest.com'
        ]);
    }
}
