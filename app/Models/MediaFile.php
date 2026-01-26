<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'band_id',
        'user_id',
        'filename',
        'stored_filename',
        'mime_type',
        'file_size',
        'disk',
        'title',
        'description',
        'media_type',
        'folder_path',
    ];

    protected $appends = ['url', 'formatted_size', 'thumbnail_url'];

    protected $with = ['tags', 'uploader'];

    /**
     * Get the band that owns the media file
     */
    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    /**
     * Get the user who uploaded the media file
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the tags associated with this media file
     */
    public function tags()
    {
        return $this->belongsToMany(MediaTag::class, 'media_file_tags');
    }

    /**
     * Get all associations for this media file
     */
    public function associations()
    {
        return $this->hasMany(MediaAssociation::class);
    }

    /**
     * Get all share links for this media file
     */
    public function shares()
    {
        return $this->hasMany(MediaShare::class);
    }

    /**
     * Get the URL for the media file
     */
    public function getUrlAttribute()
    {
        // Use the media.serve route for serving files
        return url('/media/' . $this->id . '/serve');
    }

    /**
     * Get the thumbnail URL for images and videos
     */
    public function getThumbnailUrlAttribute()
    {
        if ($this->media_type === 'image' || $this->media_type === 'video') {
            // Check if thumbnail exists
            $thumbnailPath = str_replace(
                '.' . pathinfo($this->stored_filename, PATHINFO_EXTENSION),
                '_thumb.jpg',
                $this->stored_filename
            );

            if (Storage::disk($this->disk)->exists($thumbnailPath)) {
                return url('/media/' . $this->id . '/thumbnail');
            }
        }

        return null;
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if media file is an image
     */
    public function isImage()
    {
        return $this->media_type === 'image';
    }

    /**
     * Check if media file is a video
     */
    public function isVideo()
    {
        return $this->media_type === 'video';
    }

    /**
     * Check if media file is audio
     */
    public function isAudio()
    {
        return $this->media_type === 'audio';
    }

    /**
     * Check if media file is a PDF
     */
    public function isPdf()
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Delete the file from storage and update quota when model is deleted
     */
    protected static function booted()
    {
        static::deleting(function ($media) {
            // Delete file from storage
            try {
                Storage::disk($media->disk)->delete($media->stored_filename);

                // Delete thumbnail if it exists (for images and videos)
                if ($media->media_type === 'image' || $media->media_type === 'video') {
                    $thumbnailPath = str_replace(
                        '.' . pathinfo($media->stored_filename, PATHINFO_EXTENSION),
                        '_thumb.jpg',
                        $media->stored_filename
                    );
                    Storage::disk($media->disk)->delete($thumbnailPath);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to delete media file from storage', [
                    'media_file_id' => $media->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Update quota
            $quota = BandStorageQuota::firstOrCreate(
                ['band_id' => $media->band_id],
                [
                    'quota_limit' => 5368709120, // 5GB default
                    'quota_used' => 0
                ]
            );
            $quota->quota_used = max(0, $quota->quota_used - $media->file_size);
            $quota->save();

            // Delete associations and shares
            $media->associations()->delete();
            $media->shares()->delete();
        });
    }
}
