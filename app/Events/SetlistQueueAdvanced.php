<?php

namespace App\Events;

use App\Models\LiveSetlistSession;
use App\Models\LiveSetlistQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SetlistQueueAdvanced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveSetlistSession $session,
        public ?LiveSetlistQueue $currentSong,
        public ?LiveSetlistQueue $nextSong,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('setlist.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'current_position' => $this->session->current_position,
            'current_song' => $this->formatSong($this->currentSong),
            'next_song' => $this->formatSong($this->nextSong),
        ];
    }

    private function formatSong(?LiveSetlistQueue $entry): ?array
    {
        if (!$entry) return null;
        return [
            'id' => $entry->id,
            'title' => $entry->display_title,
            'artist' => $entry->display_artist,
            'song_key' => $entry->song?->song_key,
            'genre' => $entry->song?->genre,
            'bpm' => $entry->song?->bpm,
            'lead_singer' => $entry->song?->leadSinger?->display_name,
            'crowd_reaction' => $entry->crowd_reaction,
            'is_off_setlist' => $entry->is_off_setlist,
        ];
    }
}
