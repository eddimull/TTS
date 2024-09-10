<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contracts extends Model
{
    use HasFactory;
    protected $fillable = [
        'envelope_id',
        'author_id',
        'envelope_id',
        'status',
        'asset_url'
    ];

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }
}
