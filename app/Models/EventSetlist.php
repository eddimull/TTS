<?php

namespace App\Models;

use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Model;

class EventSetlist extends Model
{
    /*
     * Realtime: `event_setlist` signals. SetlistSong rows deliberately do NOT
     * broadcast themselves — setlist writes delete and recreate every row, so
     * writers wrap the rewrite in withoutBandBroadcasts() and call
     * broadcastRefresh() once afterwards (one signal per request).
     */
    use BroadcastsBandChanges;

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
