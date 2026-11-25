<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booking's questionnaire free text as parsed by ClientRequestNotesService.
 */
class BookingClientNotes extends Model
{
    protected $table = 'booking_client_notes';

    protected $fillable = ['booking_id', 'notes', 'source_hash', 'parsed_at'];

    protected $casts = [
        'notes' => 'array',
        'parsed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Bookings::class, 'booking_id');
    }
}
