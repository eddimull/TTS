<?php

namespace App\Services;

use App\Ai\Agents\SetlistAgent;
use App\Ai\Agents\VisionAgent;
use App\Models\Events;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Base64Image;

class SetlistAiService
{
    public function __construct() {}

    /**
     * Generate a setlist for an event.
     * Returns ['items' => [...], 'event_context' => string, 'image_context' => string[]].
     *
     * @param array         $attachmentImages  Each element: ['data' => base64string, 'media_type' => 'image/jpeg']
     * @param callable|null $progress          fn(string $step, string $status, ?string $detail) — called at each stage
     */
    public function generateSetlist(Events $event, array $songs, ?string $extraContext = null, array $attachmentImages = [], ?callable $progress = null): array
    {
        $progress ??= fn () => null;

        $context = $this->buildEventContext($event);
        $songList = $this->buildSongList($songs);

        $extraSection = $extraContext
            ? "\nBAND LEADER INSTRUCTIONS:\n{$extraContext}\n"
            : '';

        Log::info(empty($attachmentImages) ? 'No attachment images provided' : count($attachmentImages) . ' attachment image(s) provided');

        // Classify each image then extract structured information based on its type.
        // This keeps the noisy vision tasks separate from setlist generation.
        $imageSections   = []; // keyed entries: ['type' => ..., 'content' => ...]
        $attachmentParts = []; // plain text for the prompt

        foreach ($attachmentImages as $i => $image) {
            $label = count($attachmentImages) > 1 ? 'image ' . ($i + 1) : 'attached image';

            $progress("Analysing {$label}…", 'working', null);
            $type = $this->classifyImage($image);
            Log::info('SetlistAiService: image classified', ['type' => $type]);

            $typeLabel = match ($type) {
                'MARKED_SETLIST' => 'marked setlist',
                'PLAIN_SETLIST'  => 'song list',
                'TIMELINE'       => 'event timeline',
                default          => 'other (skipping)',
            };

            $extracted = match ($type) {
                'MARKED_SETLIST' => $this->extractClientMarkingsFromImages([$image], $songs, $progress),
                'PLAIN_SETLIST'  => $this->extractPlainSetlist([$image]),
                'TIMELINE'       => $this->extractTimeline([$image]),
                default          => null,
            };

            $imageSections[] = ['type' => $type, 'content' => $extracted];

            if ($extracted !== null) {
                $attachmentParts[] = $extracted;
            }

            $progress("Analysing {$label}…", 'done', "Recognised as {$typeLabel}");
        }

        $attachmentSection = '';
        if (!empty($attachmentParts)) {
            $attachmentSection = "\nATTACHMENT CONTEXT:\n" . implode("\n\n", $attachmentParts) . "\n";
        }

        $prompt = <<<PROMPT
You are a professional band manager building a setlist for a live performance.

EVENT DETAILS:
{$context}
{$extraSection}{$attachmentSection}
AVAILABLE SONGS (format: ID | Title – Artist | Key | Genre | BPM | Energy | Lead singer | Transitions into):
{$songList}

RULES — follow every rule without exception:

1. EXCLUSIONS ARE ABSOLUTE: Songs are excluded if: (a) the band leader's instructions say to exclude them, (b) they appear crossed out or struck through in any attached image. Do NOT include excluded songs under any circumstances — not to fill time, not to round out a set, not for any reason. A shorter setlist is always preferable to violating an exclusion. If a song is crossed out in an image AND mentioned in the notes as a request, the crossed-out marking wins — do not play it.
2. PLAY/DO-NOT-PLAY: Respect any play or do-not-play lists in the event notes with the same strictness as rule 1.
3. REQUESTED SONGS: Only include a song as a client request if it is EXPLICITLY named in the event notes, band leader instructions, or listed under "REQUESTED SONGS" in the CLIENT MARKINGS section above. Do NOT infer, guess, or add songs you think the client might want — only what is literally listed.
   - If the song IS in the library, include it as {"id": <song_id>, "note": "Client request"} instead of a bare integer.
   - If the song is NOT in the library, include it as {"title": "...", "artist": "...", "note": "Client request — not in library"}.
   Do not silently omit client requests, but also do not fabricate them.
4. HIGHLIGHTED SONGS: Songs listed under "REQUESTED SONGS" in the CLIENT MARKINGS section are client preferences. Include them where possible without violating other rules. Add {"note": "Highlighted"} for any such song. Take note of the genre pattern of requested songs and try to reflect that in the overall setlist.
5. TRANSITIONS: When a song has a "Transitions into" field, place that target song immediately after it in the setlist.
6. ENERGY FLOW: Use the Energy field (Energy: 1/10) to shape the crowd arc. Take into account the BPM and what you already know about a song. Build energy gradually and always end the night on high energy songs (≥7). Avoid placing multiple low-energy songs (≤4) consecutively. After a set break, start with a high-energy song (≥7 if available). Songs with no energy rating should be treated as moderate (5).
7. LEAD SINGERS: Distribute lead singers proportionally. Never have more than 3 consecutive songs with the same lead singer.
8. BREAKS: If the event details specify multiple sets or a total performance duration, insert the string "break" between sets to represent set breaks. Do NOT place a break at the very beginning or very end of the setlist. Use the performance duration and typical ~3.5-minute song length to determine approximately how many songs fit per set.
9. PERFORMANCE TYPE: Consider the type of performance specified in the event details (e.g., full concert, festival set, private event) and structure the setlist accordingly. For example, a festival set may require a shorter, high-energy selection of songs, whereas a full concert may allow for a more gradual build and inclusion of lower-energy songs or a wedding needs to include a mix of crowd-pleasing, familiar songs that appeal to a broad audience and keep the energy appropriate for a family-friendly or celebratory atmosphere.
10. LOCATION: If the the event is taking place in South Louisiana, for example, consider including songs that reflect the local musical culture, such as zydeco, Cajun, or swamp pop influences, where appropriate, to connect with the audience and enhance the event experience.

Return ONLY a valid JSON array in performance order. Each element is one of:
- A song ID integer (plain library song with no special notes)
- {"id": <song_id>, "note": "Client request"} for a library song that was specifically requested by the client
- {"id": <song_id>, "note": "Highlighted"} for a library song that was highlighted or circled in an attached image
- {"title": "...", "artist": "...", "note": "Client request — not in library"} for a requested song not in the library
- The string "break" for set breaks

Example: [42, {"id": 17, "note": "Client request"}, {"title": "Sugar", "artist": "Fall Out Boy", "note": "Client request — not in library"}, "break", 23, 5]

Do not include any explanation, commentary, reasoning, or markdown. Output the raw JSON array and nothing else.
PROMPT;

        Log::info('SetlistAiService: prompt sent to Claude', ['prompt' => $prompt]);

        $progress('Building setlist with AI…', 'working', null);
        $responseText = $this->callClaude($prompt);
        Log::info('SetlistAiService: response received from Claude', ['response' => $responseText]);

        $parsed = $this->parseSetlistItems($responseText);
        $songCount = count(array_filter($parsed, fn ($i) => $i !== 'break'));
        $progress('Building setlist with AI…', 'done', "{$songCount} songs arranged");

        $songMap = collect($songs)->keyBy('id');
        $readable = collect($parsed)->map(function ($item) use ($songMap) {
            if ($item === 'break') return '[BREAK]';
            if (is_int($item)) {
                $s = $songMap->get($item);
                return $s ? "#{$item} {$s['title']}" : "#{$item} (unknown)";
            }
            if (is_array($item) && isset($item['id'])) {
                $s = $songMap->get($item['id']);
                $title = $s ? $s['title'] : "#{$item['id']} (unknown)";
                return "{$title} [{$item['note']}]";
            }
            if (is_array($item) && isset($item['title'])) {
                return "{$item['title']} (not in library)";
            }
            return '(unknown)';
        })->values()->toArray();

        Log::info('SetlistAiService: parsed setlist from Claude', ['order' => $readable]);

        return [
            'items'         => $parsed,
            'event_context' => trim($context . ($extraContext ? "\n\nBand leader instructions: {$extraContext}" : '')),
            'image_context' => $imageSections,
        ];
    }

