<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Bands;
use App\Models\User;
use App\Models\BandOwners;
use App\Models\userPermissions;

class CanEditMemberPermissionsTest extends TestCase
{
    use RefreshDatabase;
    private $user;
    private $band;
    private $member;

    protected function setupBandAndUser()
    {
        $this->band = Bands::factory()->create();
        $this->user = User::factory()->create();
        $this->member = User::factory()->create([
            'email'=>'testPermissionsUser@gmail.com'
        ]);
        BandOwners::create([
            'user_id'=>$this->user->id,
            'band_id'=>$this->band->id
        ]);

        BandMembers::create([
            'user_id'=>$this->member->id,
            'band_id'=>$this->band->id
        ]);
    }
    public function test_cannotUpdatePermissionsAsRandomUser()
    {
        $this->setupBandAndUser();
        $randomUser = User::factory()->create();

        
        $response = $this->actingAs($randomUser)->post('/permissions/' . $this->band->id . '/' . $this->member->id,[
            'permissions'=>[
                'read_colors'=> true,
                'write_colors'=> true
            ]
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('user_permissions',[
            
                'user_id'=>$this->member->id,
                'band_id'=>$this->band->id,
                'read_colors'=>true,
                'write_colors'=>true
            
        ]);
    }
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_canUpdatePermissionsAsBandOwner()
    {
        $this->setupBandAndUser();

        
        $response = $this->actingAs($this->user)->post('/permissions/' . $this->band->id . '/' . $this->member->id,[
            'permissions'=>[
                'read_colors'=> true,
                'write_colors'=> true
            ]
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('successMessage');
        $this->assertDatabaseHas('user_permissions',[
            
                'user_id'=>$this->member->id,
                'band_id'=>$this->band->id,
                'read_colors'=>true,
                'write_colors'=>true
            
        ]);
    }
}
