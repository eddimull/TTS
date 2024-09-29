<?php

namespace App\Models;

use App\Models\Traits\Signable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contracts extends Model
{
    use HasFactory;
    use Signable;

    protected $fillable = [
        'envelope_id',
        'author_id',
        'status',
        'asset_url',
        'custom_terms',
    ];

    protected $casts = [
        'custom_terms' => 'array',
        'updated_at' => 'date:Y-m-d h:i a',
    ];

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getPdfUrl(): string
    {
        return $this->asset_url;
    }

    public function getSignatureFields(): array
    {
        return $this->custom_terms;
    }
}
