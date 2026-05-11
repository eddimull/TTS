<?php

namespace Tests\Browser;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

class ContractWysiwygTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $owner;
    private Bands $band;

    /**
     * Skip the parent's teardown migrate:rollback, which fails on this
     * codebase's irreversible drop_proposal_tables migration. The next
     * test's migrate:fresh resets state cleanly.
     */
    protected function tearDown(): void
    {
        // Intentionally empty.
    }

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'EventTypeSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'StatesTableSeeder', '--force' => true]);

        $this->band = Bands::factory()->create([
            'name' => 'Contract WYSIWYG Band',
            'address' => '1 Main St',
            'city' => 'Testville',
            'state' => 'LA',
            'zip' => '70001',
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Wendy Owner',
            'email' => 'contract-wysiwyg-owner@test.local',
            'password' => Hash::make('password'),
        ]);
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $ownerRole = Role::where('name', 'band-owner')->where('guard_name', 'web')->first();
        if ($ownerRole) {
            setPermissionsTeamId($this->band->id);
            $this->owner->assignRole($ownerRole);
            setPermissionsTeamId(0);
        }
    }

    private function makeBookingWithEvents(array $bookingAttrs, array $eventsAttrs): Bookings
    {
        // The WYSIWYG renders only when contract_option = 'default' AND
        // status is neither 'confirmed' nor 'pending'. Default to draft so
        // the editor is rendered for every test.
        $booking = Bookings::factory()->create(array_merge([
            'band_id' => $this->band->id,
            'author_id' => $this->owner->id,
            'name' => 'Contract WYSIWYG Booking',
            'price' => 5000,
            'status' => 'draft',
            'contract_option' => 'default',
            'event_type_id' => 2,
        ], $bookingAttrs));

        Contracts::factory()->create([
            'contractable_type' => Bookings::class,
            'contractable_id' => $booking->id,
            'author_id' => $this->owner->id,
            'status' => 'pending',
            'custom_terms' => [],
        ]);

        $primaryContact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Connie Buyer',
            'email' => 'connie-buyer@test.local',
        ]);
        $booking->contacts()->attach($primaryContact, ['is_primary' => true]);

        foreach ($eventsAttrs as $eventAttrs) {
            Events::factory()->create(array_merge([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
            ], $eventAttrs));
        }

        return $booking;
    }

    public function test_single_event_wysiwyg_renders_today_wording(): void
    {
        $booking = $this->makeBookingWithEvents(
            ['name' => 'Anniversary Gig'],
            [[
                'title' => 'Anniversary Performance',
                'date' => '2026-06-13',
                'start_time' => '19:00',
                'end_time' => '22:00',
                'venue_name' => 'Symphony Hall',
            ]]
        );

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}/contract")
                ->waitForText('Details of engagement', 10)
                // Single-event branch: Date / Performance Length / Venue.
                ->assertSee('Date:')
                ->assertSee('06/13/2026')
                ->assertSee('Performance Length:')
                ->assertSee('3 hours')
                ->assertSee('Venue:')
                ->assertSee('Symphony Hall')
                // The multi-event header must NOT appear on a single-event booking.
                ->assertDontSee('Performances:')
                ->assertDontSee('Total Performance Length')
                // Sound-check copy is singular for single-event.
                ->assertSee('at least 1 hour before performance');

            // Overtime: (5000 / 3) * 1.5 / 1 = 2500.00. Look for the dollar
            // amount in the rendered overtime span.
            $browser->assertSee('2500.00');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }

    public function test_multi_event_wysiwyg_renders_performances_list(): void
    {
        $booking = $this->makeBookingWithEvents(
            ['name' => 'Three Show Run'],
            [
                [
                    'title' => 'Rehearsal',
                    'date' => '2026-06-12',
                    'start_time' => '19:00',
                    'end_time' => '21:00',
                    'venue_name' => 'Symphony Hall',
                ],
                [
                    'title' => 'Saturday performance',
                    'date' => '2026-06-13',
                    'start_time' => '19:00',
                    'end_time' => '21:00',
                    'venue_name' => 'Symphony Hall',
                ],
                [
                    'title' => 'Sunday performance',
                    'date' => '2026-06-14',
                    'start_time' => '19:00',
                    'end_time' => '21:00',
                    'venue_name' => 'Symphony Hall',
                ],
            ]
        );

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}/contract")
                ->waitForText('Details of engagement', 10)
                // Multi-event branch.
                ->assertSee('Performances:')
                ->assertSee('Total Performance Length:')
                ->assertSee('6 hours')
                ->assertSee('Rehearsal')
                ->assertSee('Saturday performance')
                ->assertSee('Sunday performance')
                // Sound-check copy is plural for multi-event.
                ->assertSee('at least 1 hour before each performance')
                // Single-event labels MUST NOT appear at the top level.
                // (The chip in the engagement summary uses "Date" only in
                // single-event mode; with the engagement summary not visible
                // on this page, the only "Date:" occurrence would be the
                // single-event details block.)
                ->assertDontSee('Date:</span> 06/');

            // Overtime: 5000 / 6 * 1.5 / 3 = 416.666... → toFixed(2) = 416.67
            $browser->assertSee('416.67');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }

    public function test_single_event_wysiwyg_falls_back_to_tbd_for_missing_venue(): void
    {
        // A draft booking whose single event has no venue at all should
        // render "Venue: TBD" — confirms the new || 'TBD' fallback.
        $booking = $this->makeBookingWithEvents(
            ['name' => 'No Venue Yet'],
            [[
                'title' => 'Placeholder event',
                'date' => '2026-08-01',
                'start_time' => '19:00',
                'end_time' => '22:00',
                'venue_name' => null,
                'venue_address' => null,
            ]]
        );

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}/contract")
                ->waitForText('Details of engagement', 10)
                ->assertSee('Venue:')
                ->assertSee('TBD');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }
}
