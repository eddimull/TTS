<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleDriveConnection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'band_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'google_account_email',
        'drive_id',
        'is_active',
        'last_synced_at',
        'sync_status',
        'last_sync_error',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Automatically encrypt access token when setting
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = encrypt($value);
    }

    /**
     * Automatically decrypt access token when getting
     */
    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Automatically encrypt refresh token when setting
     */
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? encrypt($value) : null;
    }

    /**
     * Automatically decrypt refresh token when getting
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Get the user that owns this connection
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the band that owns this connection
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Get the folders synced through this connection
     */
    public function folders(): HasMany
    {
        return $this->hasMany(GoogleDriveFolder::class, 'connection_id');
    }

    /**
     * Get the media files synced through this connection
     */
    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'drive_connection_id');
    }

    /**
     * Get the sync logs for this connection
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(GoogleDriveSyncLog::class, 'connection_id');
    }

    /**
     * Check if the access token is expired
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    /**
     * Check if this connection needs syncing
     */
    public function needsSync(): bool
    {
        return $this->is_active &&
               (!$this->last_synced_at || $this->last_synced_at->lt(now()->subHours(6)));
    }

    /**
     * Scope to get only active connections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get connections that need syncing
     */
    public function scopeNeedsSync($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                  ->orWhere('last_synced_at', '<', now()->subHours(6));
            });
    }
}
