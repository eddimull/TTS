<?php

namespace Tests\Feature\Api\Mobile;

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
use Tests\TestCase;

/**
 * The mobile setlist editor payload surfaces client must-play / do-not-play
 * picks from submitted questionnaires on the event's booking as
 * `client_requests`.
 */
class SetlistEditorClientRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;
    private Bookings $booking;
    private Events $event;
    private string $token;

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

        $this->token = $this->user->createToken('test-device')->plainTextToken;
    }

    private function show()
    {
        return $this->withToken($this->token)
            ->withHeader('X-Band-ID', (string) $this->band->id)
            ->getJson("/api/mobile/events/{$this->event->id}/setlist");
    }

    /**
     * @param array<int> $mustPlay
     * @param array<int> $doNotPlay
     */
    private function makeInstance(array $mustPlay, array $doNotPlay, ?callable $state = null): QuestionnaireInstances
    {
        $factory = QuestionnaireInstances::factory();
        if ($state) {
            $factory = $state($factory);
        }
        $instance = $factory->create([
            'booking_id' => $this->booking->id,
            'name' => 'Wedding Questionnaire',
        ]);

        foreach (['must_play' => $mustPlay, 'do_not_play' => $doNotPlay] as $purpose => $ids) {
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

        return $instance;
    }

    public function test_client_requests_is_null_without_any_questionnaire(): void
    {
        Song::factory()->active()->create(['band_id' => $this->band->id]);

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_requests', null);
    }

    public function test_unsubmitted_instance_is_ignored(): void
    {
        $song = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $this->makeInstance([$song->id], [], fn ($f) => $f->inProgress());

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_requests', null);
    }

    public function test_submitted_instance_returns_must_and_do_not_play_ids(): void
    {
        $must = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $skip = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $this->makeInstance([$must->id], [$skip->id], fn ($f) => $f->submitted());

        $resp = $this->show()->assertOk();

        $resp->assertJsonPath('client_requests.must_play', [$must->id])
            ->assertJsonPath('client_requests.do_not_play', [$skip->id])
            ->assertJsonPath('client_requests.source.name', 'Wedding Questionnaire');
        $this->assertNotNull($resp->json('client_requests.source.submitted_at'));
    }

    public function test_locked_instance_counts_as_submitted(): void
    {
        $must = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $this->makeInstance([$must->id], [], fn ($f) => $f->locked());

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_requests.must_play', [$must->id])
            ->assertJsonPath('client_requests.do_not_play', []);
    }

    public function test_removed_or_inactive_or_foreign_songs_are_dropped(): void
    {
        $keep = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $inactive = Song::factory()->inactive()->create(['band_id' => $this->band->id]);
        $foreign = Song::factory()->active()->create();
        $this->makeInstance([$keep->id, $inactive->id, $foreign->id, 999999], [], fn ($f) => $f->submitted());

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_requests.must_play', [$keep->id]);
    }

    public function test_do_not_play_wins_when_song_is_in_both_lists(): void
    {
        $song = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $this->makeInstance([$song->id], [$song->id], fn ($f) => $f->submitted());

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_requests.must_play', [])
            ->assertJsonPath('client_requests.do_not_play', [$song->id]);
    }

    public function test_multiple_submitted_instances_are_unioned(): void
    {
        $a = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $b = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $this->makeInstance([$a->id], [], fn ($f) => $f->submitted());
        $this->makeInstance([$b->id], [], fn ($f) => $f->submitted());

        $resp = $this->show()->assertOk();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $resp->json('client_requests.must_play'));
    }

    public function test_source_skips_newer_instance_whose_picks_were_all_removed(): void
    {
        $keep = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $older = $this->makeInstance([$keep->id], [], fn ($f) => $f->submitted());
        $older->forceFill(['name' => 'Older Questionnaire', 'submitted_at' => now()->subDays(5)])->save();

        $gone = Song::factory()->inactive()->create(['band_id' => $this->band->id]);
        $this->makeInstance([$gone->id], [], fn ($f) => $f->submitted());

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_requests.must_play', [$keep->id])
            ->assertJsonPath('client_requests.source.instance_id', $older->id)
            ->assertJsonPath('client_requests.source.name', 'Older Questionnaire');
    }

    public function test_instance_with_only_empty_song_picks_yields_null(): void
    {
        $this->makeInstance([], [], fn ($f) => $f->submitted());

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_requests', null);
    }
}
