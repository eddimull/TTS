<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuestionnaireResponses extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'questionnaire_responses';

    protected $fillable = [
        'instance_id',
        'instance_field_id',
        'value',
        'applied_to_event_at',
        'applied_by_user_id',
    ];

    protected $casts = [
        'applied_to_event_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireInstances::class, 'instance_id');
    }

    public function instanceField(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireInstanceFields::class, 'instance_field_id');
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['value', 'applied_to_event_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('questionnaires');
    }
}
