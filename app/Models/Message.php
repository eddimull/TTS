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
        static::created(fn (self $m) => static::signalDmParticipants($m, 'created'));
        static::updated(fn (self $m) => static::signalDmParticipants($m, 'updated'));
        static::deleted(fn (self $m) => static::signalDmParticipants($m, 'deleted'));
    }

    /**
     * DM threads have no band — signal each participant's user channel
     * instead. Band/topic threads are covered by BroadcastsBandChanges.
     *
     * Mirrors BroadcastsBandChanges::broadcastBandChange(): a realtime
     * signal must never break the write that caused it.
     */
    protected static function signalDmParticipants(self $message, string $action): void
    {
        try {
            $conversation = $message->conversation;
            if (!$conversation || $conversation->type !== Conversation::TYPE_DM) {
                return;
            }
            foreach ($conversation->participants()->pluck('user_id') as $userId) {
                broadcast(new ConversationChanged((int) $userId, $conversation->id, $message->id, $action))
                    ->toOthers();
            }
        } catch (\Throwable $e) {
            report($e);
        }
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

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
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
