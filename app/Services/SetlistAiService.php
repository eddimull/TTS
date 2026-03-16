<?php

namespace App\Services;

use App\Models\Events;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

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
     * Returns an array of song IDs in recommended order.
     */
    public function generateSetlist(Events $event, array $songs, ?string $extraContext = null): array
    {
        $context = $this->buildEventContext($event);
        $songList = $this->buildSongList($songs);

        $extraSection = $extraContext
            ? "\nBAND LEADER INSTRUCTIONS:\n{$extraContext}\n"
            : '';

        $prompt = <<<PROMPT
You are a professional band manager building a setlist for a live performance.

EVENT DETAILS:
{$context}
{$extraSection}
AVAILABLE SONGS (format: ID | Title – Artist | Key | Genre | BPM | Lead singer | Transitions into):
{$songList}

RULES — follow every rule without exception:

1. EXCLUSIONS ARE ABSOLUTE: If the band leader's instructions mention excluding any artist, song, or genre — do NOT include any song by that artist or matching that description, no matter what. A shorter setlist is always preferable to violating an exclusion. Do not include excluded songs to "fill time" or "round out the set".
2. PLAY/DO-NOT-PLAY: Respect any play or do-not-play lists in the event notes with the same strictness.
3. TRANSITIONS: When a song has a "Transitions into" field, place that target song immediately after it in the setlist.
4. ENERGY FLOW: Vary genres and BPM to manage crowd energy. Do not cluster all slow songs together.
5. LEAD SINGERS: Vary lead singers throughout the set when possible.

Return ONLY a valid JSON array of song IDs in performance order. It is fine if the list is shorter than the full library.
Example: [42, 17, 8, 23, 5]

Do not include any explanation, commentary, or markdown — only the raw JSON array.
PROMPT;

        $responseText = $this->callClaude($prompt);

        return $this->parseSongIds($responseText);
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

Do not include any explanation or markdown — only the JSON array.
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

Do not include any explanation or markdown — only the JSON array.
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
        $lines[] = 'Time: ' . ($event->time ?? 'Unknown');

        if ($event->type) {
            $lines[] = 'Event Type: ' . $event->type->name;
        }

        if ($event->eventable) {
            $booking = $event->eventable;
            if (!empty($booking->venue_name)) {
                $lines[] = 'Venue: ' . $booking->venue_name;
            }
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

    private function callClaude(string $prompt): string
    {
        $response = $this->http->post('/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['content'][0]['text'] ?? '';
    }

    private function parseSongIds(string $text): array
    {
        // Strip any markdown code fences if Claude adds them
        $text = preg_replace('/```[a-z]*\n?/', '', $text);
        $text = trim($text);

        $ids = json_decode($text, true);

        if (!is_array($ids)) {
            Log::warning('SetlistAiService: could not parse song IDs from response', ['text' => $text]);
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }
}
