<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'google_folder_id',
        'google_folder_name',
        'google_folder_path',
        'local_folder_path',
        'auto_sync',
        'last_synced_at',
        'sync_cursor',
    ];

    protected $casts = [
        'auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the connection this folder belongs to
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleDriveConnection::class, 'connection_id');
    }

    /**
     * Scope to get folders that have auto-sync enabled
     */
    public function scopeAutoSync($query)
    {
        return $query->where('auto_sync', true);
    }

    /**
     * Scope to get folders that need syncing
     */
    public function scopeNeedsSync($query)
    {
        return $query->autoSync()
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                  ->orWhere('last_synced_at', '<', now()->subHours(6));
            });
    }
}
