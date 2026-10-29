<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One batch "Send to event" from a questionnaire instance. `before` holds
 * the event fields (and instance song_overrides) the batch overwrote so it
 * can be undone.
 */
class QuestionnaireApplications extends Model
{
    protected $table = 'questionnaire_applications';

    protected $fillable = [
        'instance_id',
        'event_id',
        'user_id',
        'before',
        'applied_response_ids',
        'event_updated_at',
        'event_fingerprint',
        'undone_at',
    ];

    protected $casts = [
        'before' => 'array',
        'applied_response_ids' => 'array',
        'event_updated_at' => 'datetime',
        'undone_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireInstances::class, 'instance_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
