<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubstituteCallList extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'instrument',
        'roster_member_id',
        'custom_name',
        'custom_email',
        'custom_phone',
        'priority',
        'notes',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    /**
     * Get the band this call list entry belongs to.
     */
    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Get the roster member (substitute) for this entry.
     */
    public function rosterMember()
    {
        return $this->belongsTo(RosterMember::class);
    }

    /**
     * Scope to get call list for a specific instrument/role.
     */
    public function scopeForInstrument($query, string $instrument)
    {
        return $query->where('instrument', $instrument)->orderBy('priority');
    }

    /**
     * Scope to get call list for a band.
     */
    public function scopeForBand($query, int $bandId)
    {
        return $query->where('band_id', $bandId);
    }

    /**
     * Check if this is a custom player (not from roster).
     */
    public function isCustomPlayer(): bool
    {
        return !$this->roster_member_id;
    }

    /**
     * Get display name (roster member or custom name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->roster_member_id
            ? $this->rosterMember->display_name
            : $this->custom_name;
    }

    /**
     * Get display email (roster member or custom email).
     */
    public function getDisplayEmailAttribute(): ?string
    {
        return $this->roster_member_id
            ? $this->rosterMember->display_email
            : $this->custom_email;
    }

    /**
     * Get display phone (roster member or custom phone).
     */
    public function getDisplayPhoneAttribute(): ?string
    {
        return $this->roster_member_id
            ? $this->rosterMember->phone
            : $this->custom_phone;
    }
}
