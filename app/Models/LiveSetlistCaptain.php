<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSetlistCaptain extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'promoted_by',
    ];

    public function session()
    {
        return $this->belongsTo(LiveSetlistSession::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function promotedBy()
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }
}
