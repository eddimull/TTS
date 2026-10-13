<?php

namespace Tests\Feature\Api\Mobile;

use App\Ai\Agents\SetlistAgent;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\LiveSetlistSession;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The live session honors the client's questionnaire picks: the mobile
 * payload carries them, and AI "what's next" suggestions never offer a
 * do-not-play song and steer toward must-play songs.
 */
class LiveSetlistClientRequestsTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private Bookings $booking;
    private Events $event;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake(); // no broadcasting
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
            'date'           => now()->format('Y-m-d'),
        ]);
    }

    private function api()
    {
        return $this->withToken($this->token)->withHeaders(['X-Band-ID' => $this->band->id]);
    }

    /** @param array<string, array<int>> $picks purpose => song ids */
    private function submitQuestionnaire(array $picks): void
    {
        $instance = QuestionnaireInstances::factory()->submitted()->create([
            'booking_id' => $this->booking->id,
            'name' => 'Wedding Questionnaire',
        ]);
        foreach ($picks as $purpose => $ids) {
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
    }

    private function startLiveSession(): LiveSetlistSession
    {
        $id = $this->api()
            ->postJson("/api/mobile/setlist/events/{$this->event->key}/session")
            ->assertOk()
            ->json('id');

        return LiveSetlistSession::findOrFail($id);
    }

    public function test_session_payload_includes_client_requests(): void
    {
        [$must, $skip] = Song::factory()->active()->count(2)->create(['band_id' => $this->band->id]);
        $this->submitQuestionnaire(['must_play' => [$must->id], 'do_not_play' => [$skip->id]]);

        $this->api()
            ->getJson("/api/mobile/setlist/events/{$this->event->key}/session")
            ->assertOk()
            ->assertJsonPath('client_requests.must_play', [$must->id])
            ->assertJsonPath('client_requests.do_not_play', [$skip->id]);
    }

    public function test_suggestion_never_offers_a_do_not_play_song(): void
    {
        [$ok, $skip] = Song::factory()->active()->count(2)->create(['band_id' => $this->band->id]);
        $this->submitQuestionnaire(['do_not_play' => [$skip->id]]);
        $session = $this->startLiveSession();

        // Even if the model names the excluded song, it must not come back.
        SetlistAgent::fake(["[{$skip->id}]"]);

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")
            ->assertOk()
            ->assertJsonPath('suggestion.song_id', $ok->id);

        SetlistAgent::assertPrompted(fn ($prompt) => !str_contains($prompt->prompt, "ID:{$skip->id} "));
    }

    public function test_suggestion_prompt_flags_must_play_songs(): void
    {
        [$must, $other] = Song::factory()->active()->count(2)->create(['band_id' => $this->band->id]);
        $this->submitQuestionnaire(['must_play' => [$must->id]]);
        $session = $this->startLiveSession();

        SetlistAgent::fake(["[{$must->id}]"]);

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")
            ->assertOk()
            ->assertJsonPath('suggestion.song_id', $must->id)
            ->assertJsonPath('suggestion.must_play', true);

        SetlistAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, "CLIENT MUST-PLAY REQUESTS still unplayed (IDs): {$must->id}")
        );
    }

    private function submitFreeText(array $answers): void
    {
        $instance = QuestionnaireInstances::factory()->submitted()->create([
            'booking_id' => $this->booking->id,
            'name' => 'Wedding Questionnaire',
        ]);
        foreach ($answers as $label => $value) {
            $field = QuestionnaireInstanceFields::factory()->create([
                'instance_id' => $instance->id,
                'type' => 'long_text',
                'label' => $label,
                'settings' => [],
            ]);
            QuestionnaireResponses::create([
                'instance_id' => $instance->id,
                'instance_field_id' => $field->id,
                'value' => $value,
            ]);
        }
    }

    public function test_free_text_requests_are_parsed_once_when_the_session_starts(): void
    {
        $september = Song::factory()->active()->create(['band_id' => $this->band->id, 'title' => 'September']);
        $this->submitFreeText([
            'Other must-play songs / artists not on our list' => 'Calling Baton Rouge is a MUST!',
            'Other do-not-play songs / artists not on our list' => 'No slow songs. Do not play Pink Pony Club.',
        ]);

        SetlistAgent::fake([json_encode([
            'requests' => [
                ['title' => 'Calling Baton Rouge', 'artist' => 'Garth Brooks', 'song_id' => null, 'kind' => 'must_play', 'note' => 'Bride is Samantha'],
                ['title' => 'Pink Pony Club', 'artist' => 'Chappell Roan', 'song_id' => null, 'kind' => 'do_not_play', 'note' => null],
                ['title' => 'Bogus', 'artist' => null, 'song_id' => 999999, 'kind' => 'must_play', 'note' => null],
                ['title' => 'Bad kind', 'artist' => null, 'song_id' => null, 'kind' => 'whatever', 'note' => null],
            ],
            'song_notes' => [
                ['title' => 'September', 'song_id' => $september->id, 'note' => 'Sing "dancing in October"'],
            ],
            'rules' => ['No slow songs'],
            'crowd' => 'Young crowd ready to party',
        ])]);

        $session = $this->startLiveSession();

        $notes = $session->fresh()->client_notes;
        $this->assertSame('Calling Baton Rouge', $notes['requests'][0]['title']);
        // A catalog ID the band doesn't have is dropped to null, not trusted.
        $this->assertNull($notes['requests'][2]['song_id']);
        // Unknown kinds are discarded.
        $this->assertCount(3, $notes['requests']);
        $this->assertSame($september->id, $notes['song_notes'][0]['song_id']);
        $this->assertSame(['No slow songs'], $notes['rules']);

        SetlistAgent::assertPrompted(fn ($p) => str_contains($p->prompt, 'Calling Baton Rouge is a MUST!'));

        // Loading the session again doesn't re-run the AI.
        $this->api()
            ->getJson("/api/mobile/setlist/events/{$this->event->key}/session")
            ->assertOk()
            ->assertJsonPath('client_notes.crowd', 'Young crowd ready to party');
    }

    public function test_no_questionnaire_text_means_no_ai_call(): void
    {
        SetlistAgent::fake()->preventStrayPrompts();

        $session = $this->startLiveSession();

        $this->assertSame([], $session->fresh()->client_notes['requests']);
    }

    public function test_client_rules_reach_the_suggestion_prompt(): void
    {
        $song = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $this->submitFreeText(['Anything else?' => 'No slow songs please']);
        SetlistAgent::fake([
            json_encode(['requests' => [], 'song_notes' => [], 'rules' => ['No slow songs'], 'crowd' => null]),
            "[{$song->id}]",
        ]);
        $session = $this->startLiveSession();

        $this->api()->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")->assertOk();

        SetlistAgent::assertPrompted(fn ($p) => str_contains($p->prompt, "CLIENT INSTRUCTIONS (follow strictly):\n- No slow songs"));
    }

    public function test_no_suggestion_when_nothing_left_fits_the_client_rules(): void
    {
        Song::factory()->active()->create(['band_id' => $this->band->id, 'genre' => 'Ballad']);
        $this->submitFreeText(['Anything else?' => 'No slow songs please']);
        SetlistAgent::fake([
            json_encode(['requests' => [], 'song_notes' => [], 'rules' => ['No slow songs'], 'crowd' => null]),
            '[]',
        ]);
        $session = $this->startLiveSession();

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")
            ->assertOk()
            ->assertJsonPath('suggestion', null)
            ->assertJsonPath('message', "Nothing left that fits the client's requests.");
    }

    public function test_fenced_empty_reply_still_means_nothing_fits(): void
    {
        Song::factory()->active()->create(['band_id' => $this->band->id, 'genre' => 'Ballad']);
        $this->submitFreeText(['Anything else?' => 'No slow songs please']);
        SetlistAgent::fake([
            json_encode(['requests' => [], 'song_notes' => [], 'rules' => ['No slow songs'], 'crowd' => null]),
            "```JSON\n[]\n```",
        ]);
        $session = $this->startLiveSession();

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")
            ->assertOk()
            ->assertJsonPath('suggestion', null);
    }

    public function test_a_request_not_in_the_catalog_can_be_queued_by_title(): void
    {
        $session = $this->startLiveSession();

        $this->api()
            ->postJson("/api/mobile/setlist/sessions/{$session->id}/off-setlist", [
                'custom_title' => 'Calling Baton Rouge',
                'custom_artist' => 'Garth Brooks',
                'placement' => 'end',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Calling Baton Rouge');

        $entry = $session->fresh()->queue()->first();
        $this->assertNull($entry->song_id);
        $this->assertSame('Garth Brooks', $entry->custom_artist);
    }

    public function test_songs_removed_from_the_queue_can_be_suggested_again(): void
    {
        $song = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $session = $this->startLiveSession();
        $session->queue()->create([
            'song_id' => $song->id, 'position' => 5, 'status' => 'removed', 'type' => 'song',
        ]);
        SetlistAgent::fake(["[{$song->id}]"]);

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")
            ->assertOk()
            ->assertJsonPath('suggestion.song_id', $song->id);
    }

    public function test_skip_offers_a_different_song_without_remembering_it(): void
    {
        [$a, $b] = Song::factory()->active()->count(2)->create(['band_id' => $this->band->id]);
        $session = $this->startLiveSession();
        SetlistAgent::fake(["[{$b->id}]", "[{$a->id}]"]);

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest?skip[]={$a->id}")
            ->assertOk()
            ->assertJsonPath('suggestion.song_id', $b->id);
        $this->assertSame([], $session->fresh()->rejected_song_ids ?? []);

        // Everything shown already → start over rather than run dry.
        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest?skip[]={$a->id}&skip[]={$b->id}")
            ->assertOk()
            ->assertJsonPath('suggestion.song_id', $a->id);
    }

    private function sessionWithOffCatalogRequests(): LiveSetlistSession
    {
        Song::factory()->active()->create(['band_id' => $this->band->id]);
        $this->submitFreeText(['Other must-play songs' => 'Calling Baton Rouge and Family Tradition']);
        SetlistAgent::fake([json_encode([
            'requests' => [
                ['title' => 'Calling Baton Rouge', 'artist' => 'Garth Brooks', 'song_id' => null, 'kind' => 'must_play', 'note' => 'Bride is Samantha'],
                ['title' => 'Family Tradition', 'artist' => 'Hank Williams Jr.', 'song_id' => null, 'kind' => 'must_play', 'note' => null],
                ['title' => 'Perfect', 'artist' => 'Ed Sheeran', 'song_id' => null, 'kind' => 'special_moment', 'note' => 'Last dance'],
            ],
            'song_notes' => [], 'rules' => [], 'crowd' => null,
        ])]);

        return $this->startLiveSession();
    }

    public function test_ai_can_suggest_a_client_request_that_is_not_in_the_catalog(): void
    {
        $session = $this->sessionWithOffCatalogRequests();
        SetlistAgent::fake(['["R1"]']);

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")
            ->assertOk()
            ->assertJsonPath('suggestion.custom', true)
            ->assertJsonPath('suggestion.song_id', null)
            ->assertJsonPath('suggestion.title', 'Calling Baton Rouge')
            ->assertJsonPath('suggestion.must_play', true)
            ->assertJsonPath('suggestion.note', 'Bride is Samantha');

        // Special moments are timeline cues, not candidates.
        SetlistAgent::assertPrompted(fn ($p) => str_contains($p->prompt, 'R1 | Calling Baton Rouge – Garth Brooks | MUST PLAY')
            && !str_contains($p->prompt, 'Perfect – Ed Sheeran'));
    }

    public function test_requests_already_queued_are_not_offered(): void
    {
        $session = $this->sessionWithOffCatalogRequests();
        $session->queue()->create([
            'custom_title' => 'Calling Baton Rouge', 'position' => 3, 'status' => 'pending', 'type' => 'song',
        ]);
        SetlistAgent::fake(['["R1"]']);

        // Calling Baton Rouge is queued, so R1 is now Family Tradition.
        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest")
            ->assertOk()
            ->assertJsonPath('suggestion.title', 'Family Tradition');
    }

    public function test_skip_titles_offers_a_different_request_then_cycles(): void
    {
        $session = $this->sessionWithOffCatalogRequests();
        SetlistAgent::fake(['["R1"]', '["R1"]']);

        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest?skip_titles[]=Calling%20Baton%20Rouge")
            ->assertOk()
            ->assertJsonPath('suggestion.title', 'Family Tradition');

        // Both shown already → start over with both.
        $this->api()
            ->getJson("/api/mobile/setlist/sessions/{$session->id}/suggest?skip_titles[]=Calling%20Baton%20Rouge&skip_titles[]=Family%20Tradition")
            ->assertOk()
            ->assertJsonPath('suggestion.title', 'Calling Baton Rouge');
    }

    public function test_accepting_a_suggestion_queues_it(): void
    {
        $song = Song::factory()->active()->create(['band_id' => $this->band->id]);
        $session = $this->startLiveSession();

        $this->api()
            ->postJson("/api/mobile/setlist/sessions/{$session->id}/accept-suggestion", ['song_id' => $song->id])
            ->assertOk();

        $this->assertSame(
            [$song->id],
            $session->fresh()->queue()->where('status', 'pending')->pluck('song_id')->all(),
        );
    }
}
