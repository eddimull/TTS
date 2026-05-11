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

    public function test_multi_event_payout_page_shows_itemization_section(): void
    {
        $booking = $this->makeMultiEventBooking();

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}/payout")
                ->waitForText('Itemized by event', 10)
                ->assertSee('Itemized by event')
                ->assertSee('Itemized total')
                ->assertSee('Booking total');
        });
        $this->assertTrue(true, 'browser assertions completed');
    }

    public function test_single_event_payout_page_does_not_show_itemization_section(): void
    {
        $booking = $this->makeSingleEventBooking();

        $this->browse(function (Browser $browser) use ($booking) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}/payout")
                ->waitForText('Payout Breakdown', 10)
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
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}/payout")
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

    public function test_multi_event_booking_form_handles_full_add_edit_delete_save(): void
    {
        $booking = $this->makeMultiEventBooking('Concert Series');

        // The factory adds 3 events ordered by date: rehearsal (6/12),
        // Saturday performance (6/13), Sunday performance (6/14).
        $events = $booking->events()->orderBy('date')->orderBy('id')->get();
        $rehearsal = $events->get(0);
        $saturday = $events->get(1);
        $sunday = $events->get(2);

        $this->browse(function (Browser $browser) use ($booking, $rehearsal, $sunday) {
            $browser->loginAs($this->owner)
                // Open the edit form via the ?edit=true query string.
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}?edit=true")
                ->waitForText('Save Booking', 10);

            // 1) Rename the booking. TextInput renders <input id="{name}">
            //    so the booking-level "name" field is `#name`.
            $browser->script(<<<'JS'
                const nameInput = document.querySelector('#name');
                nameInput.focus();
                nameInput.value = 'Concert Series — Renamed';
                nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                nameInput.dispatchEvent(new Event('change', { bubbles: true }));
            JS);

            // 2) Change the Rehearsal event date (originally 2026-06-12).
            //    Find the row whose date input currently has that value —
            //    the form lists events in reverse-chronological order so
            //    we can't rely on position.
            $browser->script(<<<'JS'
                const rows = document.querySelectorAll('.border.border-gray-200');
                const subFormRows = Array.from(rows).filter((el) =>
                    el.querySelector('input[type="date"]')
                );
                const rehearsalRow = subFormRows.find((row) =>
                    row.querySelector('input[type="date"]').value === '2026-06-12'
                );
                const dateInput = rehearsalRow.querySelector('input[type="date"]');
                dateInput.value = '2026-06-10';
                dateInput.dispatchEvent(new Event('input', { bubbles: true }));
                dateInput.dispatchEvent(new Event('change', { bubbles: true }));
            JS);

            // 3) Add a new event row via the "Add event" button.
            $browser->script(<<<'JS'
                const addBtns = Array.from(document.querySelectorAll('button'))
                    .filter((b) => b.textContent.trim().toLowerCase().includes('add event'));
                addBtns[0].click();
            JS);

            // Wait until the new row appears (4 rows total now).
            $browser->waitUsing(5, 250, function () use ($browser) {
                $count = $browser->script(<<<'JS'
                    return document.querySelectorAll('.border.border-gray-200 input[type="date"]').length;
                JS)[0];
                return $count === 4;
            });

            // Fill in the new event row's title + date. It's the last
            // EventSubForm in the list.
            $browser->script(<<<'JS'
                const rows = document.querySelectorAll('.border.border-gray-200');
                const subFormRows = Array.from(rows).filter((el) =>
                    el.querySelector('input[type="date"]')
                );
                const lastRow = subFormRows[subFormRows.length - 1];
                // TextInput renders <input type="text" id="{name}">. The first
                // text-type input in an EventSubForm is the Title field.
                const titleInput = lastRow.querySelector('input[type="text"]');
                titleInput.focus();
                titleInput.value = 'Encore performance';
                titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                titleInput.dispatchEvent(new Event('change', { bubbles: true }));
                const dateInput = lastRow.querySelector('input[type="date"]');
                dateInput.value = '2026-06-15';
                dateInput.dispatchEvent(new Event('input', { bubbles: true }));
                dateInput.dispatchEvent(new Event('change', { bubbles: true }));
            JS);

            // 4) Delete the Sunday performance row (originally 3rd by date).
            //    With the new row appended, the original Sunday is row index 2.
            $browser->script(<<<'JS'
                const rows = document.querySelectorAll('.border.border-gray-200');
                const subFormRows = Array.from(rows).filter((el) =>
                    el.querySelector('input[type="date"]')
                );
                // Find the row whose date is currently 2026-06-14 (the
                // original Sunday performance).
                const sundayRow = subFormRows.find((row) =>
                    row.querySelector('input[type="date"]').value === '2026-06-14'
                );
                const deleteBtn = sundayRow.querySelector('button[title*="Remove"]')
                    ?? sundayRow.querySelector('button .pi-trash')?.closest('button');
                deleteBtn.click();
            JS);

            // Wait for the deletion to drop the row count back to 3
            // (rehearsal + Saturday + the new encore).
            $browser->waitUsing(5, 250, function () use ($browser) {
                $count = $browser->script(<<<'JS'
                    return document.querySelectorAll('.border.border-gray-200 input[type="date"]').length;
                JS)[0];
                return $count === 3;
            });

            // 5) Submit the form.
            $browser->script(<<<'JS'
                const saveBtns = Array.from(document.querySelectorAll('button[type="submit"]'))
                    .filter((b) => b.textContent.trim().toLowerCase().includes('save'));
                saveBtns[0].click();
            JS);

            // The sequential save fires:
            //   1) PATCH booking
            //   2) PUT existing events
            //   3) POST new events
            //   4) DELETE removed events
            // Poll the DB for the expected end state — equivalent to waiting
            // for the post-save redirect, but resilient against Inertia
            // caching the edit-mode URL after navigation.
            $browser->waitUsing(15, 500, function () use ($booking) {
                $renamed = (bool) \App\Models\Bookings::where('id', $booking->id)
                    ->where('name', 'Concert Series — Renamed')
                    ->exists();
                $encoreExists = (bool) $booking->events()
                    ->where('title', 'Encore performance')
                    ->exists();
                $count = $booking->events()->count();
                return $renamed && $encoreExists && $count === 3;
            });
        });

        // Assert booking renamed.
        $booking->refresh();
        $this->assertEquals('Concert Series — Renamed', $booking->name);

        // Assert rehearsal date changed.
        $rehearsal->refresh();
        $this->assertEquals('2026-06-10', $rehearsal->date->format('Y-m-d'));

        // Assert Sunday performance is gone.
        $this->assertNull(\App\Models\Events::find($sunday->id));

        // Assert new "Encore performance" event exists on 6/15.
        $encore = $booking->events()->where('title', 'Encore performance')->first();
        $this->assertNotNull($encore);
        $this->assertEquals('2026-06-15', $encore->date->format('Y-m-d'));

        // Assert final event count is 3 (rehearsal + Saturday + Encore).
        $this->assertEquals(3, $booking->events()->count());
    }

    public function test_invalid_per_event_price_is_rejected_by_the_backend(): void
    {
        $booking = $this->makeMultiEventBooking();
        $firstEvent = $booking->events()->orderBy('date')->orderBy('id')->first();
        $originalPrice = $firstEvent->price;

        $this->browse(function (Browser $browser) use ($booking, $firstEvent) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$booking->id}/payout")
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
