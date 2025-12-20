<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'name',
        'slug',
        'color',
    ];

    /**
     * Get the band that owns the tag
     */
    public function band()
    {
        return $this->belongsTo(Bands::class);
    }

    /**
     * Get all media files associated with this tag
     */
    public function mediaFiles()
    {
        return $this->belongsToMany(MediaFile::class, 'media_file_tags');
    }

    /**
     * Automatically generate slug when creating a tag
     */
    protected static function booted()
    {
        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }
}
