<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RosterMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'roster_id',
        'user_id',
        'name',
        'email',
        'phone',
        'role',
        'default_payout_type',
        'default_payout_amount',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_payout_amount' => 'integer',
    ];

    /**
     * Get the roster this member belongs to.
     */
    public function roster()
    {
        return $this->belongsTo(Roster::class);
    }

    /**
     * Get the user if this is a registered user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get event attendance records for this roster member.
     */
    public function eventAttendance()
    {
        return $this->hasMany(EventMember::class);
    }

    /**
     * Get the display name for this roster member.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user_id) {
            return $this->user->name;
        }

        return $this->name ?? 'Unknown';
    }

    /**
     * Get the display email for this roster member.
     */
    public function getDisplayEmailAttribute(): ?string
    {
        if ($this->user_id) {
            return $this->user->email;
        }

        return $this->email;
    }

    /**
     * Check if this member is a registered user.
     */
    public function isUser(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Check if this member is a non-user (sub, guest, etc.).
     */
    public function isNonUser(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Scope to only user-based members.
     */
    public function scopeUsers($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope to only non-user members.
     */
    public function scopeNonUsers($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope to only active members.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
