<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class EventSubs extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'band_id',
        'band_role_id',
        'user_id',
        'email',
        'name',
        'phone',
        'invitation_key',
        'pending',
        'accepted_at',
        'payout_amount',
        'notes',
    ];

    protected $casts = [
        'pending' => 'boolean',
        'accepted_at' => 'datetime',
        'payout_amount' => 'integer',
    ];

    /**
     * Auto-generate invitation key on creation
     */
    protected static function booted()
    {
        static::creating(function ($eventSub) {
            if (empty($eventSub->invitation_key)) {
                $eventSub->invitation_key = Str::random(36);
            }
        });
    }

    /**
     * Relationship to the event
     */
    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    /**
     * Relationship to the band
     */
    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Relationship to the user (if registered)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship to the band role (instrument)
     */
    public function bandRole()
    {
        return $this->belongsTo(BandRole::class, 'band_role_id');
    }

    /**
     * Get display name - prioritizes user's name if registered
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user_id && $this->user) {
            return $this->user->name;
        }
        return $this->name ?? $this->email ?? 'Unknown';
    }

    /**
     * Get display email
     */
    public function getDisplayEmailAttribute(): string
    {
        if ($this->user_id && $this->user) {
            return $this->user->email;
        }
        return $this->email ?? '';
    }

    /**
     * Get role/instrument name
     */
    public function getRoleNameAttribute(): ?string
    {
        if ($this->band_role_id && $this->bandRole) {
            return $this->bandRole->name;
        }
        return null;
    }

    /**
     * Check if this is a registered user
     */
    public function isRegisteredUser(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Check if invitation is still pending
     */
    public function isPending(): bool
    {
        return $this->pending;
    }

    /**
     * Mark invitation as accepted
     */
    public function markAsAccepted(): void
    {
        $this->update([
            'pending' => false,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Scope to only pending invitations
     */
    public function scopePending($query)
    {
        return $query->where('pending', true);
    }

    /**
     * Scope to only accepted invitations
     */
    public function scopeAccepted($query)
    {
        return $query->where('pending', false);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
