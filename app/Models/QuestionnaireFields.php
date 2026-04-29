<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireFields extends Model
{
    use HasFactory;

    protected $table = 'questionnaire_fields';

    protected $fillable = [
        'questionnaire_id',
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

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaires::class, 'questionnaire_id');
    }
}
