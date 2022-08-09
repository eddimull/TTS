<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Bands;

class CanCreateEventsTest extends TestCase
{
    // use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_can_createEvent()
    {
        $band = Bands::factory()->hasOwner()->create();
        $user = $band->owner[0]->user;
        $testEventName = "Test ElectricBoogaloo";
        $response = $this->actingAs($user)->post('/events/',[
            "address_street"=> "417 Jefferson Street",
            "backline_provided"=> false,
            "band_id"=> $band->id,
            "band_loadin_time"=> "2022-08-18T21:00:00.000Z",
            "bouquet_garter"=> "",
            "ceremony_time"=> "2022-08-18T23:00:00.000Z",
            "city"=> "Lafayette",
            "colorway_id"=> "",
            "colorway_text"=> "<p>Test</p>",
            "created_at"=> "",
            "depositReceived"=> "",
            "end_time"=> "2022-08-19T04:00:00.000Z",
            "event_key"=> "",
            "event_name"=> $testEventName,
            "event_time"=> "2022-08-19T00:00:00.000Z",
            "event_type_id"=> 3,
            "father_daughter"=> "",
            "first_dance"=> "",
            "lodging"=> "",
            "money_dance"=> "",
            "mother_groom"=> "",
            "notes"=> "<p>Test</p>",
            "onsite"=> "",
            "outdoors"=> "",
            "pay"=> "0",
            "production_loadin_time"=> "2022-08-18T19:00:00.000Z",
            "production_needed"=> false,
            "public"=> "",
            "quiet_time"=> "2022-08-18T23:00:00.000Z",
            "rhythm_loadin_time"=> "2022-08-18T20:00:00.000Z",
            "state_id"=> 19,
            "updated_at"=> "",
            "venue_name"=> "The Grouse Room",
            "zip"=> "70501"
        ]);

        $this->withoutExceptionHandling();
        // dd($response);
        $response->assertStatus(302);
        $this->assertDatabaseHas('band_events',[
            'event_name'=>$testEventName
        ]);
    }
}
