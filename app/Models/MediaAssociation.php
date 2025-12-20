<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaAssociation extends Model
{
    use HasFactory;

    protected $fillable = [
        'media_file_id',
        'associable_type',
        'associable_id',
    ];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the media file that owns the association
     */
    public function mediaFile()
    {
        return $this->belongsTo(MediaFile::class);
    }

    /**
     * Get the associated model (Event or Booking)
     */
    public function associable()
    {
        return $this->morphTo();
    }
}
