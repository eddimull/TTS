<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'folder_id',
        'sync_type',
        'status',
        'files_checked',
        'files_downloaded',
        'files_updated',
        'files_deleted',
        'files_skipped',
        'bytes_transferred',
        'error_message',
        'error_details',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'files_checked' => 'integer',
        'files_downloaded' => 'integer',
        'files_updated' => 'integer',
        'files_deleted' => 'integer',
        'files_skipped' => 'integer',
        'bytes_transferred' => 'integer',
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the connection this log belongs to
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleDriveConnection::class, 'connection_id');
    }

    /**
     * Get the folder this log is for
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(GoogleDriveFolder::class, 'folder_id');
    }

    /**
     * Get the duration of the sync in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }
        return null;
    }

    /**
     * Get human-readable bytes transferred
     */
    public function getFormattedBytesAttribute(): string
    {
        $bytes = $this->bytes_transferred;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope to get failed syncs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get successful syncs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }
}