    /**
     * Re-rank the remaining pending songs based on crowd reactions so far.
     * Returns an array of song IDs in new recommended order.
     */
    public function rerankQueue(array $pendingSongs, array $reactionLog, array $playedSongs): array
    {
        $pendingList = collect($pendingSongs)->map(fn($s) => sprintf(
            'ID:%d | %s%s | Genre: %s | BPM: %s',
            $s['id'],
            $s['title'],
            $s['artist'] ? ' – ' . $s['artist'] : '',
            $s['genre'] ?? 'Unknown',
            $s['bpm'] ?? 'Unknown'
        ))->implode("\n");

        $reactionsText = collect($reactionLog)->map(fn($r) => sprintf(
            '%s: %s (%s)',
            $r['title'],
            $r['reaction'],
            $r['genre'] ?? 'Unknown'
        ))->implode("\n") ?: 'None yet';

        $playedText = collect($playedSongs)->map(fn($s) => sprintf(
            '%s%s',
            $s['title'],
            $s['artist'] ? ' – ' . $s['artist'] : ''
        ))->implode(', ') ?: 'None yet';

        $prompt = <<<PROMPT
You are managing a live band performance in real-time.

SONGS ALREADY PLAYED: {$playedText}

CROWD REACTIONS SO FAR:
{$reactionsText}

REMAINING SONGS TO RE-ORDER:
{$pendingList}

Re-rank the remaining songs to optimize crowd energy based on the reactions above.
- Penalize songs similar in genre/energy to songs that got thumbs down
- Boost songs similar to songs that got thumbs up
- Maintain good energy flow

Return ONLY a valid JSON array of the song IDs in new recommended order. Example:
[15, 8, 22, 4]

Do not include any explanation, reasoning, or markdown. Output the raw JSON array and nothing else.
PROMPT;

        $responseText = $this->callClaude($prompt);

        return $this->parseSongIds($responseText);
    }

