<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LodgingAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lodging_id', 'filename', 'stored_filename', 'mime_type', 'file_size', 'disk',
    ];

    public function lodging()
    {
        return $this->belongsTo(Lodging::class);
    }

    protected static function booted()
    {
        static::deleting(function ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->stored_filename);
        });
    }
}
