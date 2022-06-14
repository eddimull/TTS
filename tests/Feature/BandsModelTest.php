<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\Bands;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use Tests\TestCase;

class BandsModelTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_band_has_multiple_members()
    {
        $numberOfMembers = 5;
        $band = Bands::factory()->create();

        $users = User::factory()->count($numberOfMembers)->create();

        foreach($users as $user)
        {
            BandMembers::create([
                'user_id'=>$user->id,
                'band_id'=>$band->id
            ]);
        }

        $band->refresh();

        $this->assertEquals($numberOfMembers,count($band->members));
    }
}
