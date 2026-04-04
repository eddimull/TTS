<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Song extends Model
{
    use HasFactory, Searchable;

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
    ];

    protected $casts = [
        'active' => 'boolean',
        'bpm' => 'integer',
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
}
