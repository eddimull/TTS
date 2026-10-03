<?php

namespace Tests\Feature\Setlists;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The web setlist editor receives the same client must-play / do-not-play
 * picks as the mobile editor, as the `clientRequests` Inertia prop.
 */
class EditorClientRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;
    private Bookings $booking;
    private Events $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->user->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id'   => $this->booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);
    }

    private function show(): TestResponse
    {
        return $this->actingAs($this->user)->get("/events/{$this->event->key}/setlist");
    }

    public function test_client_requests_prop_is_null_without_submitted_picks(): void
    {
        Song::factory()->active()->create(['band_id' => $this->band->id]);

        $this->show()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Setlists/Editor')
                ->where('clientRequests', null));
    }

    public function test_client_requests_prop_carries_submitted_picks(): void
    {
        $must = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $skip = Song::factory()->active()->create(['band_id' => $this->band->id]);

        $instance = QuestionnaireInstances::factory()->submitted()->create([
            'booking_id' => $this->booking->id,
            'name' => 'Wedding Questionnaire',
        ]);
        foreach (['must_play' => [$must->id], 'do_not_play' => [$skip->id]] as $purpose => $ids) {
            $field = QuestionnaireInstanceFields::factory()->create([
                'instance_id' => $instance->id,
                'type' => 'song_picker',
                'settings' => ['purpose' => $purpose],
            ]);
            QuestionnaireResponses::create([
                'instance_id' => $instance->id,
                'instance_field_id' => $field->id,
                'value' => json_encode($ids),
            ]);
        }

        $this->show()
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Setlists/Editor')
                ->where('clientRequests.must_play', [$must->id])
                ->where('clientRequests.do_not_play', [$skip->id])
                ->where('clientRequests.source.name', 'Wedding Questionnaire'));
    }
}
