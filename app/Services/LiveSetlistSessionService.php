<?php

namespace App\Services;

use App\Events\SetlistCaptainChanged;
use App\Events\SetlistQueueAdvanced;
use App\Events\SetlistQueueUpdated;
use App\Events\SetlistSessionStarted;
use App\Events\SetlistSessionStateChanged;
use App\Models\Bands;
use App\Models\EventSetlist;
use App\Models\LiveSetlistCaptain;
use App\Models\LiveSetlistEvent;
use App\Models\LiveSetlistQueue;
use App\Models\LiveSetlistSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LiveSetlistSessionService
{
    public function start(EventSetlist $setlist, User $user): LiveSetlistSession
    {
        return DB::transaction(function () use ($setlist, $user) {
            $session = LiveSetlistSession::create([
                'event_id' => $setlist->event_id,
                'band_id' => $setlist->band_id,
                'started_by' => $user->id,
                'status' => 'active',
                'current_position' => 1,
                'started_at' => now(),
            ]);

            // Seed the live queue from the static setlist
            $setlist->load('songs.song.leadSinger');
            foreach ($setlist->songs as $entry) {
                LiveSetlistQueue::create([
                    'session_id' => $session->id,
                    'song_id' => $entry->song_id,
                    'custom_title' => $entry->custom_title,
                    'custom_artist' => $entry->custom_artist,
                    'position' => $entry->position,
                    'status' => 'pending',
                ]);
            }

            // Make the starter a captain
            LiveSetlistCaptain::create([
                'session_id' => $session->id,
                'user_id' => $user->id,
                'promoted_by' => null,
            ]);

            $this->log($session, null, $user, 'session_start');

            broadcast(new SetlistSessionStateChanged($session));
            $this->notifyBandMembers($session);

            return $session->load(['queue.song.leadSinger', 'captains']);
        });
    }

    public function startEmpty(int $eventId, int $bandId, User $user): LiveSetlistSession
    {
        return DB::transaction(function () use ($eventId, $bandId, $user) {
            $session = LiveSetlistSession::create([
                'event_id' => $eventId,
                'band_id' => $bandId,
                'is_dynamic' => true,
                'started_by' => $user->id,
                'status' => 'active',
                'current_position' => 1,
                'started_at' => now(),
            ]);

            LiveSetlistCaptain::create([
                'session_id' => $session->id,
                'user_id' => $user->id,
                'promoted_by' => null,
            ]);

            $this->log($session, null, $user, 'session_start');

            broadcast(new SetlistSessionStateChanged($session));
            $this->notifyBandMembers($session);

            return $session->load(['queue.song.leadSinger', 'captains']);
        });
    }

    public function acceptSuggestion(LiveSetlistSession $session, int $songId, User $user): LiveSetlistQueue
    {
        return DB::transaction(function () use ($session, $songId, $user) {
            $isFirst = $session->queue()->doesntExist();
            $maxPosition = $session->queue()->max('position') ?? 0;
            $position = $maxPosition + 1;

            $entry = LiveSetlistQueue::create([
                'session_id' => $session->id,
                'song_id' => $songId,
                'position' => $position,
                'status' => 'pending',
            ]);

            // If this is the very first song, point current_position at it
            if ($isFirst) {
                $session->update(['current_position' => $position]);
            }

            $this->log($session, $entry, $user, 'accept_suggestion', ['song_id' => $songId]);

            $entry->load('song.leadSinger');

            broadcast(new SetlistQueueUpdated($session, $this->formatQueue($session)));

            return $entry;
        });
    }

    public function next(LiveSetlistSession $session, User $user): void
    {
        DB::transaction(function () use ($session, $user) {
            $current = $session->queue()
                ->where('position', $session->current_position)
                ->where('status', 'pending')
                ->first();

            if ($current) {
                $current->update(['status' => 'played', 'played_at' => now()]);
                $this->log($session, $current, $user, 'next');
            }

            // Find next pending song
            $next = $session->pendingQueue()
                ->where('position', '>', $session->current_position)
                ->first();

            if ($next) {
                $session->update(['current_position' => $next->position]);
            } elseif ($session->is_dynamic) {
                // Dynamic session — captain will pick/accept the next song via AI suggestion
                // Don't end the session; frontend will show the suggestion panel
                return;
            } else {
                // No more songs — end the session
                $this->end($session, $user);
                return;
            }

            $session->refresh();
            $newCurrent = $session->currentSong();
            $newNext = $session->nextSong();

            $newCurrent?->load('song.leadSinger');
            $newNext?->load('song.leadSinger');

            broadcast(new SetlistQueueAdvanced($session, $newCurrent, $newNext));
        });
    }

    public function react(LiveSetlistSession $session, LiveSetlistQueue $entry, string $reaction, User $user): void
    {
        $action = $reaction === 'positive' ? 'thumbs_up' : 'thumbs_down';
        $entry->update(['crowd_reaction' => $reaction]);
        $this->log($session, $entry, $user, $action);

        broadcast(new SetlistQueueUpdated($session, $this->formatQueue($session)));
    }

    public function skip(LiveSetlistSession $session, User $user): void
    {
        DB::transaction(function () use ($session, $user) {
            $current = $session->queue()
                ->where('position', $session->current_position)
                ->where('status', 'pending')
                ->first();

            if ($current) {
                $current->update(['status' => 'skipped']);
                $this->log($session, $current, $user, 'skip');
            }

            $next = $session->pendingQueue()
                ->where('position', '>', $session->current_position)
                ->first();

            if ($next) {
                $session->update(['current_position' => $next->position]);
            } elseif ($session->is_dynamic) {
                return;
            } else {
                $this->end($session, $user);
                return;
            }

            $session->refresh();
            $newCurrent = $session->currentSong();
            $newNext = $session->nextSong();

            $newCurrent?->load('song.leadSinger');
            $newNext?->load('song.leadSinger');

            broadcast(new SetlistQueueAdvanced($session, $newCurrent, $newNext));
        });
    }

    public function skipRemove(LiveSetlistSession $session, User $user): void
    {
        DB::transaction(function () use ($session, $user) {
            $current = $session->queue()
                ->where('position', $session->current_position)
                ->where('status', 'pending')
                ->first();

            if ($current) {
                $current->update(['status' => 'removed']);
                $this->log($session, $current, $user, 'skip_remove');
            }

            $next = $session->pendingQueue()
                ->where('position', '>', $session->current_position)
                ->first();

            if ($next) {
                $session->update(['current_position' => $next->position]);
            } elseif ($session->is_dynamic) {
                broadcast(new SetlistQueueUpdated($session, $this->formatQueue($session)));
                return;
            } else {
                $this->end($session, $user);
                return;
            }

            $session->refresh();
            $newCurrent = $session->currentSong();
            $newNext = $session->nextSong();

            $newCurrent?->load('song.leadSinger');
            $newNext?->load('song.leadSinger');

            broadcast(new SetlistQueueAdvanced($session, $newCurrent, $newNext));
            broadcast(new SetlistQueueUpdated($session, $this->formatQueue($session)));
        });
    }

    public function addOffSetlist(LiveSetlistSession $session, int $songId, User $user): LiveSetlistQueue
    {
        return DB::transaction(function () use ($session, $songId, $user) {
            // Insert after current position
            $insertAt = $session->current_position + 1;

            // Shift all pending songs after insert point
            $session->pendingQueue()
                ->where('position', '>=', $insertAt)
                ->increment('position');

            $entry = LiveSetlistQueue::create([
                'session_id' => $session->id,
                'song_id' => $songId,
                'position' => $insertAt,
                'status' => 'pending',
                'is_off_setlist' => true,
            ]);

            $this->log($session, $entry, $user, 'off_setlist');

            $entry->load('song.leadSinger');

            broadcast(new SetlistQueueUpdated($session, $this->formatQueue($session)));

            return $entry;
        });
    }

    public function promoteCaptain(LiveSetlistSession $session, User $target, User $promotedBy): void
    {
        LiveSetlistCaptain::firstOrCreate([
            'session_id' => $session->id,
            'user_id' => $target->id,
        ], ['promoted_by' => $promotedBy->id]);

        $this->log($session, null, $promotedBy, 'promote_captain', ['promoted_user_id' => $target->id]);

        broadcast(new SetlistCaptainChanged($session, $target->id, 'promoted'));
    }

    public function demoteCaptain(LiveSetlistSession $session, User $target, User $demotedBy): void
    {
        LiveSetlistCaptain::where('session_id', $session->id)
            ->where('user_id', $target->id)
            ->delete();

        $this->log($session, null, $demotedBy, 'demote_captain', ['demoted_user_id' => $target->id]);

        broadcast(new SetlistCaptainChanged($session, $target->id, 'demoted'));
    }

    public function end(LiveSetlistSession $session, User $user): void
    {
        $session->update(['status' => 'completed', 'ended_at' => now()]);
        $this->log($session, null, $user, 'session_end');
        broadcast(new SetlistSessionStateChanged($session));
    }

    public function formatQueue(LiveSetlistSession $session): array
    {
        return $session->queue()->with('song.leadSinger')->get()->map(fn($e) => [
            'id' => $e->id,
            'position' => $e->position,
            'status' => $e->status,
            'title' => $e->display_title,
            'artist' => $e->display_artist,
            'song_key' => $e->song?->song_key,
            'genre' => $e->song?->genre,
            'bpm' => $e->song?->bpm,
            'lead_singer' => $e->song?->leadSinger?->display_name,
            'crowd_reaction' => $e->crowd_reaction,
            'is_off_setlist' => $e->is_off_setlist,
            'played_at' => $e->played_at,
        ])->all();
    }

    private function notifyBandMembers(LiveSetlistSession $session): void
    {
        $session->load('event');
        $band = Bands::with(['owners', 'members'])->find($session->band_id);

        if (!$band) return;

        foreach ($band->everyone() as $member) {
            if ($member->user_id !== $session->started_by) {
                broadcast(new SetlistSessionStarted($session, $session->event, $member->user_id));
            }
        }
    }

    private function log(LiveSetlistSession $session, ?LiveSetlistQueue $entry, User $user, string $action, array $payload = []): void
    {
        LiveSetlistEvent::create([
            'session_id' => $session->id,
            'queue_entry_id' => $entry?->id,
            'user_id' => $user->id,
            'action' => $action,
            'payload' => empty($payload) ? null : $payload,
        ]);
    }
}
