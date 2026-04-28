<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuestionnaireInstanceFields extends Model
{
    use HasFactory;

    protected $table = 'questionnaire_instance_fields';

    protected $fillable = [
        'instance_id',
        'source_field_id',
        'type',
        'label',
        'help_text',
        'required',
        'position',
        'settings',
        'visibility_rule',
        'mapping_target',
    ];

    protected $casts = [
        'required' => 'boolean',
        'settings' => 'array',
        'visibility_rule' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireInstances::class, 'instance_id');
    }

    public function response(): HasOne
    {
        return $this->hasOne(QuestionnaireResponses::class, 'instance_field_id');
    }
}
