<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class BandApiToken extends Model
{
    use HasFactory, HasRoles;

    /**
     * The guard name for permissions
     */
    protected $guard_name = 'api_token';

    protected $fillable = [
        'band_id',
        'token',
        'name',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'token',
    ];

    /**
     * Relationship to the Band
     */
    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Generate a new API token
     */
    public static function generateToken(): string
    {
        return hash('sha256', Str::random(60));
    }

    /**
     * Mark this token as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Scope to only active tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the plain text token (only available when creating)
     */
    public function getPlainTextAttribute(): ?string
    {
        return $this->attributes['plain_text'] ?? null;
    }
}
