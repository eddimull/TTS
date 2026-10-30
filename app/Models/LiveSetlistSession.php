<?php

namespace App\Models;

use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Model;

class LiveSetlistSession extends Model
{
    use BroadcastsBandChanges;

    /**
     * The event page only shows whether a live session exists / is running
     * (live_session_id), so only lifecycle columns broadcast. Per-song
     * updates (current_position, after_break, rejected_song_ids, ...) have
     * their own live-session channel and must not spam the band channel.
     */
    protected function broadcastHasMeaningfulChanges(): bool
    {
        return $this->wasChanged(['status', 'started_at', 'ended_at']);
    }

    protected function broadcastBandId(): ?int
    {
        if (isset($this->band_id) && $this->band_id) {
            return (int) $this->band_id;
        }

        return $this->event?->resolveBandId();
    }

    protected function broadcastParent(): ?array
    {
        return $this->event_id
            ? ['model' => 'events', 'id' => (int) $this->event_id]
            : null;
    }
    protected $fillable = [
        'event_id',
        'band_id',
        'is_dynamic',
        'started_by',
        'status',
        'current_position',
        'started_at',
        'ended_at',
        'after_break',
        'break_started_at',
        'rejected_song_ids',
        'client_notes',
    ];

    protected $casts = [
        'is_dynamic' => 'boolean',
        'after_break' => 'boolean',
        'current_position' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'break_started_at' => 'datetime',
        'rejected_song_ids' => 'array',
        'client_notes' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function captains()
    {
        return $this->hasMany(LiveSetlistCaptain::class, 'session_id');
    }

    public function captainUsers()
    {
        return $this->belongsToMany(User::class, 'live_setlist_captains', 'session_id', 'user_id')
            ->withTimestamps();
    }

    public function queue()
    {
        return $this->hasMany(LiveSetlistQueue::class, 'session_id')->orderBy('position');
    }

    public function pendingQueue()
    {
        return $this->queue()->where('status', 'pending');
    }

    public function eventLog()
    {
        return $this->hasMany(LiveSetlistEvent::class, 'session_id')->orderBy('created_at');
    }

    public function isCaptain(User $user): bool
    {
        return $this->captains()->where('user_id', $user->id)->exists();
    }

    public function currentSong(): ?LiveSetlistQueue
    {
        return $this->queue()
            ->where('position', $this->current_position)
            ->where('type', 'song')
            ->first();
    }

    public function nextSong(): ?LiveSetlistQueue
    {
        return $this->pendingQueue()
            ->where('type', 'song')
            ->where('position', '>', $this->current_position)
            ->first();
    }
}
