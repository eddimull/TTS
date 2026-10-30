<?php

namespace App\Models;

use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RosterMember extends Model
{
    use HasFactory, SoftDeletes, BroadcastsBandChanges;

    protected function broadcastBandId(): ?int
    {
        if (! $this->roster_id) {
            return null;
        }

        $bandId = Roster::withTrashed()->whereKey($this->roster_id)->value('band_id');

        return $bandId ? (int) $bandId : null;
    }

    protected function broadcastParent(): ?array
    {
        return $this->roster_id
            ? ['model' => 'roster', 'id' => (int) $this->roster_id]
            : null;
    }

    protected $fillable = [
        'roster_id',
        'slot_id',
        'user_id',
        'name',
        'email',
        'phone',
        'role',
        'band_role_id',
        'notes',
        'is_active',
        'deleted_at',
    ];

    protected $appends = ['display_name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the slot this member is assigned to.
     */
    public function slot()
    {
        return $this->belongsTo(RosterSlot::class, 'slot_id');
    }

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
     * Get the band role for this roster member.
     */
    public function bandRole()
    {
        return $this->belongsTo(BandRole::class, 'band_role_id');
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
     * Get the role name from BandRole relationship.
     */
    public function getRoleNameAttribute(): ?string
    {
        return $this->bandRole?->name;
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
