<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Models\Bands;
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
}
