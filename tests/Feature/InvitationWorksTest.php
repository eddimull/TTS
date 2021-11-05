<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Bands;
use App\Models\Invitations;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\BandOwners;
use Illuminate\Support\Str;


class InvitationWorksTest extends TestCase
{
    use RefreshDatabase;
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
    public function test_memberInvitationWorksForNewUser()
    {
        $this->setupBandAndUser();

        Invitations::create([
            'email'=>'test12345@example.com',
            'band_id'=>$this->band->id,
            'invite_type_id'=>2
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test12345@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $recentlyCreatedUser = Auth::user();
        $this->assertDatabaseHas('invitations',[
            'email'=>$recentlyCreatedUser->email,
            'band_id'=>$this->band->id,
            'pending'=>false
        ]);
        $this->assertDatabaseHas('band_members',[
            'user_id'=>$recentlyCreatedUser->id,
            'band_id'=>$this->band->id,
        ]);
    }

    public function test_memberInvitationWorksForExistingUser()
    {
        $this->setupBandAndUser();
        $existingUser = User::create([
            'email'=>'eddimull@yahoo.com',
            'name'=>'Its me',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10)            
        ]); 
                
        $response = $this->actingAs($this->user)->post('/inviteMember/' . $this->band->id,['email'=>$existingUser->email]);
        
        $response->assertSessionHas(['successMessage']);
        
        $this->assertDatabaseHas('band_members',[
            'user_id'=>$existingUser->id,
            'band_id'=>$this->band->id,
        ]);
    }  

    public function test_ownerInvitationWorksForNewUser()
    {
        $this->setupBandAndUser();

        Invitations::create([
            'email'=>'test1234@example.com',
            'band_id'=>$this->band->id,
            'invite_type_id'=>1
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test1234@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);
        $recentlyCreatedUser = Auth::user();
        $this->assertDatabaseHas('invitations',[
            'email'=>$recentlyCreatedUser->email,
            'band_id'=>$this->band->id,
            'pending'=>false
        ]);
        $this->assertDatabaseHas('band_owners',[
            'user_id'=>$recentlyCreatedUser->id,
            'band_id'=>$this->band->id,
        ]);
    }

    public function test_ownerInvitationWorksForExistingUser()
    {
        $this->setupBandAndUser();
        $existingUser = User::create([
            'email'=>'eddimulll@yahoo.com',
            'name'=>'Its me again',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10)            
        ]); 
                
        $response = $this->actingAs($this->user)->post('/inviteOwner/' . $this->band->id,['email'=>$existingUser->email]);
        
        $response->assertSessionHas(['successMessage']);

        $this->assertDatabaseHas('band_owners',[
            'user_id'=>$existingUser->id,
            'band_id'=>$this->band->id,
        ]);
    } 
    
}
