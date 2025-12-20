<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BandStorageQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'quota_limit',
        'quota_used',
        'last_calculated_at',
    ];

    protected $casts = [
        'last_calculated_at' => 'datetime',
    ];

    /**
     * Get the band that owns the quota
     */
    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    /**
     * Get the quota usage percentage
     */
    public function getUsagePercentage()
    {
        if ($this->quota_limit == 0) {
            return 0;
        }

        return ($this->quota_used / $this->quota_limit) * 100;
    }

    /**
     * Check if the band has space for additional bytes
     */
    public function hasSpace($bytes)
    {
        return ($this->quota_used + $bytes) <= $this->quota_limit;
    }

    /**
     * Get formatted used space
     */
    public function getFormattedUsed()
    {
        return $this->formatBytes($this->quota_used);
    }

    /**
     * Get formatted quota limit
     */
    public function getFormattedLimit()
    {
        return $this->formatBytes($this->quota_limit);
    }

    /**
     * Format bytes into human-readable format
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Recalculate the quota usage from actual media files
     */
    public function recalculate()
    {
        $this->quota_used = MediaFile::where('band_id', $this->band_id)
            ->whereNull('deleted_at')
            ->sum('file_size');

        $this->last_calculated_at = now();
        $this->save();

        return $this;
    }
}