    /**
     * Suggest the single best next song for a live dynamic session.
     * Returns a song ID, or null if nothing suitable.
     */
    public function suggestNext(Events $event, array $availableSongs, array $playedSongs, array $reactionLog, bool $afterBreak = false): ?int
    {
        $context = $this->buildEventContext($event);
        $songList = $this->buildSongList($availableSongs);

        $playedText = collect($playedSongs)->map(fn($s) => sprintf(
            '%s%s',
            $s['title'],
            !empty($s['artist']) ? ' – ' . $s['artist'] : ''
        ))->implode(', ') ?: 'None yet';

        $reactionsText = collect($reactionLog)->map(fn($r) => sprintf(
            '%s (%s): %s',
            $r['title'],
            $r['genre'] ?? 'Unknown',
            $r['reaction']
        ))->implode("\n") ?: 'None yet';

        $afterBreakSection = $afterBreak
            ? "\nPOST-BREAK ENERGY RESET: The band is returning from a set break. Choose a high-energy, crowd-engaging song to re-energize the room. Prioritize uptempo songs (higher BPM), strong crowd favourites, or songs that previously received positive reactions. Do NOT start with a slow or low-energy song.\n"
            : '';

        $excludeSection = '';

        $prompt = <<<PROMPT
You are a professional band manager choosing the next song for a live performance already in progress.

EVENT DETAILS:
{$context}

SONGS ALREADY PLAYED THIS SHOW: {$playedText}

CROWD REACTIONS SO FAR:
{$reactionsText}
{$afterBreakSection}{$excludeSection}
AVAILABLE SONGS (format: ID | Title – Artist | Key | Genre | BPM | Energy | Lead singer | Transitions into):
{$songList}

Choose the single best next song to play right now. Consider:
1. ENERGY FLOW: Match or build on the current crowd energy based on reactions. After thumbs-down songs, shift genre or tempo. After thumbs-up, stay in a similar vibe or build higher.
2. VARIETY: Do not repeat genres or lead singers back-to-back unless the crowd reaction strongly warrants it.
3. EVENT CONTEXT: Respect the event type, venue, and any special notes.
4. TRANSITIONS: The "Transitions into" field is informational only — transitions are enforced before you are called, so ignore this field when choosing.
5. EXCLUSIONS: Any artists, songs, or genres mentioned as excluded in the event notes are ABSOLUTE — never suggest them.

Return ONLY a valid JSON array containing the single chosen song ID. Example: [42]

Do not include any explanation, reasoning, or markdown. Output the raw JSON array and nothing else.
PROMPT;

        $responseText = $this->callClaude($prompt);
        $ids = $this->parseSongIds($responseText);

        return $ids[0] ?? null;
    }

