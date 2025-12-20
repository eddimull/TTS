<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'media_file_id',
        'token',
        'created_by',
        'expires_at',
        'download_limit',
        'download_count',
        'password_hash',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the media file that owns the share
     */
    public function mediaFile()
    {
        return $this->belongsTo(MediaFile::class);
    }

    /**
     * Get the user who created the share
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if the share link has expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the download limit has been reached
     */
    public function hasReachedLimit()
    {
        return $this->download_limit && $this->download_count >= $this->download_limit;
    }

    /**
     * Check if the share can be accessed with the given password
     */
    public function canAccess($password = null)
    {
        // Check if share is active
        if (!$this->is_active) {
            return false;
        }

        // Check if expired
        if ($this->isExpired()) {
            return false;
        }

        // Check if download limit reached
        if ($this->hasReachedLimit()) {
            return false;
        }

        // Check password if required
        if ($this->password_hash) {
            if (!$password || !password_verify($password, $this->password_hash)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Automatically generate token when creating a share
     */
    protected static function booted()
    {
        static::creating(function ($share) {
            if (empty($share->token)) {
                $share->token = Str::random(64);
            }
        });
    }
}
