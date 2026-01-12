<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Roster extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'band_id',
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the band this roster belongs to.
     */
    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Get all members in this roster.
     */
    public function members()
    {
        return $this->hasMany(RosterMember::class);
    }

    /**
     * Get only active members.
     */
    public function activeMembers()
    {
        return $this->members()->where('is_active', true);
    }

    /**
     * Get events using this roster.
     */
    public function events()
    {
        return $this->hasMany(Events::class);
    }

    /**
     * Create a default roster from band members.
     */
    public static function createDefaultForBand(Bands $band): self
    {
        $roster = self::create([
            'band_id' => $band->id,
            'name' => 'Default Roster',
            'description' => 'Default band roster including all owners and members',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Add all band owners and members to roster
        $bandMembers = $band->everyone();

        foreach ($bandMembers as $member) {
            RosterMember::create([
                'roster_id' => $roster->id,
                'user_id' => $member->user_id,
                'default_payout_type' => 'equal_split',
                'is_active' => true,
            ]);
        }

        return $roster;
    }

    /**
     * Ensure only one default roster per band.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($roster) {
            if ($roster->is_default) {
                // Unset any other default rosters for this band
                static::where('band_id', $roster->band_id)
                    ->where('id', '!=', $roster->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