    /**
     * Public wrapper for testing in isolation via artisan command.
     * Classifies each image then routes to the appropriate extractor.
     */
    public function testExtractMarkings(array $images, array $songs): string
    {
        $sections = [];
        foreach ($images as $image) {
            $type = $this->classifyImage($image);
            Log::info('SetlistAiService: test image classified', ['type' => $type]);

            $extracted = match ($type) {
                'MARKED_SETLIST' => $this->extractClientMarkingsFromImages([$image], $songs),
                'PLAIN_SETLIST'  => $this->extractPlainSetlist([$image]),
                'TIMELINE'       => $this->extractTimeline([$image]),
                default          => null,
            };

            $sections[] = "[Classified as: {$type}]";
            if ($extracted !== null) {
                $sections[] = $extracted;
            } else {
                $sections[] = "(No structured extraction for this image type — passed as general context only.)";
            }
        }

        return implode("\n\n", $sections);
    }

    /**
     * Pre-flight: classify an image so the correct extraction strategy can be applied.
     * Returns one of: MARKED_SETLIST, PLAIN_SETLIST, TIMELINE, OTHER.
     */
    private function classifyImage(array $image): string
    {
        $prompt = <<<PROMPT
Look at this image and classify it into exactly one of these four categories:

MARKED_SETLIST — a printed or typed list of song titles where the client has added handwritten marks (highlights, circles, stars, asterisks, checkmarks, crossed-out lines, or colored annotations).
PLAIN_SETLIST — a printed, typed, or handwritten list of song titles with no client marks or annotations.
TIMELINE — a schedule, run-of-show, or timeline for an event (e.g. wedding reception order, ceremony schedule, show itinerary with times).
OTHER — anything else (stage plot, photo, logo, contract, diagram, etc.).

Reply with exactly one word: MARKED_SETLIST, PLAIN_SETLIST, TIMELINE, or OTHER.
PROMPT;

        $attachment = new Base64Image($image['data'], $image['media_type']);

        try {
            $raw  = trim((string) (new VisionAgent())->prompt($prompt, [$attachment], timeout: 120));
            $type = strtoupper(preg_replace('/[^A-Z_]/', '', $raw));

            if (!in_array($type, ['MARKED_SETLIST', 'PLAIN_SETLIST', 'TIMELINE', 'OTHER'])) {
                Log::warning('SetlistAiService: unexpected image classification', ['raw' => $raw]);
                return 'OTHER';
            }

            return $type;
        } catch (\Throwable $e) {
            Log::error('SetlistAiService: image classification failed', ['error' => $e->getMessage()]);
            return 'OTHER';
        }
    }

