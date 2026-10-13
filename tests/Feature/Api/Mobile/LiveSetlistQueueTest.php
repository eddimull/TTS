<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\LiveSetlistSession;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Captain queue editing: a song can be added right after the current one
 * (default) or at the end of the queue — which lets the mobile app queue
 * songs one tap at a time in tap order — and upcoming songs can be removed.
 */
class LiveSetlistQueueTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private string $token;
    private LiveSetlistSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake(); // no broadcasting

        $user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $user->id]);
        $this->token = $user->createToken('test')->plainTextToken;

        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->format('Y-m-d'),
        ]);

        // No setlist → an empty pick-as-you-go session.
        $id = $this->api()
            ->postJson("/api/mobile/setlist/events/{$event->key}/session")
            ->assertOk()
            ->json('id');
        $this->session = LiveSetlistSession::findOrFail($id);
    }

    private function api()
    {
        return $this->withToken($this->token)->withHeaders(['X-Band-ID' => $this->band->id]);
    }

    private function add(Song $song, ?string $placement = null)
    {
        $body = ['song_id' => $song->id] + ($placement ? ['placement' => $placement] : []);

        return $this->api()->postJson("/api/mobile/setlist/sessions/{$this->session->id}/off-setlist", $body);
    }

    /** @return array<int> song ids of pending entries, in queue order */
    private function pendingOrder(): array
    {
        return $this->session->fresh()->queue()
            ->where('status', 'pending')
            ->orderBy('position')
            ->pluck('song_id')
            ->all();
    }

    public function test_end_placement_appends_in_tap_order(): void
    {
        [$a, $b, $c] = Song::factory()->count(3)->create(['band_id' => $this->band->id]);

        $this->add($a, 'end')->assertOk();
        $this->add($b, 'end')->assertOk();
        $this->add($c, 'end')->assertOk();

        $this->assertSame([$a->id, $b->id, $c->id], $this->pendingOrder());
    }

    public function test_default_placement_still_inserts_right_after_current(): void
    {
        [$a, $b] = Song::factory()->count(2)->create(['band_id' => $this->band->id]);

        $this->add($a)->assertOk();
        $this->add($b)->assertOk();

        $this->assertSame([$b->id, $a->id], $this->pendingOrder());
    }

    public function test_end_placement_lands_after_the_current_song(): void
    {
        [$a, $b] = Song::factory()->count(2)->create(['band_id' => $this->band->id]);

        $this->add($a, 'end')->assertOk();
        $this->api()->postJson("/api/mobile/setlist/sessions/{$this->session->id}/next")->assertOk();
        $this->add($b, 'end')->assertOk();

        $session = $this->session->fresh();
        $current = $session->queue()->where('position', $session->current_position)->first();
        $this->assertSame($a->id, $current->song_id);
        $this->assertSame([$a->id, $b->id], $this->pendingOrder());
    }

    public function test_captain_can_remove_an_upcoming_song(): void
    {
        [$a, $b, $c] = Song::factory()->count(3)->create(['band_id' => $this->band->id]);
        $this->add($a, 'end');
        $this->api()->postJson("/api/mobile/setlist/sessions/{$this->session->id}/next")->assertOk();
        $this->add($b, 'end');
        $this->add($c, 'end');

        $entryB = $this->session->fresh()->queue()->where('song_id', $b->id)->first();

        $this->api()
            ->deleteJson("/api/mobile/setlist/sessions/{$this->session->id}/queue/{$entryB->id}")
            ->assertOk();

        $this->assertSame('removed', $entryB->fresh()->status);
        $this->assertSame([$a->id, $c->id], $this->pendingOrder());
    }

    public function test_cannot_remove_the_song_that_is_playing(): void
    {
        $song = Song::factory()->create(['band_id' => $this->band->id]);
        $this->add($song, 'end');
        $this->api()->postJson("/api/mobile/setlist/sessions/{$this->session->id}/next")->assertOk();

        $current = $this->session->fresh()->queue()->where('song_id', $song->id)->first();

        $this->api()
            ->deleteJson("/api/mobile/setlist/sessions/{$this->session->id}/queue/{$current->id}")
            ->assertNotFound();
        $this->assertSame('pending', $current->fresh()->status);
    }

    public function test_captain_can_reorder_upcoming(): void
    {
        [$a, $b, $c, $d] = Song::factory()->count(4)->create(['band_id' => $this->band->id]);
        $this->add($a, 'end');
        $this->api()->postJson("/api/mobile/setlist/sessions/{$this->session->id}/next")->assertOk();
        foreach ([$b, $c, $d] as $song) {
            $this->add($song, 'end');
        }
        $ids = fn (Song ...$songs) => array_map(
            fn ($s) => $this->session->fresh()->queue()->where('song_id', $s->id)->value('id'),
            $songs,
        );

        $this->api()
            ->putJson("/api/mobile/setlist/sessions/{$this->session->id}/queue/order", [
                'entry_ids' => $ids($d, $b, $c),
            ])
            ->assertOk();

        // The playing song stays put; upcoming takes the new order.
        $this->assertSame([$a->id, $d->id, $b->id, $c->id], $this->pendingOrder());
    }

    public function test_reorder_rejects_a_stale_list(): void
    {
        [$a, $b, $c] = Song::factory()->count(3)->create(['band_id' => $this->band->id]);
        $this->add($a, 'end');
        $this->api()->postJson("/api/mobile/setlist/sessions/{$this->session->id}/next")->assertOk();
        $this->add($b, 'end');
        $this->add($c, 'end');
        $entryB = $this->session->fresh()->queue()->where('song_id', $b->id)->value('id');

        // Missing one upcoming entry → refuse rather than guess.
        $this->api()
            ->putJson("/api/mobile/setlist/sessions/{$this->session->id}/queue/order", [
                'entry_ids' => [$entryB],
            ])
            ->assertStatus(422);
        $this->assertSame([$a->id, $b->id, $c->id], $this->pendingOrder());
    }

    public function test_neutral_reaction_clears_without_logging_a_thumbs_down(): void
    {
        $song = Song::factory()->create(['band_id' => $this->band->id]);
        $this->add($song, 'end');
        $entry = $this->session->fresh()->queue()->first();
        $react = fn ($r) => $this->api()
            ->postJson("/api/mobile/setlist/sessions/{$this->session->id}/reaction", [
                'queue_entry_id' => $entry->id,
                'reaction' => $r,
            ])->assertOk();

        $react('positive');
        $react('neutral');

        $this->assertNull($entry->fresh()->crowd_reaction);
        $this->assertSame(
            ['thumbs_up'],
            \App\Models\LiveSetlistEvent::where('session_id', $this->session->id)
                ->whereIn('action', ['thumbs_up', 'thumbs_down'])
                ->pluck('action')->all(),
        );
    }

    public function test_rejects_unknown_placement(): void
    {
        $song = Song::factory()->create(['band_id' => $this->band->id]);

        $this->add($song, 'middle')->assertStatus(422);
    }
}
