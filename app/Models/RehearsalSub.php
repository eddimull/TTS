<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RehearsalSub extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rehearsal_id',
        'band_id',
        'band_role_id',
        'user_id',
        'name',
        'email',
        'phone',
        'notes',
        'invited_by',
    ];

    public function rehearsal(): BelongsTo
    {
        return $this->belongsTo(Rehearsal::class);
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function bandRole(): BelongsTo
    {
        return $this->belongsTo(BandRole::class, 'band_role_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRegisteredUser(): bool
    {
        return $this->user_id !== null;
    }
}
