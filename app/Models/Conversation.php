<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Conversation extends Model
{
    use HasFactory;

    public const TYPE_DM    = 'dm';
    public const TYPE_BAND  = 'band';
    public const TYPE_TOPIC = 'topic';

    protected $fillable = ['type', 'band_id', 'conversable_type', 'conversable_id', 'unique_key'];

    public static function dmKeyFor(int $userA, int $userB): string
    {
        return 'dm:' . min($userA, $userB) . ':' . max($userA, $userB);
    }

    public static function bandKeyFor(int $bandId): string
    {
        return 'band:' . $bandId;
    }

    public static function topicKeyFor(Model $conversable): string
    {
        return 'topic:' . get_class($conversable) . ':' . $conversable->getKey();
    }

    public function conversable(): MorphTo
    {
        return $this->morphTo();
    }

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
