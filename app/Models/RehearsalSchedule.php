<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RehearsalSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'band_id',
        'name',
        'description',
        'frequency',
        'day_of_week',
        'selected_days',
        'day_of_month',
        'monthly_pattern',
        'monthly_weekday',
        'default_time',
        'location_name',
        'location_address',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'selected_days' => 'array',
    ];

    /**
     * Get the band that owns this rehearsal schedule
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class);
    }

    /**
     * Get all rehearsals for this schedule
     */
    public function rehearsals(): HasMany
    {
        return $this->hasMany(Rehearsal::class);
    }

    /**
     * Get upcoming rehearsals
     */
    public function upcomingRehearsals()
    {
        return $this->rehearsals()
            ->whereHas('events', function ($query) {
                $query->where('date', '>=', now()->toDateString());
            })
            ->with('events');
    }

    /**
     * Get past rehearsals
     */
    public function pastRehearsals()
    {
        return $this->rehearsals()
            ->whereHas('events', function ($query) {
                $query->where('date', '<', now()->toDateString());
            })
            ->with('events');
    }
}
