<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Events extends Model
{
    use HasFactory;

    protected $fillable = [
        'additional_data',
        'date',
        'event_type_id',
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

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }
}
