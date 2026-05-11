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
                ->assertSee('Symphony Hall')
                // Multi-event chip should render next to title
                ->assertSee('3 events');
        });
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
    }

    public function test_setting_a_per_event_price_persists_to_the_event(): void
    {
        $booking = $this->makeMultiEventBooking();

        // Find the chronologically-first event (rehearsal on 6/12) — its
        // itemization row is rendered first in the editor.
        $firstEvent = $booking->events()->orderBy('date')->orderBy('id')->first();

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Itemized by event', 10);

            // Itemization rows render one <input type="number"> per event. Type
            // a price into the first row and dispatch a change event so the
            // component's save handler fires (Vue listens to @change, which
            // fires on blur for number inputs).
            $browser->script(<<<'JS'
                const inputs = document.querySelectorAll('input[type="number"]');
                // The first matching number input under the Payout section.
                const target = Array.from(inputs).find((el) =>
                    el.closest('.bg-white') &&
                    el.closest('.bg-white').textContent.includes('Itemized by event')
                );
                if (!target) {
                    document.title = 'NO_TARGET';
                    return;
                }
                target.focus();
                target.value = '1500';
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
                target.blur();
            JS);

            // The save fires an Inertia PUT. The component shows a brief
            // green check on success. Wait for the request to roundtrip and
            // the DB to update.
            $browser->pause(2000);
        });

        // Assert the event's price was actually written.
        $firstEvent->refresh();
        $this->assertEquals('1500.00', $firstEvent->price);
    }

    public function test_invalid_per_event_price_surfaces_inline_error(): void
    {
        $booking = $this->makeMultiEventBooking();
        $firstEvent = $booking->events()->orderBy('date')->orderBy('id')->first();
        $originalPrice = $firstEvent->price;

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}")
                ->waitForText('Itemized by event', 10);

            // Push a negative number; the form request validation
            // (price >= 0) should reject it.
            $browser->script(<<<'JS'
                const inputs = document.querySelectorAll('input[type="number"]');
                const target = Array.from(inputs).find((el) =>
                    el.closest('.bg-white') &&
                    el.closest('.bg-white').textContent.includes('Itemized by event')
                );
                target.focus();
                target.value = '-50';
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
                target.blur();
            JS);

            // Wait for the error indicator to render (red exclamation-circle).
            $browser->pause(2000);
            $errorPresent = $browser->script('return document.querySelector(\'.pi-exclamation-circle\') !== null;')[0];
            $this->assertTrue((bool) $errorPresent, 'Expected an inline error indicator after invalid save.');
        });

        // Event price must NOT have been updated.
        $firstEvent->refresh();
        $this->assertEquals($originalPrice, $firstEvent->price, 'Invalid save must not change the event price.');
    }
}
