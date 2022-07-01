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
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_getEveryone()
    {
        Bands::factory()->count(10)->create();
        $count = rand(1,10);
        $band = Bands::factory()->hasOwners($count)->hasMembers($count)->create();
        // dd($count,count($band->members),count($band->owners), $band->everyone());
        $this->assertEquals(count($band->members)+count($band->owners), count($band->everyone()));
    }
}
