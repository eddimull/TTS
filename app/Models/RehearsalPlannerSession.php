<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RehearsalPlannerSession extends Model
{
    use HasFactory;

    protected $fillable = ['band_id', 'user_id', 'title'];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RehearsalPlannerMessage::class, 'session_id')->orderBy('id');
    }
}
