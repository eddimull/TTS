<?php

namespace Tests\Unit\Services;

use App\Services\Ai\Contracts\AiAdapterInterface;
use App\Services\SetlistAiService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SetlistAiServiceTest extends TestCase
{
    private AiAdapterInterface $adapter;
    private SetlistAiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = Mockery::mock(AiAdapterInterface::class);
        $this->service = new SetlistAiService($this->adapter, 'test-key');
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── classifyImage ───────────────────────────────────────────────────────

    public function test_classify_image_returns_marked_setlist(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()
            ->andReturn('MARKED_SETLIST');

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('[Classified as: MARKED_SETLIST]', $result);
    }

    public function test_classify_image_returns_other_for_unrecognised_response(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->andReturn('SOMETHING_UNEXPECTED');

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('[Classified as: OTHER]', $result);
    }

    public function test_classify_image_strips_non_alpha_chars_from_response(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        // Model adds trailing punctuation sometimes
        $this->adapter->shouldReceive('queryWithImage')
            ->andReturn("TIMELINE.\n");

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('[Classified as: TIMELINE]', $result);
    }

    // ─── PLAIN_SETLIST extraction ────────────────────────────────────────────

    public function test_plain_setlist_extraction_returns_formatted_song_list(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()
            ->andReturn('PLAIN_SETLIST')                          // classify
            ->shouldReceive('queryWithImage')
            ->once()
            ->andReturn('["September", "Uptown Funk", "Happy"]'); // extract

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('September', $result);
        $this->assertStringContainsString('Uptown Funk', $result);
        $this->assertStringContainsString('CLIENT SONG LIST', $result);
    }

    public function test_plain_setlist_handles_invalid_json_gracefully(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('PLAIN_SETLIST')
            ->shouldReceive('queryWithImage')
            ->once()->andReturn('not valid json at all');

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('None', $result);
    }

    public function test_plain_setlist_strips_markdown_code_fences(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('PLAIN_SETLIST')
            ->shouldReceive('queryWithImage')
            ->once()->andReturn("```json\n[\"September\"]\n```");

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('September', $result);
    }

    // ─── TIMELINE extraction ─────────────────────────────────────────────────

    public function test_timeline_extraction_returns_formatted_schedule(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $timeline = json_encode([
            ['time' => '6:00 PM', 'description' => 'Cocktail hour'],
            ['time' => '7:00 PM', 'description' => 'Grand entrance'],
        ]);

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('TIMELINE')
            ->shouldReceive('queryWithImage')
            ->once()->andReturn($timeline);

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('6:00 PM: Cocktail hour', $result);
        $this->assertStringContainsString('7:00 PM: Grand entrance', $result);
        $this->assertStringContainsString('EVENT TIMELINE', $result);
    }

    public function test_timeline_handles_items_without_time(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $timeline = json_encode([
            ['time' => null, 'description' => 'Sound check'],
        ]);

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('TIMELINE')
            ->shouldReceive('queryWithImage')
            ->once()->andReturn($timeline);

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('Sound check', $result);
        $this->assertStringNotContainsString('null', $result);
    }

    public function test_timeline_handles_invalid_json_gracefully(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('TIMELINE')
            ->shouldReceive('queryWithImage')
            ->once()->andReturn('not json');

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('None', $result);
    }

    // ─── MARKED_SETLIST extraction ───────────────────────────────────────────

    public function test_marked_setlist_parses_highlighted_and_excluded_songs(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
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

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('MARKED_SETLIST')
            ->shouldReceive('queryWithImage')
            ->once()->andReturn($chunkResponse);

        $result = $this->service->testExtractMarkings([$image], $songs);

        $this->assertStringContainsString('September', $result);
        $this->assertStringContainsString('REQUESTED SONGS', $result);
        $this->assertStringContainsString('Uptown Funk', $result);
        $this->assertStringContainsString('EXCLUDED SONGS', $result);
        $this->assertStringNotContainsString('Happy', $result);
    }

    public function test_marked_setlist_reports_none_when_no_songs_marked(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        $songs = [['id' => 1, 'title' => 'September']];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('MARKED_SETLIST')
            ->shouldReceive('queryWithImage')
            ->once()->andReturn('1. September | UNMARKED');

        $result = $this->service->testExtractMarkings([$image], $songs);

        $this->assertStringContainsString('REQUESTED SONGS (highlighted/starred by client):', $result);
        $this->assertStringContainsString('None', $result);
    }

    public function test_marked_setlist_chunks_large_song_libraries(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];
        // 26 songs triggers two chunks at chunkSize=25
        $songs = array_map(
            fn ($i) => ['id' => $i, 'title' => "Song {$i}"],
            range(1, 26)
        );

        $chunk1 = implode("\n", array_map(fn ($i) => "{$i}. Song {$i} | UNMARKED", range(1, 25)));
        $chunk2 = "26. Song 26 | HIGHLIGHTED";

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('MARKED_SETLIST') // classify
            ->shouldReceive('queryWithImage')
            ->once()->andReturn($chunk1)           // chunk 0
            ->shouldReceive('queryWithImage')
            ->once()->andReturn($chunk2);           // chunk 1

        $result = $this->service->testExtractMarkings([$image], $songs);

        $this->assertStringContainsString('Song 26', $result);
        $this->assertStringContainsString('REQUESTED SONGS', $result);
    }

    // ─── OTHER ───────────────────────────────────────────────────────────────

    public function test_other_image_type_skips_extraction(): void
    {
        $image = ['data' => 'abc', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('OTHER');

        $result = $this->service->testExtractMarkings([$image], []);

        $this->assertStringContainsString('No structured extraction', $result);
    }

    // ─── multiple images ─────────────────────────────────────────────────────

    public function test_multiple_images_are_each_classified_and_extracted(): void
    {
        $image1 = ['data' => 'aaa', 'media_type' => 'image/jpeg'];
        $image2 = ['data' => 'bbb', 'media_type' => 'image/jpeg'];

        $this->adapter->shouldReceive('queryWithImage')
            ->once()->andReturn('OTHER')           // classify image1
            ->shouldReceive('queryWithImage')
            ->once()->andReturn('TIMELINE')         // classify image2
            ->shouldReceive('queryWithImage')
            ->once()->andReturn(json_encode([       // extract timeline
                ['time' => '8:00 PM', 'description' => 'First dance'],
            ]));

        $result = $this->service->testExtractMarkings([$image1, $image2], []);

        $this->assertStringContainsString('[Classified as: OTHER]', $result);
        $this->assertStringContainsString('[Classified as: TIMELINE]', $result);
        $this->assertStringContainsString('First dance', $result);
    }
}
