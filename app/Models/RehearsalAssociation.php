<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RehearsalAssociation extends Model
{
    use HasFactory;

    protected $fillable = [
        'rehearsal_id',
        'associable_type',
        'associable_id',
        'notes',
    ];

    /**
     * Get the rehearsal
     */
    public function rehearsal(): BelongsTo
    {
        return $this->belongsTo(Rehearsal::class);
    }

    /**
     * Get the associated model (Booking or Event)
     */
    public function associable(): MorphTo
    {
        return $this->morphTo();
    }
}
