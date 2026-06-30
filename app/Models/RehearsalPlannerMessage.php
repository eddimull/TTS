<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RehearsalPlannerMessage extends Model
{
    use HasFactory;

    protected $fillable = ['session_id', 'role', 'content', 'payload', 'status'];

    protected $casts = ['payload' => 'array'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RehearsalPlannerSession::class, 'session_id');
    }
}
