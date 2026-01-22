<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BandSubs extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'band_id'];

    /**
     * Relationship to the user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship to the band
     */
    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Get all events this sub is invited to
     */
    public function eventSubs()
    {
        return $this->hasMany(EventSubs::class, 'user_id', 'user_id')
            ->where('band_id', $this->band_id);
    }

    /**
     * Get events through the event_subs pivot
     */
    public function events()
    {
        return $this->hasManyThrough(
            Events::class,
            EventSubs::class,
            'user_id',     // Foreign key on event_subs table
            'id',          // Foreign key on events table
            'user_id',     // Local key on band_subs table
            'event_id'     // Local key on event_subs table
        )->where('event_subs.band_id', $this->band_id);
    }
}
