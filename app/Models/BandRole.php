<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BandRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'name',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the band that owns the role.
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Get the roster members with this role.
     */
    public function rosterMembers(): HasMany
    {
        return $this->hasMany(RosterMember::class, 'band_role_id');
    }

    /**
     * Get the event members with this role.
     */
    public function eventMembers(): HasMany
    {
        return $this->hasMany(EventMember::class, 'band_role_id');
    }

    /**
     * Get the substitute call list entries with this role.
     */
    public function substituteCallLists(): HasMany
    {
        return $this->hasMany(SubstituteCallList::class, 'band_role_id');
    }

    /**
     * Scope to only include active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
