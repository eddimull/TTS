<?php

namespace App\Console\Commands;

use App\Services\SetlistAiService;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestSetlistImageExtraction extends Command
{
    protected $signature = 'setlist:test-image-extraction
                            {attachment-id : ID from event_attachments table}
                            {--songs= : Comma-separated list of song titles to use as the library (optional)}
                            {--driver=gemini : Which AI to use for OCR: gemini or claude}';

    protected $description = 'Test the image extraction step of setlist AI in isolation';

    public function handle(SetlistAiService $service): void
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

        $result = match ($driver) {
            'claude' => $this->runClaude($service, $data, $attachment->mime_type, $songs),
            default  => $this->runGemini($data, $attachment->mime_type, $songs),
        };

        $this->line('─────────────────────────────────────────');
        $this->line($result);
        $this->line('─────────────────────────────────────────');
    }

    private function runClaude(SetlistAiService $service, string $data, string $mimeType, array $songs): string
    {
        $images = [[
            'data'       => base64_encode($data),
            'media_type' => $mimeType,
        ]];

        return $service->testExtractMarkings($images, $songs);
    }

    private function runGemini(string $data, string $mimeType, array $songs): string
    {
        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return 'ERROR: GEMINI_API_KEY is not set in .env';
        }

        $chunkSize = 25;
        $chunks    = collect($songs)->values()->chunk($chunkSize);
        $highlighted = [];
        $excluded    = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $numberedList = $chunk->values()
                ->map(fn ($s, $i) => ($chunkIndex * $chunkSize + $i + 1) . '. ' . $s['title'])
                ->implode("\n");

            $prompt = <<<PROMPT
You are analyzing a band's printed master setlist that a client has physically marked up.

The image shows a printed list of song titles. The client has made physical marks next to some songs.

There are TWO types of client marks:
1. HIGHLIGHTED / REQUESTED — a star (*), asterisk, circle, checkmark, or different-colored text (orange, gold, red) next to the title.
2. CROSSED OUT / EXCLUDED — a line struck through the title.

IMPORTANT — FUZZY TITLE MATCHING: The song titles in the list below may differ slightly from what is printed in the image. The image may use abbreviations, alternate punctuation, shortened titles, or omit subtitles. Match each song to the closest title you can find in the image. For example, "If You Don't Want Me To (The Freeze)" in the list may appear as "If You Don't Want Me To" or "The Freeze" in the image — treat these as the same song.

TASK: For each song in the list below, find the closest matching title in the image and check whether it has a mark next to it.

SONGS TO CHECK:
{$numberedList}

Output exactly one line per song, in the same order:
[number]. [song title] | HIGHLIGHTED or EXCLUDED or UNMARKED

Do not skip any song. If you cannot find a close match in the image or cannot read it clearly, output UNMARKED.
PROMPT;

            $http = new Client(['timeout' => 60]);

            $response = $http->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-pro-preview:generateContent?key={$apiKey}",
                [
                    'json' => [
                        'contents' => [[
                            'parts' => [
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data'      => base64_encode($data),
                                    ],
                                ],
                                ['text' => $prompt],
                            ],
                        ]],
                        'generationConfig' => [
                            'temperature'     => 1,
                            'maxOutputTokens' => 8192,
                        ],
                    ],
                ]
            );

            $body = json_decode($response->getBody()->getContents(), true);
            $raw  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $this->line("  chunk {$chunkIndex} raw:\n{$raw}\n");

            foreach (explode("\n", $raw) as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (preg_match('/^\d+\.\s+(.+?)\s*\|\s*(HIGHLIGHTED|EXCLUDED|UNMARKED)/i', $line, $m)) {
                    $title  = trim($m[1]);
                    $status = strtoupper(trim($m[2]));
                    if ($status === 'HIGHLIGHTED') {
                        $highlighted[] = $title;
                    } elseif ($status === 'EXCLUDED') {
                        $excluded[] = $title;
                    }
                }
            }
        }

        $highlightedText = !empty($highlighted)
            ? implode("\n", array_map(fn ($t) => "- {$t}", $highlighted))
            : 'None';

        $excludedText = !empty($excluded)
            ? implode("\n", array_map(fn ($t) => "- {$t}", $excluded))
            : 'None';

        return "REQUESTED SONGS (highlighted/starred by client):\n{$highlightedText}\n\nEXCLUDED SONGS (crossed out by client):\n{$excludedText}";
    }
}
