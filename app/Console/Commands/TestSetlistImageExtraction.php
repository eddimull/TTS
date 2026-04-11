<?php

namespace App\Console\Commands;

use App\Services\Ai\Adapters\ClaudeAdapter;
use App\Services\Ai\Adapters\GeminiAdapter;
use App\Services\SetlistAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestSetlistImageExtraction extends Command
{
    protected $signature = 'setlist:test-image-extraction
                            {attachment-id : ID from event_attachments table}
                            {--songs= : Comma-separated list of song titles to use as the library (optional)}
                            {--driver=gemini : Which AI to use for vision: gemini or claude}';

    protected $description = 'Test the image extraction step of setlist AI in isolation';

    public function handle(): void
    {
        $attachmentId = $this->argument('attachment-id');

        $attachment = \DB::table('event_attachments')->find($attachmentId);
        if (!$attachment) {
            $this->error("Attachment ID {$attachmentId} not found.");
            return;
        }

        $supported = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($attachment->mime_type, $supported)) {
            $this->error("Attachment is not a supported image type: {$attachment->mime_type}");
            return;
        }

        $this->info("Loading image: {$attachment->stored_filename} ({$attachment->mime_type})");

        try {
            $data = Storage::disk($attachment->disk)->get($attachment->stored_filename);
        } catch (\Throwable $e) {
            $this->error("Could not load image: {$e->getMessage()}");
            return;
        }

        // Build song list — use --songs option, or pull all songs from DB
        if ($this->option('songs')) {
            $songs = collect(explode(',', $this->option('songs')))
                ->map(fn ($t) => ['id' => 0, 'title' => trim($t)])
                ->toArray();
        } else {
            $songs = \DB::table('songs')
                ->select('id', 'title')
                ->orderBy('title')
                ->get()
                ->map(fn ($s) => (array) $s)
                ->toArray();
        }

        $driver = $this->option('driver');
        $this->info("Driver: {$driver} | Songs in library: " . count($songs) . "\n");

        $adapter = match ($driver) {
            'claude' => new ClaudeAdapter(),
            default  => new GeminiAdapter('gemini-3.1-pro-preview'),
        };

        $service = new SetlistAiService($adapter);

        $image = [
            'data'       => base64_encode($data),
            'media_type' => $attachment->mime_type,
        ];

        $result = $service->testExtractMarkings([$image], $songs);

        $this->line('─────────────────────────────────────────');
        $this->line($result);
        $this->line('─────────────────────────────────────────');
    }
}
