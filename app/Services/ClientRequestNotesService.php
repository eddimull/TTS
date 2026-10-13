<?php

namespace App\Services;

use App\Ai\Agents\SetlistAgent;
use App\Models\Bookings;
use App\Models\LiveSetlistSession;
use App\Models\QuestionnaireInstances;
use App\Models\Song;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Turns a booking's questionnaire free text ("Other must-play songs…",
 * "Anything else we should know?", first-dance fields…) into structured
 * song requests for the live session, matched against the band's catalog.
 *
 * Shape stored on the session's `client_notes`:
 *   requests:   [{title, artist, song_id|null, kind, note|null}]
 *               kind: must_play | if_time | do_not_play | intermission | special_moment
 *   song_notes: [{title, song_id|null, note}]   performance notes (lyric changes…)
 *   rules:      [string]                         blanket constraints ("No slow songs")
 *   crowd:      string|null                      who's in the room
 */
class ClientRequestNotesService
{
    public const KINDS = ['must_play', 'if_time', 'do_not_play', 'intermission', 'special_moment'];

    /** Free-text field types worth reading; song pickers are handled by ClientSongRequestsService. */
    private const TEXT_TYPES = ['short_text', 'long_text'];

    /**
     * Parse once per session. Failures back off for a few minutes rather
     * than calling the AI on every session poll.
     */
    public function ensureFor(LiveSetlistSession $session): void
    {
        if ($session->client_notes !== null) {
            return;
        }
        $backoffKey = "live-session:{$session->id}:client-notes-failed";
        if (Cache::has($backoffKey)) {
            return;
        }

        $session->loadMissing('event.eventable');
        $booking = $session->event?->eventable;

        try {
            $notes = $booking instanceof Bookings
                ? $this->extract($booking)
                : $this->empty();
            $session->update(['client_notes' => $notes]);
        } catch (\Throwable $e) {
            Log::warning('ClientRequestNotesService: extraction failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            Cache::put($backoffKey, true, now()->addMinutes(5));
        }
    }

    public function extract(Bookings $booking): array
    {
        $text = $this->questionnaireText($booking);
        if ($text === '') {
            return $this->empty();
        }

        $songs = Song::query()
            ->where('band_id', $booking->band_id)
            ->where('active', true)
            ->get(['id', 'title', 'artist']);

        $prompt = $this->prompt($text, $songs);
        $raw = (string) (new SetlistAgent())->prompt($prompt, timeout: 120);

        return $this->normalize($this->decode($raw), $songs);
    }

    public function empty(): array
    {
        return ['requests' => [], 'song_notes' => [], 'rules' => [], 'crowd' => null];
    }

    /** Submitted free-text answers as "== label ==\nvalue" blocks. */
    private function questionnaireText(Bookings $booking): string
    {
        $instances = QuestionnaireInstances::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', [
                QuestionnaireInstances::STATUS_SUBMITTED,
                QuestionnaireInstances::STATUS_LOCKED,
            ])
            ->with(['fields' => fn ($q) => $q->whereIn('type', self::TEXT_TYPES), 'responses'])
            ->orderByDesc('submitted_at')
            ->get();

        $blocks = [];
        foreach ($instances as $instance) {
            $responses = $instance->responses->keyBy('instance_field_id');
            foreach ($instance->fields as $field) {
                $value = trim((string) ($responses->get($field->id)?->value ?? ''));
                if ($value !== '') {
                    $blocks[] = "== {$field->label} ==\n{$value}";
                }
            }
        }

        return implode("\n\n", $blocks);
    }

    private function prompt(string $text, Collection $songs): string
    {
        $catalog = $songs
            ->map(fn ($s) => "ID:{$s->id} | {$s->title}" . ($s->artist ? " – {$s->artist}" : ''))
            ->implode("\n");

        return <<<PROMPT
A client filled out a questionnaire for their event. Extract every music request and performance note so the band can use them live.

QUESTIONNAIRE ANSWERS:
{$text}

THE BAND'S SONG CATALOG (format: ID | Title – Artist):
{$catalog}

Return ONLY a JSON object with these keys:
- "requests": array of {"title", "artist", "song_id", "kind", "note"}
    - kind is one of:
        "must_play"      — songs the client insists on
        "if_time"        — nice-to-haves ("if time permits")
        "do_not_play"    — songs to avoid
        "intermission"   — songs for the band's break / DJ, not performed by the band
        "special_moment" — songs tied to a moment (a dance, an entrance, last dance)
    - song_id: the catalog ID if the song is in the catalog (match loosely on title), else null
    - artist: the artist if stated or well known, else null
    - note: short context for the band (who asked, which moment, timing), else null
    - Include first/father-daughter/mother-son/money/bouquet dances as special_moment.
- "song_notes": array of {"title", "song_id", "note"} for performance instructions about a specific song (e.g. a lyric change), even if the song is not requested elsewhere.
- "rules": array of short blanket constraints for song choice (e.g. "No slow songs", "No line dances except at intermission").
- "crowd": one short sentence describing the crowd if the answers say anything about it, else null.

Only include songs or rules the client actually mentioned — never invent placeholder songs. "Song by Artist A and Artist B" is one song (a duet or collaboration): list it ONCE with both artists — e.g. "Islands in the Stream by Kenny Rogers and Dolly Parton" → one entry, artist "Kenny Rogers & Dolly Parton". A request's title must be an actual song title, never an artist name; artist-level wishes ("more Michael Jackson") belong in "rules". When something is ambiguous, keep one entry and explain the ambiguity in its note. Skip non-music details (names, addresses, colors, attire).
Output raw JSON only — no markdown, no explanation.
PROMPT;
    }

    private function decode(string $raw): array
    {
        $raw = preg_replace('/```[a-z]*\n?/i', '', $raw);
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false) {
            throw new \RuntimeException('No JSON object in AI response.');
        }
        $data = json_decode(substr($raw, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            throw new \RuntimeException('Unparseable AI response.');
        }

        return $data;
    }

    /** Keep only well-formed entries and catalog IDs that really exist. */
    private function normalize(array $data, Collection $songs): array
    {
        $validIds = $songs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $songId = fn ($v) => is_numeric($v) && in_array((int) $v, $validIds, true) ? (int) $v : null;
        $str = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        $requests = collect($data['requests'] ?? [])
            ->filter(fn ($r) => is_array($r) && $str($r['title'] ?? null) && in_array($r['kind'] ?? null, self::KINDS, true))
            ->map(fn ($r) => [
                'title'   => $str($r['title']),
                'artist'  => $str($r['artist'] ?? null),
                'song_id' => $songId($r['song_id'] ?? null),
                'kind'    => $r['kind'],
                'note'    => $str($r['note'] ?? null),
            ])->values()->all();

        $songNotes = collect($data['song_notes'] ?? [])
            ->filter(fn ($n) => is_array($n) && $str($n['title'] ?? null) && $str($n['note'] ?? null))
            ->map(fn ($n) => [
                'title'   => $str($n['title']),
                'song_id' => $songId($n['song_id'] ?? null),
                'note'    => $str($n['note']),
            ])->values()->all();

        return [
            'requests'   => $requests,
            'song_notes' => $songNotes,
            'rules'      => collect($data['rules'] ?? [])->map($str)->filter()->values()->all(),
            'crowd'      => $str($data['crowd'] ?? null),
        ];
    }
}
