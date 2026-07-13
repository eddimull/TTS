<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Song extends Model
{
    use \App\Models\Traits\BroadcastsBandChanges;

    use HasFactory, Searchable;

    public const GENRES = [
        'Blues', 'Country', 'Funk', 'Hip Hop', 'Jazz', 'Latin',
        'Pop', 'R&B', 'Rock', 'Soul',
    ];

    protected $fillable = [
        'band_id',
        'title',
        'artist',
        'song_key',
        'genre',
        'bpm',
        'notes',
        'lead_singer_id',
        'transition_song_id',
        'active',
        'rating',
        'energy',
    ];

    protected $casts = [
        'active' => 'boolean',
        'bpm' => 'integer',
        'rating' => 'integer',
        'energy' => 'integer',
    ];

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function leadSinger()
    {
        return $this->belongsTo(RosterMember::class, 'lead_singer_id');
    }

    public function transitionSong()
    {
        return $this->belongsTo(Song::class, 'transition_song_id');
    }

    public function setlistSongs()
    {
        return $this->hasMany(SetlistSong::class, 'song_id');
    }

    public function queueEntries()
    {
        return $this->hasMany(LiveSetlistQueue::class, 'song_id');
    }

    public function charts(): HasMany
    {
        return $this->hasMany(Charts::class, 'song_id');
    }
}
