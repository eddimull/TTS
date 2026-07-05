<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationLog extends Model
{
    protected $table = 'push_notification_log';

    protected $fillable = ['event_id', 'user_id', 'type', 'dedupe_key', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
