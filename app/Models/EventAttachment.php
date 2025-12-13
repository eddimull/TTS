<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EventAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'filename',
        'stored_filename',
        'mime_type',
        'file_size',
        'disk',
    ];

    /**
     * Get the event that owns the attachment
     */
    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    /**
     * Get the URL for the attachment
     */
    public function getUrlAttribute()
    {
        return Storage::disk($this->disk)->url($this->stored_filename);
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if attachment is an image
     */
    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if attachment is a PDF
     */
    public function isPdf()
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Delete the file from storage when model is deleted
     */
    protected static function booted()
    {
        static::deleting(function ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->stored_filename);
        });
    }
}
