<?php

namespace Tests\Browser;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

class BookingItemizationTest extends DuskTestCase
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

        $this->band = Bands::factory()->create(['name' => 'Itemization Band']);

        $this->owner = User::factory()->create([
            'name' => 'Iris Owner',
            'email' => 'itemization-owner@test.local',
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

    private function makeMultiEventBooking(string $name = 'Symphony Hire'): Bookings
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'author_id' => $this->owner->id,
            'name' => $name,
            'price' => 5000, // dollars; Price cast multiplies by 100 on write
            'status' => 'pending',
        ]);

        // A primary contact is required for some controller code paths.
        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Engagement Contact',
            'email' => 'engagement-contact@test.local',
        ]);
        $booking->contacts()->attach($contact, ['is_primary' => true]);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Rehearsal',
            'date' => '2026-06-12',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'venue_name' => 'Symphony Hall',
            'price' => 0,
        ]);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Saturday performance',
            'date' => '2026-06-13',
            'start_time' => '19:30',
            'end_time' => '22:30',
            'venue_name' => 'Symphony Hall',
            'price' => 0,
        ]);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Sunday performance',
            'date' => '2026-06-14',
            'start_time' => '14:00',
            'end_time' => '17:00',
            'venue_name' => 'Symphony Hall',
            'price' => 0,
        ]);

        return $booking;
    }

    private function makeSingleEventBooking(string $name = 'Anniversary Gig'): Bookings
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'author_id' => $this->owner->id,
            'name' => $name,
            'price' => 1200,
            'status' => 'pending',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Single Event Contact',
            'email' => 'single-event-contact@test.local',
        ]);
        $booking->contacts()->attach($contact, ['is_primary' => true]);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Anniversary',
            'date' => '2026-07-04',
            'start_time' => '19:00',
            'end_time' => '23:00',
            'venue_name' => 'Lakeshore Club',
            'price' => 0,
        ]);

        return $booking;
    }

    public function test_multi_event_booking_detail_shows_engagement_summary_and_chip(): void
    {
        $booking = $this->makeMultiEventBooking();

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Symphony Hire', 10)
                ->assertSee('Symphony Hire')
                // EngagementSummary subtitle: "3 events · {range} · {venue}"
                ->assertSee('3 events')
                ->assertSee('Symphony Hall');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }

    public function test_single_event_booking_detail_does_not_show_multi_event_chip(): void
    {
        $booking = $this->makeSingleEventBooking();

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Anniversary Gig', 10)
                ->assertSee('Anniversary Gig')
                // EngagementSummary subtitle should read "1 event" (singular)
                ->assertSee('1 event')
                ->assertDontSee('2 events')
                ->assertDontSee('3 events');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }

    public function test_multi_event_payout_shows_itemization_section(): void
    {
        $booking = $this->makeMultiEventBooking();

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Itemized by event', 10)
                ->assertSee('Itemized by event')
                ->assertSee('Itemized total')
                ->assertSee('Booking total');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }

    public function test_single_event_payout_does_not_show_itemization_section(): void
    {
        $booking = $this->makeSingleEventBooking();

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Anniversary Gig', 10)
                ->assertDontSee('Itemized by event');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }

    public function test_setting_a_per_event_price_persists_to_the_event(): void
    {
        $booking = $this->makeMultiEventBooking();

        $firstEvent = $booking->events()->orderBy('date')->orderBy('id')->first();

        $this->browse(function (Browser $browser) use ($booking, $firstEvent) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Itemized by event', 10);

            // Headless-chromium + Vue v-model + Dusk's native typing produces
            // flaky results for triggering @change, so we drive the save
            // through the same Inertia client that the component uses. This
            // still exercises the auth, route, controller, and event model
            // wiring — it just bypasses the native input interaction.
            $url = route('Update Booking Event', [$booking->band_id, $booking->id, $firstEvent->id]);
            $payload = json_encode([
                'title'          => $firstEvent->title,
                'date'           => $firstEvent->date->format('Y-m-d'),
                'start_time'     => $firstEvent->start_time?->format('H:i'),
                'end_time'       => $firstEvent->end_time?->format('H:i'),
                'venue_name'     => $firstEvent->venue_name,
                'venue_address'  => $firstEvent->venue_address,
                'price'          => 1500,
                'roster_id'      => $firstEvent->roster_id,
                'notes'          => $firstEvent->notes,
                'silent'         => true,
            ]);

            // Fire the Inertia request the component would fire; capture
            // resolution so the test waits before continuing.
            $browser->script(<<<JS
                window.__itemizationDone = false;
                window.__itemizationError = null;
                window.__itemizationStatus = null;
                window.axios.put('$url', JSON.parse('$payload'), {
                    headers: { 'X-Inertia': 'true' }
                }).then((res) => {
                    window.__itemizationStatus = res.status;
                    window.__itemizationDone = true;
                }).catch((err) => {
                    window.__itemizationStatus = err.response?.status ?? null;
                    window.__itemizationError = JSON.stringify(err.response?.data ?? err.message);
                    window.__itemizationDone = true;
                });
            JS);

            $browser->waitUsing(10, 250, function () use ($browser) {
                return $browser->script('return window.__itemizationDone === true;')[0];
            });

            $error = $browser->script('return window.__itemizationError;')[0];
            // Axios sometimes resolves redirect responses through .catch() with
            // an empty body; treat null OR empty-string as success here.
            $this->assertContains($error, [null, '', '""'], "PUT failed unexpectedly: {$error}");
        });

        $firstEvent->refresh();
        $this->assertEquals('1500.00', $firstEvent->price);
    }

    public function test_invalid_per_event_price_is_rejected_by_the_backend(): void
    {
        $booking = $this->makeMultiEventBooking();
        $firstEvent = $booking->events()->orderBy('date')->orderBy('id')->first();
        $originalPrice = $firstEvent->price;

        $this->browse(function (Browser $browser) use ($booking, $firstEvent) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Itemized by event', 10);

            $url = route('Update Booking Event', [$booking->band_id, $booking->id, $firstEvent->id]);
            $payload = json_encode([
                'title'          => $firstEvent->title,
                'date'           => $firstEvent->date->format('Y-m-d'),
                'start_time'     => $firstEvent->start_time?->format('H:i'),
                'end_time'       => $firstEvent->end_time?->format('H:i'),
                'venue_name'     => $firstEvent->venue_name,
                'venue_address'  => $firstEvent->venue_address,
                'price'          => -50,
                'roster_id'      => $firstEvent->roster_id,
                'notes'          => $firstEvent->notes,
                'silent'         => true,
            ]);

            $browser->script(<<<JS
                window.__itemizationDone = false;
                window.__itemizationError = null;
                window.__itemizationStatus = null;
                window.axios.put('$url', JSON.parse('$payload'), {
                    headers: { 'X-Inertia': 'true' }
                }).then((res) => {
                    window.__itemizationStatus = res.status;
                    window.__itemizationDone = true;
                }).catch((err) => {
                    window.__itemizationStatus = err.response?.status ?? null;
                    window.__itemizationError = JSON.stringify(err.response?.data ?? err.message);
                    window.__itemizationDone = true;
                });
            JS);

            $browser->waitUsing(10, 250, function () use ($browser) {
                return $browser->script('return window.__itemizationDone === true;')[0];
            });

            $error = $browser->script('return window.__itemizationError;')[0];
            $this->assertNotNull($error, 'Expected the backend to reject a negative price.');
            $this->assertStringContainsString('price', strtolower($error), 'Error payload should mention the rejected price field.');
        });

        $firstEvent->refresh();
        $this->assertEquals($originalPrice, $firstEvent->price, 'Rejected save must not change the event price.');
    }
}
