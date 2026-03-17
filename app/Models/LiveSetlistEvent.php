<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSetlistEvent extends Model
{
    public const UPDATED_AT = null; // append-only, no updated_at

    protected $fillable = [
        'session_id',
        'queue_entry_id',
        'user_id',
        'action',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(LiveSetlistSession::class, 'session_id');
    }

    public function queueEntry()
    {
        return $this->belongsTo(LiveSetlistQueue::class, 'queue_entry_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
