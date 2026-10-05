<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rehearsal ↔ event associations: the controller only ever associates
 * App\Models\Events rows (never bookings), and the form/detail pages must
 * speak the same language.
 */
class RehearsalAssociationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerBandSchedule(): array
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);
        $schedule = RehearsalSchedule::factory()->create(['band_id' => $band->id]);

        return [$owner, $band, $schedule];
    }

    private function makeUpcomingBookingEvent(Bands $band, string $title = 'Gig'): Events
    {
        $booking = Bookings::factory()->create(['band_id' => $band->id, 'status' => 'confirmed']);

        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(10)->format('Y-m-d'),
            'title'          => $title,
        ]);
    }

    public function test_edit_form_receives_upcoming_events_and_the_rehearsal_associations(): void
    {
        [$owner, $band, $schedule] = $this->makeOwnerBandSchedule();
        $event     = $this->makeUpcomingBookingEvent($band, 'Thompson Wedding');
        $rehearsal = Rehearsal::factory()->create(['band_id' => $band->id, 'rehearsal_schedule_id' => $schedule->id]);
        $rehearsal->associations()->create(['associable_type' => 'App\\Models\\Events', 'associable_id' => $event->id]);

        $this->actingAs($owner)
            ->get(route('rehearsals.edit', ['band' => $band, 'rehearsal_schedule' => $schedule, 'rehearsal' => $rehearsal]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rehearsals/RehearsalForm')
                ->has('upcomingEvents', 1)
                ->where('upcomingEvents.0.id', $event->id)
                ->where('rehearsal.associations.0.associable_type', 'App\\Models\\Events')
                ->where('rehearsal.associations.0.associable.title', 'Thompson Wedding'));
    }

    public function test_update_replaces_associations_with_the_submitted_events(): void
    {
        [$owner, $band, $schedule] = $this->makeOwnerBandSchedule();
        $old = $this->makeUpcomingBookingEvent($band, 'Old');
        $new = $this->makeUpcomingBookingEvent($band, 'New');
        $rehearsal = Rehearsal::factory()->create(['band_id' => $band->id, 'rehearsal_schedule_id' => $schedule->id]);
        $rehearsal->associations()->create(['associable_type' => 'App\\Models\\Events', 'associable_id' => $old->id]);
        $rehearsalEvent = Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(3)->format('Y-m-d'),
            'title'          => 'Rehearsal',
        ]);

        $this->actingAs($owner)
            ->put(route('rehearsals.update', ['band' => $band, 'rehearsal_schedule' => $schedule, 'rehearsal' => $rehearsal]), [
                'event_title'       => 'Rehearsal',
                'event_type_id'     => $rehearsalEvent->event_type_id,
                'event_date'        => $rehearsalEvent->date,
                'event_time'        => '19:00',
                'associated_events' => [$new->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$new->id],
            $rehearsal->fresh()->associations()->pluck('associable_id')->map(fn ($id) => (int) $id)->all(),
        );
        $this->assertSame('App\\Models\\Events', $rehearsal->fresh()->associations()->first()->associable_type);
    }

    public function test_show_page_loads_associations_with_their_events(): void
    {
        [$owner, $band, $schedule] = $this->makeOwnerBandSchedule();
        $event     = $this->makeUpcomingBookingEvent($band, 'Thompson Wedding');
        $rehearsal = Rehearsal::factory()->create(['band_id' => $band->id, 'rehearsal_schedule_id' => $schedule->id]);
        $rehearsal->associations()->create(['associable_type' => 'App\\Models\\Events', 'associable_id' => $event->id]);

        $this->actingAs($owner)
            ->get(route('rehearsals.show', ['band' => $band, 'rehearsal_schedule' => $schedule, 'rehearsal' => $rehearsal]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rehearsals/RehearsalDetail')
                ->where('rehearsal.associations.0.associable.key', $event->key)
                ->where('rehearsal.associations.0.associable.title', 'Thompson Wedding'));
    }
}
