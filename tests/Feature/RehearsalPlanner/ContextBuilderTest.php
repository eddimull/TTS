<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventSetlist;
use App\Models\Events;
use App\Models\Rehearsal;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Services\RehearsalPlannerContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_includes_four_sections_and_song_count(): void
    {
        $band = Bands::factory()->create();
        Song::factory()->count(2)->create(['band_id' => $band->id, 'active' => true]);
        Song::factory()->create(['band_id' => $band->id, 'active' => false]); // excluded

        $result = app(RehearsalPlannerContextBuilder::class)->build($band->fresh());

        $this->assertStringContainsString('UPCOMING EVENTS', $result['text']);
        $this->assertStringContainsString('RECENTLY REHEARSED', $result['text']);
        $this->assertStringContainsString('PERSONNEL & INSTRUMENTS', $result['text']);
        $this->assertStringContainsString('SONG LIBRARY', $result['text']);
        $this->assertSame(2, $result['song_count']);          // only active songs
        $this->assertFalse($result['has_upcoming_requests']); // no events with setlists
    }

    public function test_empty_band_does_not_throw(): void
    {
        $band = Bands::factory()->create();
        $result = app(RehearsalPlannerContextBuilder::class)->build($band);
        $this->assertSame(0, $result['song_count']);
        $this->assertStringContainsString('(empty library)', $result['text']);
    }

    public function test_recently_rehearsed_section_lists_songs_from_past_rehearsal_bookings(): void
    {
        $band = Bands::factory()->create();

        // Song that should surface under RECENTLY REHEARSED.
        $song = Song::factory()->create([
            'band_id' => $band->id,
            'active' => true,
            'title' => 'Past Rehearsed Anthem',
        ]);

        // A past rehearsal: its polymorphic event is dated before today.
        $rehearsal = Rehearsal::factory()->forBand($band)->create();
        Events::factory()->create([
            'eventable_type' => Rehearsal::class,
            'eventable_id' => $rehearsal->id,
            'date' => now()->subWeek()->toDateString(),
        ]);

        // A booking associated with that rehearsal, whose event carries a setlist song.
        // The pivot's morph type must be set explicitly to Bookings: this is the
        // inverse side of a morphToMany, so attach() would otherwise write the
        // parent's class (Rehearsal). The builder reads associations where
        // associable_type = Bookings, so the row must be tagged correctly.
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $rehearsal->bookings()->attach($booking->id, ['associable_type' => Bookings::class]);

        $bookingEvent = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => now()->subWeek()->toDateString(),
        ]);
        $setlist = EventSetlist::create([
            'event_id' => $bookingEvent->id,
            'band_id' => $band->id,
            'status' => 'ready',
        ]);
        SetlistSong::create([
            'setlist_id' => $setlist->id,
            'song_id' => $song->id,
            'position' => 1,
        ]);

        $result = app(RehearsalPlannerContextBuilder::class)->build($band->fresh());

        $this->assertStringContainsString('Past Rehearsed Anthem', $result['text']);
        // The title must appear within the RECENTLY REHEARSED section.
        $recentlyRehearsed = substr(
            $result['text'],
            strpos($result['text'], 'RECENTLY REHEARSED'),
            strpos($result['text'], 'PERSONNEL & INSTRUMENTS') - strpos($result['text'], 'RECENTLY REHEARSED')
        );
        $this->assertStringContainsString('Past Rehearsed Anthem', $recentlyRehearsed);
    }

    public function test_upcoming_event_with_setlist_sets_has_upcoming_requests(): void
    {
        $band = Bands::factory()->create();

        $song = Song::factory()->create([
            'band_id' => $band->id,
            'active' => true,
            'title' => 'Future Request Tune',
        ]);

        // Upcoming booking event (dated in the future) with a setlist song.
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => now()->addWeek()->toDateString(),
        ]);
        $setlist = EventSetlist::create([
            'event_id' => $event->id,
            'band_id' => $band->id,
            'status' => 'ready',
        ]);
        SetlistSong::create([
            'setlist_id' => $setlist->id,
            'song_id' => $song->id,
            'position' => 1,
        ]);

        $result = app(RehearsalPlannerContextBuilder::class)->build($band->fresh());

        $this->assertTrue($result['has_upcoming_requests']);
        $upcoming = substr(
            $result['text'],
            strpos($result['text'], 'UPCOMING EVENTS'),
            strpos($result['text'], 'RECENTLY REHEARSED') - strpos($result['text'], 'UPCOMING EVENTS')
        );
        $this->assertStringContainsString('Future Request Tune', $upcoming);
    }
}
