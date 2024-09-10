<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Events extends Model
{
    use HasFactory;

    protected $fillable = [
        'eventable_id',
        'event_type_id',
        'eventable_type',
        'notes',
        'color',
        'key',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'object',
    ];

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }
}
