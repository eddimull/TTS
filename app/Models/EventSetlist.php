<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSetlist extends Model
{
    protected $fillable = [
        'event_id',
        'band_id',
        'generated_at',
        'ai_context',
        'status',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'ai_context' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function songs()
    {
        return $this->hasMany(SetlistSong::class, 'setlist_id')->orderBy('position');
    }

    public function liveSession()
    {
        return $this->hasOne(LiveSetlistSession::class, 'event_id', 'event_id');
    }
}
