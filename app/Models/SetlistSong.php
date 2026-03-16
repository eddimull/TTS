<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetlistSong extends Model
{
    protected $fillable = [
        'setlist_id',
        'song_id',
        'custom_title',
        'custom_artist',
        'position',
        'notes',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function setlist()
    {
        return $this->belongsTo(EventSetlist::class, 'setlist_id');
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
