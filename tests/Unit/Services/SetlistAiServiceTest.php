<?php

namespace Tests\Unit\Services;

use App\Ai\Agents\SetlistAgent;
use App\Ai\Agents\VisionAgent;
use App\Services\SetlistAiService;
use Laravel\Ai\Gateway\FakeTextGateway;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SetlistAiServiceTest extends TestCase
{
    private SetlistAiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SetlistAiService();
        Log::spy();
    }

    // ─── classifyImage ───────────────────────────────────────────────────────

    public function test_classify_image_returns_marked_setlist(): void
    {
        VisionAgent::fake(['MARKED_SETLIST', 'MARKED_SETLIST']); // classify + extract (no songs → empty chunk)

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('[Classified as: MARKED_SETLIST]', $result);
    }

    public function test_classify_image_returns_other_for_unrecognised_response(): void
    {
        VisionAgent::fake(['SOMETHING_UNEXPECTED']);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('[Classified as: OTHER]', $result);
    }

    public function test_classify_image_strips_non_alpha_chars_from_response(): void
    {
        VisionAgent::fake(["TIMELINE.\n", '[]']); // classify + empty timeline extraction

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('[Classified as: TIMELINE]', $result);
    }

    // ─── PLAIN_SETLIST extraction ────────────────────────────────────────────

    public function test_plain_setlist_extraction_returns_formatted_song_list(): void
    {
        VisionAgent::fake([
            'PLAIN_SETLIST',                              // classify
            '["September", "Uptown Funk", "Happy"]',     // extract
        ]);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('September', $result);
        $this->assertStringContainsString('Uptown Funk', $result);
        $this->assertStringContainsString('CLIENT SONG LIST', $result);
    }

    public function test_plain_setlist_handles_invalid_json_gracefully(): void
    {
        VisionAgent::fake([
            'PLAIN_SETLIST',
            'not valid json at all',
        ]);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('None', $result);
    }

    public function test_plain_setlist_strips_markdown_code_fences(): void
    {
        VisionAgent::fake([
            'PLAIN_SETLIST',
            "```json\n[\"September\"]\n```",
        ]);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('September', $result);
    }

    // ─── TIMELINE extraction ─────────────────────────────────────────────────

    public function test_timeline_extraction_returns_formatted_schedule(): void
    {
        $timeline = json_encode([
            ['time' => '6:00 PM', 'description' => 'Cocktail hour'],
            ['time' => '7:00 PM', 'description' => 'Grand entrance'],
        ]);

        VisionAgent::fake(['TIMELINE', $timeline]);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('6:00 PM: Cocktail hour', $result);
        $this->assertStringContainsString('7:00 PM: Grand entrance', $result);
        $this->assertStringContainsString('EVENT TIMELINE', $result);
    }

    public function test_timeline_handles_items_without_time(): void
    {
        $timeline = json_encode([
            ['time' => null, 'description' => 'Sound check'],
        ]);

        VisionAgent::fake(['TIMELINE', $timeline]);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('Sound check', $result);
        $this->assertStringNotContainsString('null', $result);
    }

    public function test_timeline_handles_invalid_json_gracefully(): void
    {
        VisionAgent::fake(['TIMELINE', 'not json']);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('None', $result);
    }

    // ─── MARKED_SETLIST extraction ───────────────────────────────────────────

    public function test_marked_setlist_parses_highlighted_and_excluded_songs(): void
    {
        $songs = [
            ['id' => 1, 'title' => 'September'],
            ['id' => 2, 'title' => 'Uptown Funk'],
            ['id' => 3, 'title' => 'Happy'],
        ];

        $chunkResponse = implode("\n", [
            '1. September | HIGHLIGHTED',
            '2. Uptown Funk | EXCLUDED',
            '3. Happy | UNMARKED',
        ]);

        VisionAgent::fake(['MARKED_SETLIST', $chunkResponse]);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], $songs);

        $this->assertStringContainsString('September', $result);
        $this->assertStringContainsString('REQUESTED SONGS', $result);
        $this->assertStringContainsString('Uptown Funk', $result);
        $this->assertStringContainsString('EXCLUDED SONGS', $result);
        $this->assertStringNotContainsString('Happy', $result);
    }

    public function test_marked_setlist_reports_none_when_no_songs_marked(): void
    {
        $songs = [['id' => 1, 'title' => 'September']];

        VisionAgent::fake(['MARKED_SETLIST', '1. September | UNMARKED']);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], $songs);

        $this->assertStringContainsString('REQUESTED SONGS (highlighted/starred by client):', $result);
        $this->assertStringContainsString('None', $result);
    }

    public function test_marked_setlist_chunks_large_song_libraries(): void
    {
        // 26 songs triggers two chunks at chunkSize=25
        $songs = array_map(
            fn ($i) => ['id' => $i, 'title' => "Song {$i}"],
            range(1, 26)
        );

        $chunk1 = implode("\n", array_map(fn ($i) => "{$i}. Song {$i} | UNMARKED", range(1, 25)));
        $chunk2 = "26. Song 26 | HIGHLIGHTED";

        VisionAgent::fake([
            'MARKED_SETLIST', // classify
            $chunk1,          // chunk 0
            $chunk2,          // chunk 1
        ]);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], $songs);

        $this->assertStringContainsString('Song 26', $result);
        $this->assertStringContainsString('REQUESTED SONGS', $result);
    }

    // ─── OTHER ───────────────────────────────────────────────────────────────

    public function test_other_image_type_skips_extraction(): void
    {
        VisionAgent::fake(['OTHER']);

        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('No structured extraction', $result);
    }

    // ─── multiple images ─────────────────────────────────────────────────────

    public function test_multiple_images_are_each_classified_and_extracted(): void
    {
        $timeline = json_encode([
            ['time' => '8:00 PM', 'description' => 'First dance'],
        ]);

        VisionAgent::fake([
            'OTHER',    // classify image1
            'TIMELINE', // classify image2
            $timeline,  // extract timeline
        ]);

        $image1 = ['data' => 'aaa', 'media_type' => 'image/jpeg'];
        $image2 = ['data' => 'bbb', 'media_type' => 'image/jpeg'];
        $result = $this->service->testExtractMarkings([$image1, $image2], []);

        $this->assertStringContainsString('[Classified as: OTHER]', $result);
        $this->assertStringContainsString('[Classified as: TIMELINE]', $result);
        $this->assertStringContainsString('First dance', $result);
    }

    // ─── SetlistAgent (callClaude) ────────────────────────────────────────────

    public function test_lookup_song_details_returns_parsed_fields(): void
    {
        $json = json_encode([
            'bpm'      => 120,
            'song_key' => 'A maj',
            'genre'    => 'Pop',
            'artist'   => 'Earth Wind & Fire',
        ]);

        SetlistAgent::fake([$json]);

        $result = $this->service->lookupSongDetails('September', 'Earth Wind & Fire');

        $this->assertSame(120, $result['bpm']);
        $this->assertSame('A maj', $result['song_key']);
        $this->assertSame('Pop', $result['genre']);
        $this->assertSame('Earth Wind & Fire', $result['artist']);
    }

    public function test_lookup_song_details_returns_nulls_on_invalid_json(): void
    {
        SetlistAgent::fake(['not json at all']);

        $result = $this->service->lookupSongDetails('Unknown Song');

        $this->assertNull($result['bpm']);
        $this->assertNull($result['song_key']);
        $this->assertNull($result['genre']);
        $this->assertNull($result['artist']);
    }

    public function test_rerank_queue_returns_sorted_ids(): void
    {
        SetlistAgent::fake(['[3, 1, 2]']);

        $pending = [
            ['id' => 1, 'title' => 'Song A', 'artist' => null, 'genre' => 'Rock', 'bpm' => 120],
            ['id' => 2, 'title' => 'Song B', 'artist' => null, 'genre' => 'Pop',  'bpm' => 90],
            ['id' => 3, 'title' => 'Song C', 'artist' => null, 'genre' => 'R&B',  'bpm' => 100],
        ];

        $result = $this->service->rerankQueue($pending, [], []);

        $this->assertSame([3, 1, 2], $result);
    }

    public function test_rerank_queue_returns_empty_on_invalid_response(): void
    {
        SetlistAgent::fake(['not an array']);

        $result = $this->service->rerankQueue([], [], []);

        $this->assertSame([], $result);
    }
}
