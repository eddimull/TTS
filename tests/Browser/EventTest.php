<?php

namespace Tests\Browser;

use App\Models\BandEvents;
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

class EventTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $owner;
    private Bands $band;

    /**
     * Skip the parent's teardown migrate:rollback, which fails on this
     * codebase's irreversible drop_proposal_tables migration. The next
     * test's migrate:fresh will reset state cleanly.
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

        $this->band = Bands::factory()->create(['name' => 'Test Band']);

        $this->owner = User::factory()->create([
            'name' => 'Bob Owner',
            'email' => 'event-owner@test.local',
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

    public function test_authenticated_user_can_view_events_index(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/events')
                ->waitForText('Draft New Event', 10)
                ->assertSee('Draft New Event');
        });
    }

    public function test_user_can_open_legacy_create_event_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/events/create')
                ->waitForText('Initial Information', 10)
                ->assertSee('Initial Information')
                ->assertPresent('#bandDropdown')
                ->assertPresent('#name')
                ->assertPresent('#productionDropdown');
        });
    }

    public function test_wedding_event_type_reveals_dance_fields(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/events/create')
                ->waitForText('Initial Information', 10)
                ->assertDontSee('First Dance')
                ->assertDontSee('Father / Daughter Dance')
                ->select('#productionDropdown', '1')
                ->waitForText('First Dance', 5)
                ->assertSee('First Dance')
                ->assertSee('Father / Daughter Dance')
                ->assertSee('Mother / Groom Dance')
                ->assertSee('Money Dance')
                ->assertSee('Bouquet / Garter');
        });
    }

    public function test_user_can_open_a_booking_event_for_editing(): void
    {
        // Seed a booking + event using the modern polymorphic flow that
        // events.edit redirects into.
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Smith Wedding',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Jane Smith',
            'email' => 'event-test-contact@test.local',
        ]);
        $booking->contacts()->attach($contact, ['is_primary' => true]);

        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Smith Wedding',
        ]);

        $this->browse(function (Browser $browser) use ($event) {
            $browser->loginAs($this->owner)
                ->visit("/events/{$event->key}/edit")
                ->waitForText('Smith Wedding', 10)
                ->assertSee('Smith Wedding');
        });
    }

    public function test_user_can_delete_a_legacy_band_event(): void
    {
        $event = BandEvents::factory()->create([
            'band_id' => $this->band->id,
            'event_name' => 'Legacy Wedding',
        ]);

        $this->browse(function (Browser $browser) use ($event) {
            // The legacy edit page is dead UI; events.edit redirects
            // polymorphic events to booking pages. Submit a real HTML form
            // to the destroy route — that uses the browser's session cookies
            // and follows the post-delete redirect.
            $browser->loginAs($this->owner)
                ->visit('/events')
                ->waitForText('Draft New Event', 10);

            $browser->script(<<<JS
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/events/{$event->event_key}';
                form.innerHTML = `
                    <input type="hidden" name="_token" value="\${token}">
                    <input type="hidden" name="_method" value="DELETE">
                `;
                document.body.appendChild(form);
                form.submit();
            JS);

            // Form submit navigates the page; wait for the post-delete redirect
            // back to /events to land before we assert.
            $browser->waitUntil('window.location.pathname === "/events"', 10);
            $browser->waitForText('Draft New Event', 10);
        });

        $this->assertSoftDeleted('band_events', ['id' => $event->id]);
    }
}
