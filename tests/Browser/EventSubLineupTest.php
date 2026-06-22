<?php

namespace Tests\Browser;

use App\Models\Bands;
use App\Models\BandRole;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Roster;
use App\Models\RosterSlot;
use App\Models\SubstituteCallList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

/**
 * Browser coverage for adding a substitute to an event from the two UIs that
 * both POST /events/{event}/members:
 *
 *   1. The booking lineup page  (RosterSection.vue — inline "Add Sub")
 *   2. The dashboard event card (Footer.vue — "Roster" modal → empty seat)
 *
 * Regression guard: a duplicate route definition once made the lineup's
 * invite_substitute=true POST hit a controller that emailed the sub but never
 * created the event_member, so the lineup "did nothing" while the dashboard
 * modal worked. These tests assert the sub actually lands in the lineup.
 */
class EventSubLineupTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $owner;
    private Bands $band;
    private Bookings $booking;
    private Events $event;
    private Roster $roster;
    private RosterSlot $slot;
    private RosterSlot $bassSlot;
    private BandRole $role;

    /**
     * Skip the parent's teardown migrate:rollback, which fails on this
     * codebase's irreversible migration. The next test's migrate:fresh
     * resets state cleanly. Matches EventTest.
     */
    protected function tearDown(): void
    {
        // Intentionally do nothing.
    }

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'EventTypeSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'StatesTableSeeder', '--force' => true]);

        $this->band = Bands::factory()->create(['name' => 'Sub Test Band']);

        $this->owner = User::factory()->create([
            'name' => 'Olive Owner',
            'email' => 'sub-lineup-owner@test.local',
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

        $this->role = BandRole::firstOrCreate([
            'band_id' => $this->band->id,
            'name' => 'Guitar',
        ]);

        // A roster with one required, intentionally-empty slot ("Lead Guitar")
        // so the lineup shows an empty seat to fill, plus a second slot that
        // gets a pre-existing member — both the lineup and the dashboard roster
        // dialog only render the slot grid once the event has >= 1 member.
        $this->roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $this->slot = RosterSlot::create([
            'roster_id' => $this->roster->id,
            'band_role_id' => $this->role->id,
            'name' => 'Lead Guitar',
            'quantity' => 1,
            'is_required' => true,
        ]);
        // Shared filler slot — each event seats one member here so the slot grid
        // renders, leaving Lead Guitar as the single empty seat to fill. Created
        // once (not per event) so it doesn't pollute other events' lineups.
        $this->bassSlot = RosterSlot::create([
            'roster_id' => $this->roster->id,
            'band_role_id' => $this->role->id,
            'name' => 'Bass',
            'quantity' => 1,
            'is_required' => false,
        ]);

        // A custom (non-roster) substitute on the band's call list — this is the
        // path that sends invite_substitute=true and was the regressed flow.
        SubstituteCallList::create([
            'band_id' => $this->band->id,
            'instrument' => 'Guitar',
            'band_role_id' => $this->role->id,
            'custom_name' => 'Sidney Sub',
            'custom_email' => 'sidney-sub@test.local',
            'custom_phone' => '555-0100',
            'priority' => 1,
        ]);

        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Sub Test Booking',
        ]);

        $this->event = $this->makeRosterEvent($this->booking, 'Sub Test Event');
    }

    /**
     * Create an event on the shared roster with an empty "Lead Guitar" seat and
     * one filler member (in a Bass slot) so the slot grid renders. The empty
     * Lead Guitar seat is what the tests click to add a sub.
     */
    private function makeRosterEvent(Bookings $booking, string $title): Events
    {
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => $title,
            'roster_id' => $this->roster->id,
            // Future date so the event appears in the dashboard's upcoming window.
            'date' => now()->addDays(30)->toDateString(),
        ]);

        \App\Models\EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'slot_id' => $this->bassSlot->id,
            'band_role_id' => $this->role->id,
            'name' => 'Benny Bass',
            'attendance_status' => 'confirmed',
        ]);

        return $event;
    }

    /**
     * Drive the lineup "Add Sub" flow for $event: open the empty Lead Guitar
     * seat, pick the call-list sub, accept the invite alert.
     */
    private function addCallListSubViaLineup(Browser $browser, Events $event): void
    {
        $url = route('Booking Lineup', [
            'band' => $this->band->id,
            'booking' => $event->eventable_id,
        ], false);

        $browser->visit($url)
            ->waitForText($event->title, 10)
            ->waitForText('Lead Guitar', 10)
            ->waitFor('@empty-seat-add-sub', 10)
            ->click('@empty-seat-add-sub')
            ->waitForText('Add Sub', 5)
            ->waitFor('@sub-list-option', 5)
            ->click('@sub-list-option')
            // A custom call-list sub is also invited, which raises a JS alert.
            ->waitForDialog(5)
            ->acceptDialog()
            ->waitForText('Event Lineup (2)', 10)
            ->assertSee('Sidney Sub');
    }

    /**
     * Lineup page: clicking the inline "Add Sub" on an empty seat and picking a
     * call-list sub adds them to the lineup (and persists the event_member).
     */
    public function test_adding_a_sub_from_the_booking_lineup_adds_them_to_the_event(): void
    {
        $url = route('Booking Lineup', ['band' => $this->band->id, 'booking' => $this->booking->id], false);

        $this->browse(function (Browser $browser) use ($url) {
            $browser->loginAs($this->owner)
                ->visit($url)
                ->waitForText('Sub Test Event', 10)
                // Click the inline "Add Sub" on the empty Lead Guitar seat — the
                // exact flow that was reported as doing nothing.
                ->waitForText('Lead Guitar', 10)
                ->waitFor('@empty-seat-add-sub', 10)
                ->click('@empty-seat-add-sub')
                ->waitForText('Add Sub', 5)
                // Pick the call-list sub from the Sub List tab
                ->waitFor('@sub-list-option', 5)
                ->click('@sub-list-option')
                // A custom call-list sub is also invited, which raises a JS alert.
                ->waitForDialog(5)
                ->assertDialogOpened('Invitation sent to sidney-sub@test.local! They\'ll be added to the band as a substitute once they create an account.')
                ->acceptDialog()
                // The modal closes and the sub now appears in the lineup count + row.
                ->waitForText('Event Lineup (2)', 10)
                ->assertSee('Sidney Sub');
        });

        // Added to the Lead Guitar seat, so it carries that slot_id.
        $this->assertDatabaseHas('event_members', [
            'event_id' => $this->event->id,
            'name' => 'Sidney Sub',
            'email' => 'sidney-sub@test.local',
            'slot_id' => $this->slot->id,
        ]);
    }

    /**
     * Reusing the SAME call-list sub on a DIFFERENT event must add them to that
     * event too — not just send another invitation. This is the second symptom
     * of the route-collision bug: the sub was already invited from event A, so
     * reusing on event B looked like it did nothing.
     */
    public function test_reusing_a_sub_on_a_different_event_adds_them_to_that_event(): void
    {
        // A second, separate booking + event sharing the band, roster and call list.
        $secondBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Second Booking',
        ]);
        $secondEvent = $this->makeRosterEvent($secondBooking, 'Second Event');

        $this->browse(function (Browser $browser) use ($secondEvent) {
            $browser->loginAs($this->owner);

            // First use: add the call-list sub to the original event.
            $this->addCallListSubViaLineup($browser, $this->event);

            // Reuse: add the same call-list sub to a different event.
            $this->addCallListSubViaLineup($browser, $secondEvent);
        });

        // The sub lands on BOTH events.
        $this->assertDatabaseHas('event_members', [
            'event_id' => $this->event->id,
            'name' => 'Sidney Sub',
            'email' => 'sidney-sub@test.local',
            'slot_id' => $this->slot->id,
        ]);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $secondEvent->id,
            'name' => 'Sidney Sub',
            'email' => 'sidney-sub@test.local',
        ]);
    }

    /**
     * Dashboard event card: opening the "Roster" modal, clicking an empty seat,
     * and picking a sub adds them to the event lineup.
     */
    public function test_adding_a_sub_from_the_dashboard_roster_modal_adds_them_to_the_event(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/dashboard')
                ->waitFor('#event_' . $this->event->id, 10)
                ->within('#event_' . $this->event->id, function (Browser $card) {
                    $card->waitFor('@open-roster', 10)
                        ->click('@open-roster');
                })
                // Roster dialog (teleported to body) shows the empty seat
                ->waitForText('Lead Guitar', 10)
                ->waitFor('@empty-seat-add-sub', 10)
                ->click('@empty-seat-add-sub')
                // Sub Picker dialog
                ->waitForText('Add Sub', 5)
                ->waitFor('@sub-picker-option', 5)
                ->click('@sub-picker-option')
                // Sub picker closes; the roster dialog seat now shows the sub.
                // (Footer.addSubToSlot does not invite, so there is no alert.)
                ->waitForText('Sidney Sub', 10)
                ->assertSee('Sidney Sub');
        });

        $this->assertDatabaseHas('event_members', [
            'event_id' => $this->event->id,
            'name' => 'Sidney Sub',
            'email' => 'sidney-sub@test.local',
            'slot_id' => $this->slot->id,
        ]);
    }
}
