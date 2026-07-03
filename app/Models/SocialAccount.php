<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_id', 'avatar_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
