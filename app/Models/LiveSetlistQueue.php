<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSetlistQueue extends Model
{
    protected $table = 'live_setlist_queue';

    protected $fillable = [
        'session_id',
        'type',
        'song_id',
        'custom_title',
        'custom_artist',
        'position',
        'status',
        'played_at',
        'is_off_setlist',
        'crowd_reaction',
        'ai_weight',
        'notes',
    ];

    protected $casts = [
        'position' => 'integer',
        'played_at' => 'datetime',
        'is_off_setlist' => 'boolean',
        'ai_weight' => 'float',
    ];

    public function session()
    {
        return $this->belongsTo(LiveSetlistSession::class, 'session_id');
    }

    public function song()
    {
        return $this->belongsTo(Song::class, 'song_id');
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->song?->title ?? $this->custom_title ?? '';
    }

    public function getDisplayArtistAttribute(): ?string
    {
        return $this->song?->artist ?? $this->custom_artist;
    }
}
