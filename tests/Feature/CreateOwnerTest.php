<?php

namespace Tests\Feature;
use App\Models\Bands;
use App\Models\User;
use App\Models\BandOwners;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class CreateOwnerTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_unauthenticated()
    {
        $response = $this->post('/inviteOwner/1',['email'=>'doot']);
        
        $response->assertStatus(302);
    }

    public function test_invalidEmail()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        BandOwners::create([
            'user_id'=>$user->id,
            'band_id'=>$band->id
        ]);

        $response = $this->actingAs($user)->post('/inviteOwner/' . $band->id,['email'=>'badEmail']);

        $response->assertSessionHasErrors(['email']);

    }

    public function test_validEmail()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        BandOwners::create([
            'user_id'=>$user->id,
            'band_id'=>$band->id
        ]);

        $response = $this->actingAs($user)->post('/inviteOwner/' . $band->id,['email'=>'test@user.com']);

        $response->assertSessionHas(['successMessage']);
        $this->assertDatabaseHas('invitations',[
            'email'=>'test@user.com',
            'invite_type_id'=>1,
            'pending'=>true
        ]);
    }
}
