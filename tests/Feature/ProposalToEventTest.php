<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\Proposals;
use App\Services\ProposalServices;
use Database\Seeders\StatesTableSeeder;
use App\Models\State;

class ProposalToEventTest extends TestCase
{
    private function addStates()
    {
        $state = State::first();
        if($state === null)
        {
            $seeder = new StatesTableSeeder();
            $seeder->run();
        }
    }
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_proposalWritesToEvents()
    {
        $this->addStates();
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

        $service = new ProposalServices($proposal);

        $service->writeToCalendar();

        $this->assertDatabaseHas('band_events',['event_name'=>$proposal->name]);
        // $response = $this->actingAs($user)->post('/proposals/createContact/' . $proposal->key,[
        //     'email' => "test@usertest.com",
        //     'name' => "Test",
        //     'phonenumber' => "Test"
        // ]);
    }

    public function test_proposalContactsWrittenToEvent()
    {
        $this->addStates();

        $this->addStates();
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
        
        $service = new ProposalServices($proposal);
        $event = $service->writeToCalendar();
        
        $this->assertDatabaseHas('event_contacts',['event_id'=>$event->id]);
    }
}
