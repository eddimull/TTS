<?php

namespace App\Models;

use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RosterSlot extends Model
{
    use HasFactory, BroadcastsBandChanges;

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
        'band_role_id',
        'name',
        'is_required',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'quantity' => 'integer',
    ];

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    public function bandRole(): BelongsTo
    {
        return $this->belongsTo(BandRole::class, 'band_role_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(RosterMember::class, 'slot_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true)->whereNull('deleted_at');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}