    /**
     * Extract song titles from a plain (unmarked) setlist image.
     * Returns a formatted string listing the songs as client requests.
     */
    private function extractPlainSetlist(array $images): string
    {
        $prompt = <<<PROMPT
This image contains a list of song titles provided by a client.

Read every song title visible in the image and return them as a JSON array of strings, in the order they appear.

Example: ["September", "Can't Stop the Feeling", "Uptown Funk"]

Return ONLY the JSON array. No explanation, no markdown.
PROMPT;

        $attachment = new Base64Image($images[0]['data'], $images[0]['media_type']);
        $raw  = (string) (new VisionAgent())->prompt($prompt, [$attachment], timeout: 120);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
        $titles = json_decode($json, true);

        if (!is_array($titles)) {
            Log::warning('SetlistAiService: could not parse plain setlist', ['raw' => $raw]);
            return "CLIENT SONG LIST (from image):\nNone";
        }

        $list = implode("\n", array_map(fn ($t) => "- {$t}", $titles));
        return "CLIENT SONG LIST (from attached image — treat as requested songs):\n{$list}";
    }

    /**
     * Extract event timeline / run-of-show from a schedule image.
     * Returns a formatted string with time-ordered event notes.
     */
    private function extractTimeline(array $images): string
    {
        $prompt = <<<PROMPT
This image contains an event timeline, run-of-show, or schedule.

Extract every item you can read and return them as a JSON array of objects with "time" and "description" keys.

Example: [{"time": "6:00 PM", "description": "Cocktail hour — background music"}, {"time": "7:00 PM", "description": "Grand entrance"}]

If no time is listed for an item, use null for "time". Return ONLY the JSON array. No explanation, no markdown.
PROMPT;

        $attachment = new Base64Image($images[0]['data'], $images[0]['media_type']);
        $raw  = (string) (new VisionAgent())->prompt($prompt, [$attachment], timeout: 120);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
        $items = json_decode($json, true);

        if (!is_array($items)) {
            Log::warning('SetlistAiService: could not parse timeline', ['raw' => $raw]);
            return "EVENT TIMELINE (from image):\nNone";
        }

        $lines = array_map(function ($item) {
            $time = $item['time'] ?? null;
            $desc = $item['description'] ?? '';
            return $time ? "{$time}: {$desc}" : $desc;
        }, $items);

        $list = implode("\n", $lines);
        return "EVENT TIMELINE (from attached image — use for set breaks and performance timing):\n{$list}";
    }

