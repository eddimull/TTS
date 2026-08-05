<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Access control for the web event detail page (`GET /events/{key}`).
 *
 * The route is guarded only by ['auth', 'verified'] — there is no
 * band-membership middleware — so before this gate ANY verified user on the
 * platform could read the full event payload (band contacts, attachments,
 * roster, notes) for ANY event. The numeric-id fallback in show() made that
 * trivially enumerable (/events/1, /events/2, ...).
 *
 * The gate must admit exactly two populations:
 *   1. full members/owners of the event's band, and
 *   2. subs of that band who are assigned to THIS specific gig — web subs
 *      legitimately open their events from sub-calendar links, so this path
 *      must keep working.
 * Everyone else gets a 403.
 */
class EventShowAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'sub', 'guard_name' => 'web']);
    }

    /**
     * A band with an owner, a booking and one event.
     */
    private function createBandWithEvent(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);

        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event   = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);

        return compact('owner', 'band', 'booking', 'event');
    }

    /**
     * Attach a sub to the band, optionally assigning them to a specific event.
     *
     * NOTE: the band_subs pivot lives on User (`User::bandSub`), not on Bands.
     * `isSubOfBand()` reads the loaded relation, so the cached (empty) copy has
     * to be dropped after attaching.
     */
    private function makeSub(Bands $band, ?Events $event = null): User
    {
        $sub = User::factory()->create(['email_verified_at' => now()]);
        $sub->bandSub()->attach($band->id);
        $sub->ensureGlobalSubRole();

        if ($event) {
            DB::table('event_subs')->insert([
                'event_id'       => $event->id,
                'band_id'        => $band->id,
                'user_id'        => $sub->id,
                'invitation_key' => Str::uuid(),
                'pending'        => false,
                'accepted_at'    => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $sub->unsetRelation('bandSub');

        return $sub;
    }

    public function test_stranger_cannot_view_event_by_key(): void
    {
        ['event' => $event] = $this->createBandWithEvent();

        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get(route('events.show', $event->key))
            ->assertStatus(403);
    }

    /**
     * The numeric-id fallback is the enumerable surface — /events/1, /events/2,
     * ... — so it needs its own assertion, not just the key path.
     */
    public function test_stranger_cannot_view_event_by_numeric_id(): void
    {
        ['event' => $event] = $this->createBandWithEvent();

        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get(route('events.show', $event->id))
            ->assertStatus(403);
    }

    public function test_member_of_a_different_band_cannot_view_event(): void
    {
        ['event' => $event] = $this->createBandWithEvent();

        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $otherBand = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->get(route('events.show', $event->key))
            ->assertStatus(403);
    }

    public function test_full_member_can_view_event(): void
    {
        ['band' => $band, 'event' => $event] = $this->createBandWithEvent();

        $member = User::factory()->create(['email_verified_at' => now()]);
        $band->members()->create(['user_id' => $member->id]);

        $this->actingAs($member)
            ->get(route('events.show', $event->key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Show')
                ->where('event.id', $event->id)
            );
    }

    public function test_band_owner_can_view_event(): void
    {
        ['owner' => $owner, 'event' => $event] = $this->createBandWithEvent();

        $this->actingAs($owner)
            ->get(route('events.show', $event->key))
            ->assertOk();
    }

    /**
     * Web subs open their gigs from sub-calendar links — this path must not
     * regress.
     */
    public function test_assigned_sub_can_view_event(): void
    {
        ['band' => $band, 'event' => $event] = $this->createBandWithEvent();

        $sub = $this->makeSub($band, $event);

        $this->actingAs($sub)
            ->get(route('events.show', $event->key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Show')
                ->where('event.id', $event->id)
            );
    }

    /**
     * Band-wide sub status is not enough: a sub assigned to gig A must not be
     * able to open gig B in the same band.
     */
    public function test_unassigned_sub_of_the_same_band_cannot_view_event(): void
    {
        ['band' => $band, 'event' => $event] = $this->createBandWithEvent();

        // The sub is assigned to a *different* gig in the same band.
        $otherBooking = Bookings::factory()->create(['band_id' => $band->id]);
        $otherEvent   = Events::factory()->create([
            'eventable_id'   => $otherBooking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(9)->format('Y-m-d'),
        ]);

        $sub = $this->makeSub($band, $otherEvent);

        $this->actingAs($sub)
            ->get(route('events.show', $event->key))
            ->assertStatus(403);

        // ...and the same holds for the enumerable numeric-id path.
        $this->actingAs($sub)
            ->get(route('events.show', $event->id))
            ->assertStatus(403);
    }
}
