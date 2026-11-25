<?php

namespace App\Observers;

use App\Jobs\ParseClientRequestNotes;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;

/**
 * Queues a re-read of the booking's questionnaire free text whenever the
 * submitted answers can have changed: an answer saved on a submitted or
 * locked questionnaire, a questionnaire submitted, re-submitted, reopened
 * or deleted.
 * The job itself skips the AI when the text is unchanged.
 */
class QuestionnaireClientNotesObserver
{
    private const COUNTED = [
        QuestionnaireInstances::STATUS_SUBMITTED,
        QuestionnaireInstances::STATUS_LOCKED,
    ];

    public function saved(QuestionnaireInstances|QuestionnaireResponses $model): void
    {
        if ($model instanceof QuestionnaireResponses) {
            $instance = $model->instance;
            $changed = $model->wasRecentlyCreated || $model->wasChanged('value');
            if ($changed && $instance && in_array($instance->status, self::COUNTED, true)) {
                ParseClientRequestNotes::queueFor($instance->booking_id);
            }

            return;
        }

        $counted = fn ($status) => in_array($status, self::COUNTED, true);
        $queue = $model->wasRecentlyCreated
            ? $counted($model->status)
            // Submitted or reopened.
            : ($model->wasChanged('status') && $counted($model->status) !== $counted($model->getOriginal('status')))
            // Re-submitted: the portal bulk-deletes hidden answers (no response
            // events) and only bumps submitted_at.
            || ($model->wasChanged('submitted_at') && $counted($model->status));
        if ($queue) {
            ParseClientRequestNotes::queueFor($model->booking_id);
        }
    }

    public function deleted(QuestionnaireInstances|QuestionnaireResponses $model): void
    {
        $instance = $model instanceof QuestionnaireResponses ? $model->instance : $model;
        if ($instance && in_array($instance->status, self::COUNTED, true)) {
            ParseClientRequestNotes::queueFor($instance->booking_id);
        }
    }
}
