<?php

namespace App\Models;

use App\Events\ConversationChanged;
use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes, BroadcastsBandChanges;

    protected $fillable = ['conversation_id', 'user_id', 'body', 'edited_at'];

    protected $casts = ['edited_at' => 'datetime'];

    protected static function booted(): void
    {
        // DM threads have no band — signal each participant's user channel
        // instead. Band/topic threads are covered by BroadcastsBandChanges.
        $signalDm = function (self $message, string $action) {
            $conversation = $message->conversation;
            if (!$conversation || $conversation->type !== Conversation::TYPE_DM) {
                return;
            }
            foreach ($conversation->participants()->pluck('user_id') as $userId) {
                broadcast(new ConversationChanged((int) $userId, $conversation->id, $message->id, $action))
                    ->toOthers();
            }
        };

        static::created(fn (self $m) => $signalDm($m, 'created'));
        static::updated(fn (self $m) => $signalDm($m, 'updated'));
        static::deleted(fn (self $m) => $signalDm($m, 'deleted'));
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }

    protected function broadcastBandId(): ?int
    {
        // Null for DMs → the band-channel trait skips them silently.
        return $this->conversation?->band_id ? (int) $this->conversation->band_id : null;
    }

    protected function broadcastParent(): ?array
    {
        return ['model' => 'conversation', 'id' => (int) $this->conversation_id];
    }
}
