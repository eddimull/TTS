<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;

class BandAttributesTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_getEveryone()
    {
        $band = Bands::factory()->create();
        $members = User::factory(rand(1,10))->create();
        $owners = User::factory(rand(1,10))->create();
        foreach($members as $member)
        {
            BandMembers::create([
                'band_id'=>$band->id,
                'user_id'=>$member->id
            ]);
        }

        foreach($owners as $owner)
        {
            BandOwners::create([
                'band_id'=>$band->id,
                'user_id'=>$owner->id
            ]);
        }
        
        $this->assertEquals(count($members)+count($owners), count($band->everyone()));
    }
}
