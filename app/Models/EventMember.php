<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'band_id',
        'user_id',
        'roster_member_id',
        'name',
        'email',
        'phone',
        'role',
        'band_role_id',
        'attendance_status',
        'payout_amount',
        'notes',
    ];

    protected $casts = [
        'payout_amount' => 'integer',
    ];

    /**
     * Get the event this attendance record is for.
     */
    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    /**
     * Get the band this attendance is for.
     */
    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Get the roster member this attendance record is for.
     */
    public function rosterMember()
    {
        return $this->belongsTo(RosterMember::class);
    }

    /**
     * Get the user (if this is a registered user).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the band role for this event member.
     */
    public function bandRole()
    {
        return $this->belongsTo(BandRole::class, 'band_role_id');
    }

    /**
     * Get the display name for this member.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->rosterMember) {
            return $this->rosterMember->display_name;
        }

        if ($this->user_id) {
            return $this->user->name;
        }

        return $this->name ?? 'Unknown';
    }

    /**
     * Get the display email for this member.
     */
    public function getDisplayEmailAttribute(): ?string
    {
        if ($this->rosterMember) {
            return $this->rosterMember->display_email;
        }

        if ($this->user_id) {
            return $this->user->email;
        }

        return $this->email;
    }

    /**
     * Get the role name from BandRole relationship.
     */
    public function getRoleNameAttribute(): ?string
    {
        // First check if this event member has their own band_role
        if ($this->bandRole) {
            return $this->bandRole->name;
        }

        // If linked to roster member, use their band role
        if ($this->rosterMember?->bandRole) {
            return $this->rosterMember->bandRole->name;
        }

        return null;
    }

    /**
     * Check if this member attended the event.
     */
    public function attended(): bool
    {
        return $this->attendance_status === 'attended';
    }

    /**
     * Check if this member was absent.
     */
    public function isAbsent(): bool
    {
        return in_array($this->attendance_status, ['absent', 'excused']);
    }

    /**
     * Get the payout amount for this member at this event.
     * Returns custom amount if set, otherwise null (will use calculated split).
     */
    public function getPayoutAmountInDollars(): ?float
    {
        return $this->payout_amount ? $this->payout_amount / 100 : null;
    }

    /**
     * Scope to only attended members.
     */
    public function scopeAttended($query)
    {
        return $query->where('attendance_status', 'attended');
    }

    /**
     * Scope to only absent members.
     */
    public function scopeAbsent($query)
    {
        return $query->whereIn('attendance_status', ['absent', 'excused']);
    }
}
