<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\Bands;
use App\Models\User;
use Tests\TestCase;

class CreateBandTest extends TestCase
{
    use RefreshDatabase;
    public function test_createInvalidBand()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/bands',['site_name'=>'TTS_Test']);
        $response->assertSessionHasErrors();
    }
    public function test_createBand()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bands',['name'=>'Three Thirty Seven', 'site_name'=>'TTS_Test']);

        $this->assertDatabaseHas('bands',[
            'name'=>'Three Thirty Seven'
        ]);
    }

    public function test_createDuplicateBand()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/bands',['site_name'=>'TTS_Test']);

        $response->assertSessionHasErrors();
    }
}
