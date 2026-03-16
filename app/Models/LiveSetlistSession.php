<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSetlistSession extends Model
{
    protected $fillable = [
        'event_id',
        'band_id',
        'is_dynamic',
        'started_by',
        'status',
        'current_position',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'is_dynamic' => 'boolean',
        'current_position' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
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
            ->first();
    }

    public function nextSong(): ?LiveSetlistQueue
    {
        return $this->pendingQueue()
            ->where('position', '>', $this->current_position)
            ->first();
    }
}