    /**
     * Extract client markings from a marked-up setlist image using Gemini.
     * Chunks the song library and checks each title against the image.
     */
    private function extractClientMarkingsFromImages(array $images, array $songs, ?callable $progress = null): string
    {
        $progress ??= fn () => null;
        $highlighted = [];
        $excluded    = [];
        $image       = $images[0]; // one image per classification call

        $chunkSize  = 25;
        $chunks     = collect($songs)->values()->chunk($chunkSize);
        $totalSongs = count($songs);

        foreach ($chunks as $chunkIndex => $chunk) {
            $start = $chunkIndex * $chunkSize + 1;
            $end   = min($start + $chunkSize - 1, $totalSongs);
            $progress('Reading marked setlist…', 'working', "Checking songs {$start}–{$end} of {$totalSongs}");
            $numberedList = $chunk->values()
                ->map(fn ($s, $i) => ($chunkIndex * $chunkSize + $i + 1) . '. ' . $s['title'])
                ->implode("\n");

            $extractionPrompt = <<<PROMPT
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

            $attachment = new Base64Image($image['data'], $image['media_type']);
            $raw = (string) (new VisionAgent())->prompt($extractionPrompt, [$attachment], timeout: 120);
            Log::info("SetlistAiService: marked setlist chunk {$chunkIndex}", ['raw' => $raw]);

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

    private function buildEventContext(Events $event): string
    {
        $lines = [];

        $lines[] = 'Event Title: ' . ($event->title ?? 'Unknown');
        $lines[] = 'Date: ' . ($event->date?->toFormattedDateString() ?? 'Unknown');
        $lines[] = 'Event Type: ' . ($event->type?->name ?? 'Unknown');

        if ($event->eventable) {
            $booking = $event->eventable;

            if (!empty($booking->venue_name)) {
                $venueLine = 'Venue: ' . $booking->venue_name;
                if (!empty($booking->venue_address)) {
                    $venueLine .= ' — ' . $booking->venue_address;
                }
                $lines[] = $venueLine;
            }

            if (!empty($booking->start_time) && !empty($booking->end_time)) {
                $start   = $booking->start_time->format('g:i A');
                $end     = $booking->end_time->format('g:i A');
                $minutes = (int) round($booking->duration * 60);
                $lines[] = "Performance Time: {$start} – {$end} ({$minutes} minutes total)";
            }
        } elseif ($event->time) {
            $lines[] = 'Start Time: ' . $event->time;
        }

        if ($event->notes) {
            $lines[] = 'Client Notes / Special Requests: ' . strip_tags($event->notes);
        }

        if ($event->roster_members && count($event->roster_members) > 0) {
            $lines[] = 'Performing Musicians:';
            foreach ($event->roster_members as $member) {
                $role    = !empty($member['role']) ? " ({$member['role']})" : '';
                $lines[] = "  - {$member['name']}{$role}";
            }
        }

        return implode("\n", $lines);
    }

    private function buildSongList(array $songs): string
    {
        return collect($songs)->map(fn($s) => sprintf(
            'ID:%d | %s%s | Key: %s | Genre: %s | BPM: %s | Energy: %s | Lead Singer: %s%s',
            $s['id'],
            $s['title'],
            !empty($s['artist']) ? ' – ' . $s['artist'] : '',
            $s['song_key'] ?? '—',
            $s['genre'] ?? '—',
            $s['bpm'] ?? '—',
            isset($s['energy']) ? $s['energy'] . '/10' : '—',
            $s['lead_singer'] ?? 'Instrumental',
            !empty($s['transition_song']) ? ' | Transitions into: ' . $s['transition_song'] : ''
        ))->implode("\n");
    }

    /**
     * Look up song details (key, BPM, genre, artist) using Claude as a fallback.
     *
     * @return array{bpm: int|null, song_key: string|null, genre: string|null, artist: string|null}
     */
    public function lookupSongDetails(string $title, ?string $artist = null): array
    {
        $artistLine = $artist ? " by {$artist}" : '';
        $prompt = <<<PROMPT
You are a music reference tool. Return the details for the song "{$title}"{$artistLine}.

Respond with ONLY a JSON object, no explanation:
{
  "bpm": <integer or null>,
  "song_key": "<note> <accidental if any> <maj or min>, e.g. 'Bb min' or 'E maj', or null if unknown>",
  "genre": "<primary genre, e.g. Rock, Country, R&B, or null if unknown>",
  "artist": "<canonical artist name, or null if unknown>"
}

If you are not confident about a value, use null rather than guessing.
PROMPT;

        try {
            $raw = $this->callClaude($prompt);
            Log::info('Claude song lookup raw', ['raw' => $raw]);
            // Strip markdown code fences if present
            $json = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
            $data = json_decode($json, true);
            Log::info('Claude song lookup parsed', ['data' => $data]);

            if (!is_array($data)) {
                return ['bpm' => null, 'song_key' => null, 'genre' => null, 'artist' => null];
            }

            return [
                'bpm'      => isset($data['bpm']) ? (int) $data['bpm'] : null,
                'song_key' => $data['song_key'] ?? null,
                'genre'    => $data['genre'] ?? null,
                'artist'   => $data['artist'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Claude song lookup failed', ['error' => $e->getMessage(), 'title' => $title]);
            return ['bpm' => null, 'song_key' => null, 'genre' => null, 'artist' => null];
        }
    }

    /**
     * Send a prompt to Claude via the Laravel AI SDK.
     *
     * @param  array  $history  Pre-built multi-turn messages [{role, content},...]. Prepended as conversation history.
     */
    private function callClaude(string $prompt, array $history = []): string
    {
        $agent = new SetlistAgent();

        if (!empty($history)) {
            $messages = collect($history)->map(
                fn ($m) => new \Laravel\Ai\Messages\Message($m['role'], $m['content'])
            )->all();

            $agent = $agent->withHistory($messages);
        }

        return (string) $agent->prompt($prompt, timeout: 120);
    }

    /**
     * Refine an existing setlist via conversational instructions.
     *
     * @param  array  $currentSetlist  Formatted songs from formatSetlist() — each has type, song_id, title, artist, notes, position
     * @param  array  $chatHistory     Alternating [{role:'user',content:...},{role:'assistant',content:...}] prior turns
     * @param  string $newMessage      The user's latest refinement instruction
     * @return array{setlist: array, summary: string}
     */
    public function refineSetlist(Events $event, array $songs, array $currentSetlist, array $chatHistory, string $newMessage): array
    {
        $context  = $this->buildEventContext($event);
        $songList = $this->buildSongList($songs);

        $currentSetlistText = collect($currentSetlist)->map(function ($entry, $i) {
            if ($entry['type'] === 'break') {
                return ($i + 1) . '. [SET BREAK]';
            }
            $line = ($i + 1) . '. ';
            $line .= $entry['title'] ?? 'Unknown';
            if (!empty($entry['artist'])) {
                $line .= ' – ' . $entry['artist'];
            }
            if (!empty($entry['song_id'])) {
                $line .= ' (ID:' . $entry['song_id'] . ')';
            } else {
                $line .= ' (custom)';
            }
            if (!empty($entry['notes'])) {
                $line .= ' [Note: ' . $entry['notes'] . ']';
            }
            return $line;
        })->implode("\n");

        $systemPrompt = <<<PROMPT
You are a professional band manager refining a live performance setlist based on conversational instructions.

EVENT DETAILS:
{$context}

AVAILABLE SONGS (format: ID | Title – Artist | Key | Genre | BPM | Energy | Lead singer | Transitions into):
{$songList}

CURRENT SETLIST:
{$currentSetlistText}

The user will give you instructions to refine this setlist. Apply ONLY the changes they request — leave everything else unchanged. When referring to songs not in the library, use a custom object.

Each element in the setlist array is one of:
- A song ID integer (for songs in the library)
- The string "break" (for set breaks)
- An object {"title": "...", "artist": "...", "note": "Client request — not in library"} for songs not in the library

Respond with ONLY a valid JSON object with exactly two keys:
{
  "setlist": [...same array format as above...],
  "summary": "A concise 1-3 sentence plain-English description of what changed, e.g. 'Moved September from position 4 to position 12. Replaced Into the Mystic with Mercy Mercy Me.'"
}

Do not include any explanation, markdown, or text outside the JSON object.
PROMPT;

        // Build multi-turn messages: system prompt as first user message, then history
        $history = [
            ['role' => 'user',      'content' => $systemPrompt],
            ['role' => 'assistant', 'content' => 'Understood. I\'m ready to refine the setlist. What changes would you like?'],
        ];

        foreach ($chatHistory as $turn) {
            $history[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $responseText = $this->callClaude($newMessage, $history);

        return $this->parseRefineResponse($responseText);
    }

    private function parseRefineResponse(string $text): array
    {
        $text = preg_replace('/```[a-z]*\n?/', '', $text);
        $text = trim($text);

        // Extract outermost JSON object
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $decoded = json_decode($text, true);

        if (!is_array($decoded) || !isset($decoded['setlist'])) {
            Log::warning('SetlistAiService: could not parse refine response', ['text' => $text]);
            return ['setlist' => [], 'summary' => 'Could not parse AI response.'];
        }

        $setlist = [];
        foreach ($decoded['setlist'] as $item) {
            if ($item === 'break') {
                $setlist[] = 'break';
            } elseif (is_int($item) && $item > 0) {
                $setlist[] = $item;
            } elseif (is_numeric($item) && (int) $item > 0) {
                $setlist[] = (int) $item;
            } elseif (is_array($item) && isset($item['id']) && is_numeric($item['id']) && (int) $item['id'] > 0) {
                $setlist[] = [
                    'id'   => (int) $item['id'],
                    'note' => isset($item['note']) ? (string) $item['note'] : 'Client request',
                ];
            } elseif (is_array($item) && !empty($item['title'])) {
                $setlist[] = [
                    'title'  => (string) $item['title'],
                    'artist' => isset($item['artist']) ? (string) $item['artist'] : null,
                    'note'   => isset($item['note'])   ? (string) $item['note']   : 'Client request — not in library',
                ];
            }
        }

        return [
            'setlist' => $setlist,
            'summary' => isset($decoded['summary']) ? (string) $decoded['summary'] : '',
        ];
    }

    /**
     * Load image attachments from storage and return base64-encoded content blocks.
     * Only processes image mime types; skips PDFs and other non-image files.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $attachments
     * @return array
     */
    public function buildImageBlocks($attachments): array
    {
        $supported = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $blocks = [];

        foreach ($attachments as $attachment) {
            if (!in_array($attachment->mime_type, $supported)) {
                continue;
            }

            try {
                $data = Storage::disk($attachment->disk)->get($attachment->stored_filename);
                $blocks[] = [
                    'data' => base64_encode($data),
                    'media_type' => $attachment->mime_type,
                ];
            } catch (\Throwable $e) {
                Log::warning('SetlistAiService: could not load attachment', [
                    'attachment_id' => $attachment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $blocks;
    }

    /**
     * Parse a setlist response that may contain song IDs (integers), "break" strings,
     * or custom-song objects {"title":..., "artist":..., "note":...}.
     *
     * Returns an array of: int (song ID), string "break", or array (custom song).
     */
    private function parseSetlistItems(string $text): array
    {
        $text = preg_replace('/```[a-z]*\n?/', '', $text);

        // Find the outermost JSON array — can now contain objects so we use a
        // bracket-depth scan instead of a restrictive regex.
        $start = strpos($text, '[');
        $end   = strrpos($text, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $text  = trim($text);
        $items = json_decode($text, true);

        if (!is_array($items)) {
            Log::warning('SetlistAiService: could not parse setlist items from response', ['text' => $text]);
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if ($item === 'break') {
                $result[] = 'break';
            } elseif (is_int($item) && $item > 0) {
                $result[] = $item;
            } elseif (is_numeric($item) && (int) $item > 0) {
                $result[] = (int) $item;
            } elseif (is_array($item) && isset($item['id']) && is_numeric($item['id']) && (int) $item['id'] > 0) {
                // Library song with a client request note
                $result[] = [
                    'id'   => (int) $item['id'],
                    'note' => isset($item['note']) ? (string) $item['note'] : 'Client request',
                ];
            } elseif (is_array($item) && !empty($item['title'])) {
                // Custom song not in library
                $result[] = [
                    'title'  => (string) $item['title'],
                    'artist' => isset($item['artist']) ? (string) $item['artist'] : null,
                    'note'   => isset($item['note'])   ? (string) $item['note']   : 'Client request — not in library',
                ];
            }
        }

        return $result;
    }

    private function parseSongIds(string $text): array
    {
        // Strip markdown code fences
        $text = preg_replace('/```[a-z]*\n?/', '', $text);

        // Extract the last JSON array in the response — handles cases where the
        // model thinks out loud before producing the final answer
        preg_match_all('/\[[\d,\s]+\]/', $text, $matches);
        if (!empty($matches[0])) {
            $text = end($matches[0]);
        }

        $text = trim($text);
        $ids = json_decode($text, true);

        if (!is_array($ids)) {
            Log::warning('SetlistAiService: could not parse song IDs from response', ['text' => $text]);
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }
}
