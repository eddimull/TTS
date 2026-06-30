<?php

namespace App\Services;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Rehearsal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RehearsalPlannerContextBuilder
{
    private const UPCOMING_LIMIT = 5;
    private const PAST_REHEARSAL_LIMIT = 5;

    /** @return array{text: string, has_upcoming_requests: bool, song_count: int} */
    public function build(Bands $band): array
    {
        [$upcomingText, $hasRequests] = $this->upcomingEvents($band);
        $rehearsedText = $this->recentlyRehearsed($band);
        $personnelText = $this->personnel($band);
        [$libraryText, $songCount] = $this->songLibrary($band);

        $text = implode("\n\n", [
            "UPCOMING EVENTS (next " . self::UPCOMING_LIMIT . "):\n" . $upcomingText,
            "RECENTLY REHEARSED (from last " . self::PAST_REHEARSAL_LIMIT . " rehearsals' bookings):\n" . $rehearsedText,
            "PERSONNEL & INSTRUMENTS:\n" . $personnelText,
            "SONG LIBRARY (active):\n" . $libraryText,
        ]);

        return [
            'text' => $text,
            'has_upcoming_requests' => $hasRequests,
            'song_count' => $songCount,
        ];
    }

    /** @return array{0: string, 1: bool} */
    private function upcomingEvents(Bands $band): array
    {
        $today = Carbon::today()->toDateString();

        $events = Events::query()
            ->where('eventable_type', Bookings::class)
            ->whereHasMorph('eventable', [Bookings::class], fn ($q) => $q->where('band_id', $band->id))
            ->whereDate('date', '>=', $today)
            ->with(['setlist.songs.song'])
            ->orderBy('date')
            ->limit(self::UPCOMING_LIMIT)
            ->get();

        if ($events->isEmpty()) {
            return ['(none scheduled)', false];
        }

        $hasRequests = false;
        $lines = $events->map(function (Events $e) use (&$hasRequests) {
            $songs = $e->setlist?->songs
                ?->map(fn ($s) => $s->song?->title)
                ->filter()
                ->values() ?? collect();
            if ($songs->isNotEmpty()) {
                $hasRequests = true;
            }
            $songList = $songs->isEmpty() ? 'no setlist yet' : $songs->implode(', ');
            $date = is_string($e->date) ? $e->date : optional($e->date)->format('Y-m-d');
            return "- {$e->title} ({$date}) — {$songList}";
        })->implode("\n");

        return [$lines, $hasRequests];
    }

    private function recentlyRehearsed(Bands $band): string
    {
        // Rehearsals have no `date` column; the date lives on the rehearsal's
        // polymorphic events (Rehearsal morphMany Events as `eventable`).
        // Pick rehearsals that have at least one PAST event, newest-past first.
        //
        // Ordering subtlety: a rehearsal may have both past AND future events.
        // Ordering by its overall most-recent event would let a future event
        // rank a rehearsal — so "last 5 past rehearsals" could be mis-ranked.
        // We therefore order by each rehearsal's most-recent PAST event date,
        // ignoring any future events for ranking purposes.
        $today = Carbon::today()->toDateString();

        // We reach associated bookings through `associations()` (hasMany
        // RehearsalAssociation, morphTo `associable`) rather than the
        // `Rehearsal::bookings()` morphToMany. The latter injects a default
        // `associable_type = Rehearsal` pivot constraint AND a manual
        // `wherePivot('associable_type', Bookings)` constraint, so the two
        // contradict and the relation always returns zero rows. The
        // associations path resolves Booking associables correctly.
        $rehearsals = Rehearsal::query()
            ->where('band_id', $band->id)
            ->whereHas('events', fn ($q) => $q->whereDate('date', '<', $today))
            ->withMax(['events as events_max_past_date' => fn ($q) => $q->whereDate('date', '<', $today)], 'date')
            ->with(['associations' => fn ($q) => $q->where('associable_type', Bookings::class),
                    'associations.associable.events.setlist.songs.song'])
            ->orderByDesc('events_max_past_date')
            ->limit(self::PAST_REHEARSAL_LIMIT)
            ->get();

        $titles = collect();
        foreach ($rehearsals as $rehearsal) {
            foreach ($rehearsal->associations as $association) {
                $booking = $association->associable;
                if (! $booking instanceof Bookings) {
                    continue;
                }
                foreach ($booking->events as $event) {
                    $songs = $event->setlist?->songs ?? collect();
                    foreach ($songs as $setlistSong) {
                        if ($setlistSong->song?->title) {
                            $titles->push($setlistSong->song->title);
                        }
                    }
                }
            }
        }

        $unique = $titles->unique()->values();
        return $unique->isEmpty() ? '(no song data from recent rehearsals)' : $unique->implode(', ');
    }

    private function personnel(Bands $band): string
    {
        $members = $this->rosterMembers($band);
        if ($members->isEmpty()) {
            return '(no roster members)';
        }
        return $members->map(function ($m) {
            $role = $m->bandRole?->name ?? 'Unassigned';
            $name = $m->display_name ?? ($m->name ?? 'Unknown');
            return "- {$name}: {$role}";
        })->implode("\n");
    }

    /** @return Collection */
    private function rosterMembers(Bands $band): Collection
    {
        // Bands -> rosters -> members (with bandRole). Flatten + de-dupe by id.
        return $band->rosters()
            ->with('members.bandRole')
            ->get()
            ->flatMap(fn ($roster) => $roster->members)
            ->unique('id')
            ->values();
    }

    /** @return array{0: string, 1: int} */
    private function songLibrary(Bands $band): array
    {
        $songs = $band->songs()->where('active', true)->with('leadSinger')->get();
        if ($songs->isEmpty()) {
            return ['(empty library)', 0];
        }
        $lines = $songs->map(function ($s) {
            $parts = array_filter([
                $s->title,
                $s->artist ? "by {$s->artist}" : null,
                $s->genre,
                $s->song_key ? "key {$s->song_key}" : null,
                $s->bpm ? "{$s->bpm}bpm" : null,
                $s->leadSinger?->display_name ? "lead {$s->leadSinger->display_name}" : null,
            ]);
            return "- [{$s->id}] " . implode(' · ', $parts);
        })->implode("\n");
        return [$lines, $songs->count()];
    }
}
