<?php

namespace App\Services;

use App\Models\Events;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SetlistAiService
{
    private Client $http;
    private string $apiKey;
    private string $model = 'claude-sonnet-4-6';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key');
        $this->http = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'timeout' => 60,
        ]);
    }

    /**
     * Generate a setlist for an event.
     * Returns an array of items: integers (song IDs) or the string "break".
     *
     * @param array $attachmentImages  Each element: ['data' => base64string, 'media_type' => 'image/jpeg']
     */
    public function generateSetlist(Events $event, array $songs, ?string $extraContext = null, array $attachmentImages = []): array
    {
        $context = $this->buildEventContext($event);
        $songList = $this->buildSongList($songs);

        $extraSection = $extraContext
            ? "\nBAND LEADER INSTRUCTIONS:\n{$extraContext}\n"
            : '';

        $attachmentSection = !empty($attachmentImages)
            ? "\nATTACHMENT IMAGES: One or more images are attached showing a handwritten or printed song request list from the client. Study each image carefully before building the setlist:\n- Any song that is CROSSED OUT, STRUCK THROUGH, or has a line drawn through it is FORBIDDEN. Treat it exactly like an exclusion in rule 1 — do not include it under any circumstances, even if it appears in the event notes.\n- Any song that is HIGHLIGHTED or circled is a must-play request.\n- When in doubt about whether a marking indicates exclusion, assume it does and leave the song out.\n"
            : '';

        $prompt = <<<PROMPT
You are a professional band manager building a setlist for a live performance.

EVENT DETAILS:
{$context}
{$extraSection}{$attachmentSection}
AVAILABLE SONGS (format: ID | Title – Artist | Key | Genre | BPM | Lead singer | Transitions into):
{$songList}

RULES — follow every rule without exception:

1. EXCLUSIONS ARE ABSOLUTE: Songs are excluded if: (a) the band leader's instructions say to exclude them, (b) they appear crossed out or struck through in any attached image. Do NOT include excluded songs under any circumstances — not to fill time, not to round out a set, not for any reason. A shorter setlist is always preferable to violating an exclusion. If a song is crossed out in an image AND mentioned in the notes as a request, the crossed-out marking wins — do not play it.
2. PLAY/DO-NOT-PLAY: Respect any play or do-not-play lists in the event notes with the same strictness as rule 1.
3. REQUESTED SONGS: If the event notes, band leader instructions, or attached images reference a specific song (that is NOT crossed out):
   - If it IS in the library, include it as {"id": <song_id>, "note": "Client request"} instead of a bare integer.
   - If it is NOT in the library, include it as {"title": "...", "artist": "...", "note": "Client request — not in library"}.
   Do not silently omit client requests in either case.
4. HIGHLIGHTED SONGS: Songs highlighted or circled in any attached images are must-play requests — include them unless they conflict with rule 1.
5. TRANSITIONS: When a song has a "Transitions into" field, place that target song immediately after it in the setlist.
6. ENERGY FLOW: Vary genres and BPM to manage crowd energy. Do not cluster all slow songs together.
7. LEAD SINGERS: Vary lead singers throughout the set when possible.
8. BREAKS: If the event details specify multiple sets or a total performance duration, insert the string "break" between sets to represent set breaks. Do NOT place a break at the very beginning or very end of the setlist. Use the performance duration and typical ~3.5-minute song length to determine approximately how many songs fit per set.

Return ONLY a valid JSON array in performance order. Each element is one of:
- A song ID integer (plain library song with no special notes)
- {"id": <song_id>, "note": "Client request"} for a library song that was specifically requested by the client
- {"title": "...", "artist": "...", "note": "Client request — not in library"} for a requested song not in the library
- The string "break" for set breaks

Example: [42, {"id": 17, "note": "Client request"}, {"title": "Sugar", "artist": "Fall Out Boy", "note": "Client request — not in library"}, "break", 23, 5]

Do not include any explanation, commentary, reasoning, or markdown. Output the raw JSON array and nothing else.
PROMPT;

        $responseText = $this->callClaude($prompt, $attachmentImages);

        return $this->parseSetlistItems($responseText);
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
AVAILABLE SONGS (format: ID | Title – Artist | Key | Genre | BPM | Lead singer | Transitions into):
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

    private function buildEventContext(Events $event): string
    {
        $lines = [];

        $lines[] = 'Title: ' . ($event->title ?? 'Unknown');
        $lines[] = 'Date: ' . ($event->date?->toFormattedDateString() ?? 'Unknown');

        if ($event->type) {
            $lines[] = 'Event Type: ' . $event->type->name;
        }

        if ($event->eventable) {
            $booking = $event->eventable;

            if (!empty($booking->venue_name)) {
                $lines[] = 'Venue: ' . $booking->venue_name;
            }

            if (!empty($booking->start_time) && !empty($booking->end_time)) {
                $start = $booking->start_time->format('g:i A');
                $end   = $booking->end_time->format('g:i A');
                $lines[] = "Performance Time: {$start} – {$end}";

                $duration = $booking->duration; // hours (float)
                $minutes  = (int) round($duration * 60);
                $lines[] = "Total Performance Duration: {$minutes} minutes";
            }
        } elseif ($event->time) {
            $lines[] = 'Start Time: ' . $event->time;
        }

        if ($event->notes) {
            $lines[] = 'Notes / Special Requests: ' . strip_tags($event->notes);
        }

        if ($event->roster_members && count($event->roster_members) > 0) {
            $roster = collect($event->roster_members)->pluck('name')->implode(', ');
            $lines[] = 'Performing Musicians: ' . $roster;
        }

        return implode("\n", $lines);
    }

    private function buildSongList(array $songs): string
    {
        return collect($songs)->map(fn($s) => sprintf(
            'ID:%d | %s%s | Key: %s | Genre: %s | BPM: %s | Lead: %s%s',
            $s['id'],
            $s['title'],
            !empty($s['artist']) ? ' – ' . $s['artist'] : '',
            $s['song_key'] ?? '—',
            $s['genre'] ?? '—',
            $s['bpm'] ?? '—',
            $s['lead_singer'] ?? 'Instrumental',
            !empty($s['transition_song']) ? ' | Transitions into: ' . $s['transition_song'] : ''
        ))->implode("\n");
    }

    /**
     * @param array|null $messages  Pre-built multi-turn messages array. When provided,
     *                              $prompt and $images are ignored.
     */
    private function callClaude(string $prompt, array $images = [], ?array $messages = null): string
    {
        if ($messages !== null) {
            $payload = $messages;
        } elseif (!empty($images)) {
            $content = [];
            foreach ($images as $image) {
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $image['media_type'],
                        'data' => $image['data'],
                    ],
                ];
            }
            $content[] = ['type' => 'text', 'text' => $prompt];
            $payload = [['role' => 'user', 'content' => $content]];
        } else {
            $payload = [['role' => 'user', 'content' => $prompt]];
        }

        $response = $this->http->post('/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'max_tokens' => 2048,
                'messages' => $payload,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['content'][0]['text'] ?? '';
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

AVAILABLE SONGS (format: ID | Title – Artist | Key | Genre | BPM | Lead singer | Transitions into):
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

        // Build multi-turn messages: system prompt as first user message, then history, then new message
        $messages = [
            ['role' => 'user', 'content' => $systemPrompt],
            ['role' => 'assistant', 'content' => 'Understood. I\'m ready to refine the setlist. What changes would you like?'],
        ];

        foreach ($chatHistory as $turn) {
            $messages[] = [
                'role'    => $turn['role'],
                'content' => $turn['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $newMessage];

        $responseText = $this->callClaude('', [], $messages);

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
