<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuestionnaireInstances extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_SENT = 'sent';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_LOCKED = 'locked';

    protected $table = 'questionnaire_instances';

    protected $fillable = [
        'questionnaire_id',
        'booking_id',
        'recipient_contact_id',
        'sent_by_user_id',
        'name',
        'description',
        'status',
        'sent_at',
        'first_opened_at',
        'submitted_at',
        'locked_at',
        'locked_by_user_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaires::class, 'questionnaire_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Bookings::class, 'booking_id');
    }

    public function recipientContact(): BelongsTo
    {
        return $this->belongsTo(Contacts::class, 'recipient_contact_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(QuestionnaireInstanceFields::class, 'instance_id')->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuestionnaireResponses::class, 'instance_id');
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'submitted_at', 'locked_at', 'locked_by_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('questionnaires');
    }
}
