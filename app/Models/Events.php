<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Events extends Model
{
    use HasFactory;

    protected $fillable = [
        'additional_data',
        'date',
        'event_type_id',
        'band_id',
        'eventable_id',
        'eventable_type',
        'key',
        'title',
        'notes',
        'time',
    ];

    protected $casts = [
        'additional_data' => 'object',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventTypes::class);
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo()->where('band_id', $this->band_id);
    }
}
