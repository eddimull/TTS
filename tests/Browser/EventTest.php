<?php

namespace Tests\Browser;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EventTest extends DuskTestCase
{
    public $time = 1234567890;
    
    /** @test */
    public function can_go_to_events()
    {
        
        $user = User::find(1);
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/events')
                    ->assertSee('Create Event');
        });
    }

        /** @test */
        public function can_add_event()
        {
            $time = $this->time;
            echo 'this is the time: ' .$time;
            $this->browse(function (Browser $browser) use ($time) {
                $browser->visit('/events/create')
                        ->assertSee('Event Information')
                        ->select('#bandDropdown','1')
                        ->type('#name','Test Event ' . $time)
                        ->assertDontSee('First Dance')
                        ->assertDontSee('Father / Daughter Dance')
                        ->assertDontSee('Mother / Groom Dance')
                        ->assertDontSee('Money Dance')
                        ->assertDontSee('Bouquet / Garter')
                        ->select('#productionDropdown','1')
                        ->assertSee('First Dance')
                        ->assertSee('Father / Daughter Dance')
                        ->assertSee('Mother / Groom Dance')
                        ->assertSee('Money Dance')
                        ->assertSee('Bouquet / Garter')
                        ->type('#venueName','Test Venue ' . $time)
                        ->type('#streetAddress','219 Mimosa Place')
                        ->type('#city','Lafayette')
                        ->select('#stateDropdown','19')
                        ->type('#zipCode','70506')
                        ->type('#notesTextArea','Testing Notes at ' . $time)
                        ->type('#pay','9000')
                        ->select('#colorway',5)
                        ->click('#eventDate')
                        ->pause(100)
                        ->click('.p-datepicker-today')
                        ->pause(50)
                        ->click('#autoFillButton')
                        ->click('button[type="submit"]')
                        ->pause(1000)
                        ->assertSee('Test Event ' . $time);
            });
        }

         /** @test */
        public function can_update_event()
        {
            $time = $this->time;
            $this->browse(function(Browser $browser) use ($time){
                $browser->click('@Test_Event_' . $time)
                                ->pause(100)
                                ->assertSee('Event Information')
                                ->type('#firstDance','Meshuggah - Bleed')
                                ->click('button[type="submit"]')
                                ->pause(1000)
                                ->click('@Test_Event_' . $time)
                                ->pause(100)
                                ->assertValue('#firstDance','Meshuggah - Bleed')
                                ->click('button[type="submit"]')
                                ->pause(1000);
                                
            });
        }

         /** @test */
        public function can_delete_event()
        {
            $time = $this->time;
            $this->browse(function(Browser $browser) use ($time){
                $browser->click('@Test_Event_' . $time)
                                ->pause(100)
                                ->assertSee('Event Information')
                                ->click('#deleteButton')
                                ->waitFor('.swal2-cancel')
                                ->assertSee("You won't be able to revert this!")
                                ->click('.swal2-cancel')
                                ->click('#deleteButton')
                                ->waitFor('.swal2-confirm')
                                ->click('.swal2-confirm')
                                ->pause(5000)
                                ->visit('/events')
                                ->assertSee('Create Event')
                                ->assertDontSee('Test Event ' . $time);
            });
        }
        

        
}
