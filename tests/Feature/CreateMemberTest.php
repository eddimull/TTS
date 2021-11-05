<?php

namespace Tests\Feature;
use App\Models\Bands;
use App\Models\User;
use App\Models\BandOwners;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class CreateMemberTest extends TestCase
{

    private $user;
    private $band;

    protected function setupBandAndUser()
    {
        $this->band = Bands::factory()->create();
        $this->user = User::factory()->create();
        BandOwners::create([
            'user_id'=>$this->user->id,
            'band_id'=>$this->band->id
        ]);
    }
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_unauthenticated()
    {
        $response = $this->post('/inviteMember/1',['email'=>'doot']);
        
        $response->assertStatus(302);
    }

    public function test_invalidEmail()
    {

        $this->setupBandAndUser();

        $response = $this->actingAs($this->user)->post('/inviteMember/' . $this->band->id,['email'=>'badEmail']);

        $response->assertSessionHasErrors(['email']);

    }

    public function test_validEmail()
    {
      
        $this->setupBandAndUser();
        $response = $this->actingAs($this->user)->post('/inviteMember/' . $this->band->id,['email'=>'test@user.com']);

        $response->assertSessionHas(['successMessage']);
        $this->assertDatabaseHas('invitations',[
            'email'=>'test@user.com',
            'invite_type_id'=>2,
            'pending'=>true
        ]);
        
    }
}
