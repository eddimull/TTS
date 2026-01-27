<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChunkedUpload extends Model
{
    use HasFactory;
    protected $fillable = [
        'upload_id',
        'user_id',
        'filename',
        'filesize',
        'mime_type',
        'folder_path',
        'event_id',
        'total_chunks',
        'chunks_uploaded',
        'status',
        'media_id',
        'last_chunk_at',
    ];

    protected $casts = [
        'filesize' => 'integer',
        'total_chunks' => 'integer',
        'chunks_uploaded' => 'integer',
        'last_chunk_at' => 'datetime',
    ];

    /**
     * Get the user that owns the upload.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the media record created from this upload.
     */
    public function media()
    {
        return $this->belongsTo(MediaFile::class, 'media_id');
    }
}
