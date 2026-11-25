<?php

namespace Tests\Feature\Api\Mobile;

use App\Ai\Agents\SetlistAgent;
use App\Jobs\ParseClientRequestNotes;
use App\Models\Bands;
use App\Models\BookingClientNotes;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\LiveSetlistSession;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The questionnaire's free text is read into song requests by a queued job
 * whenever the submitted answers change, stored per booking, and served by
 * the setlist editor payload (read by the event's Music tab) as
 * `client_notes`. A live session reuses the stored notes.
 */
class SetlistEditorClientNotesTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private Bookings $booking;
    private Events $event;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key']);

        $user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $user->id]);
        $this->token = $user->createToken('test')->plainTextToken;

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id'   => $this->booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(4)->format('Y-m-d'),
        ]);
    }

    private function api()
    {
        return $this->withToken($this->token)->withHeaders(['X-Band-ID' => $this->band->id]);
    }

    private function show()
    {
        return $this->api()->getJson("/api/mobile/events/{$this->event->id}/setlist");
    }

    private function answer(string $value, string $status = QuestionnaireInstances::STATUS_SUBMITTED): QuestionnaireResponses
    {
        $instance = QuestionnaireInstances::factory()->create([
            'booking_id' => $this->booking->id,
            'name' => 'Wedding Questionnaire',
            'status' => $status,
            'submitted_at' => $status === QuestionnaireInstances::STATUS_SUBMITTED ? now() : null,
        ]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'long_text',
            'label' => 'Other must-play songs / artists not on our list',
            'settings' => [],
        ]);

        return QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => $value,
        ]);
    }

    private function aiReply(string $title): string
    {
        return json_encode([
            'requests' => [
                ['title' => $title, 'artist' => 'George Clinton', 'song_id' => null, 'kind' => 'must_play', 'note' => null],
            ],
            'song_notes' => [], 'rules' => ['No Taylor Swift'], 'crowd' => null,
        ]);
    }

    public function test_no_free_text_returns_empty_notes_without_an_ai_call(): void
    {
        SetlistAgent::fake()->preventStrayPrompts();

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_notes.requests', []);
    }

    public function test_submitted_answers_are_parsed_by_the_job_and_served_on_first_load(): void
    {
        SetlistAgent::fake([$this->aiReply('Atomic Dog')])->preventStrayPrompts();

        // The queue runs synchronously in tests, so saving the answer parses it.
        $this->answer('Atomic Dog - George Clinton');

        $this->show()
            ->assertOk()
            ->assertJsonPath('client_notes.requests.0.title', 'Atomic Dog')
            ->assertJsonPath('client_notes.rules', ['No Taylor Swift']);
        $this->show()->assertOk(); // no second AI call

        SetlistAgent::assertPrompted(fn ($p) => str_contains($p->prompt, 'Atomic Dog - George Clinton'));
    }

    public function test_saving_an_answer_queues_a_debounced_parse(): void
    {
        Queue::fake();

        $this->answer('Atomic Dog - George Clinton');

        Queue::assertPushed(
            ParseClientRequestNotes::class,
            fn ($job) => $job->bookingId === $this->booking->id && $job->delay !== null,
        );
    }

    public function test_answers_in_progress_are_not_parsed(): void
    {
        Queue::fake();

        $this->answer('Atomic Dog', QuestionnaireInstances::STATUS_IN_PROGRESS);

        Queue::assertNotPushed(ParseClientRequestNotes::class);
    }

    public function test_submitting_a_questionnaire_queues_a_parse(): void
    {
        Queue::fake();
        $response = $this->answer('Atomic Dog', QuestionnaireInstances::STATUS_IN_PROGRESS);

        $response->instance->update(['status' => QuestionnaireInstances::STATUS_SUBMITTED, 'submitted_at' => now()]);

        Queue::assertPushed(ParseClientRequestNotes::class);
    }

    public function test_changed_answers_are_parsed_again_and_unchanged_ones_are_not(): void
    {
        SetlistAgent::fake([$this->aiReply('Atomic Dog'), $this->aiReply('Disco Inferno')])->preventStrayPrompts();
        $response = $this->answer('Atomic Dog');

        $response->update(['value' => 'Disco Inferno']);
        // Same value again: no job, so no third (stray) AI call.
        $response->update(['value' => 'Disco Inferno']);

        $this->show()->assertJsonPath('client_notes.requests.0.title', 'Disco Inferno');
    }

    public function test_answers_from_before_this_existed_queue_a_parse_on_first_load(): void
    {
        Queue::fake();
        $this->answer('Atomic Dog');
        Queue::fake(); // forget the answer-saved job

        $this->show()->assertOk()->assertJsonPath('client_notes', null);

        Queue::assertPushed(ParseClientRequestNotes::class, fn ($job) => $job->delay === null);
    }

    public function test_stale_notes_are_served_while_a_refresh_is_queued(): void
    {
        Queue::fake();
        $this->answer('Disco Inferno');
        BookingClientNotes::create([
            'booking_id' => $this->booking->id,
            'notes' => json_decode($this->aiReply('Atomic Dog'), true),
            'source_hash' => sha1('older answers'),
            'parsed_at' => now(),
        ]);

        $this->show()->assertOk()->assertJsonPath('client_notes.requests.0.title', 'Atomic Dog');

        Queue::assertPushed(ParseClientRequestNotes::class);
    }

    public function test_a_live_session_reuses_the_stored_notes(): void
    {
        Event::fake(); // no broadcasting
        SetlistAgent::fake([$this->aiReply('Atomic Dog')])->preventStrayPrompts();
        $this->answer('Atomic Dog - George Clinton');

        $id = $this->api()
            ->postJson("/api/mobile/setlist/events/{$this->event->key}/session")
            ->assertOk()
            ->json('id');

        $this->assertSame(
            'Atomic Dog',
            LiveSetlistSession::findOrFail($id)->client_notes['requests'][0]['title'],
        );
    }

    public function test_no_ai_key_means_nothing_is_queued(): void
    {
        config(['services.anthropic.key' => null]);
        Queue::fake();

        $this->answer('Atomic Dog');
        $this->show()->assertOk()->assertJsonPath('client_notes', null);

        Queue::assertNotPushed(ParseClientRequestNotes::class);
    }

    public function test_backfill_queues_upcoming_bookings_without_stored_notes(): void
    {
        Queue::fake();
        $this->answer('Atomic Dog');
        Queue::fake();

        $this->artisan('client-notes:backfill')->assertSuccessful();

        Queue::assertPushed(ParseClientRequestNotes::class, fn ($job) => $job->bookingId === $this->booking->id);
    }
}
