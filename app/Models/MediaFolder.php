<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'path',
        'created_by',
    ];

    /**
     * Get the band that owns the folder.
     */
    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    /**
     * Get the user who created the folder.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the folder name (last part of the path).
     */
    public function getNameAttribute(): string
    {
        $parts = explode('/', $this->path);
        return end($parts);
    }

    /**
     * Get the parent folder path.
     */
    public function getParentPathAttribute(): ?string
    {
        $lastSlash = strrpos($this->path, '/');
        if ($lastSlash === false) {
            return null;
        }
        return substr($this->path, 0, $lastSlash);
    }

    /**
     * Get the depth of the folder (0 for root level).
     */
    public function getDepthAttribute(): int
    {
        return substr_count($this->path, '/');
    }
}
